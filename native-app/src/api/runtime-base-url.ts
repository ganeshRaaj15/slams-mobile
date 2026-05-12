import * as Network from 'expo-network';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import {
  APP_VARIANT,
  CONFIGURED_API_BASE_URL,
  REQUEST_TIMEOUT_MS,
  getBaseUrlPathPrefix,
  isLocalDevelopmentHost,
  isPrivateOrLoopbackIpv4,
  normalizeHost,
  resolveBackendUrl,
} from '../config/env';

const STORED_API_BASE_URL_KEY = 'slams-native-api-base-url';
const HEALTH_PATH = '/api/native/health';
const HEALTH_SERVICES = new Set(['slams-api', 'slams-mobile-api']);
const DIRECT_PROBE_TIMEOUT_MS = Math.max(700, Math.min(1600, Math.floor(REQUEST_TIMEOUT_MS / 4)));
const DISCOVERY_PROBE_TIMEOUT_MS = 650;
const DISCOVERY_BATCH_SIZE = 18;
const NEARBY_DEVICE_OCTET_SCAN_RANGE = 24;

type BaseUrlPattern = {
  pathPrefix: string;
  port: number;
};

const COMMON_LOCAL_BASE_URL_PATTERNS: BaseUrlPattern[] = [
  { port: 80, pathPrefix: '/slams/public' },
  { port: 80, pathPrefix: '/slams-mobile/public' },
  { port: 80, pathPrefix: '/public' },
  { port: 80, pathPrefix: '' },
  { port: 8081, pathPrefix: '' },
  { port: 8080, pathPrefix: '' },
  { port: 8081, pathPrefix: '/slams/public' },
  { port: 8080, pathPrefix: '/slams/public' },
  { port: 8081, pathPrefix: '/slams-mobile/public' },
  { port: 8080, pathPrefix: '/slams-mobile/public' },
  { port: 8081, pathPrefix: '/public' },
  { port: 8080, pathPrefix: '/public' },
];

let currentApiBaseUrl = CONFIGURED_API_BASE_URL;
let hasValidatedCurrentApiBaseUrl = false;
let cachedStoredApiBaseUrl: string | null | undefined;
let resolutionPromise: Promise<string> | null = null;

export function getCurrentApiBaseUrl() {
  return currentApiBaseUrl;
}

export async function primeResolvedApiBaseUrl() {
  return getResolvedApiBaseUrl();
}

export async function getResolvedApiBaseUrl({ forceRefresh = false }: { forceRefresh?: boolean } = {}) {
  if (!forceRefresh && hasValidatedCurrentApiBaseUrl && currentApiBaseUrl) {
    return currentApiBaseUrl;
  }

  if (!forceRefresh && resolutionPromise) {
    return resolutionPromise;
  }

  resolutionPromise = resolveApiBaseUrl(forceRefresh).finally(() => {
    resolutionPromise = null;
  });

  return resolutionPromise;
}

export async function resolveBackendUrlAsync(url: string) {
  const baseUrl = await getResolvedApiBaseUrl();
  return resolveBackendUrl(url, baseUrl);
}

export function buildApiUrl(path: string, baseUrl = getCurrentApiBaseUrl()) {
  return resolveBackendUrl(path, baseUrl);
}

async function resolveApiBaseUrl(forceRefresh: boolean) {
  const candidates = await directCandidates(forceRefresh);

  for (const candidate of candidates) {
    if (await probeApiBaseUrl(candidate, DIRECT_PROBE_TIMEOUT_MS)) {
      await rememberResolvedApiBaseUrl(candidate);
      return candidate;
    }
  }

  if (shouldAttemptLanDiscovery(candidates)) {
    const discoveredBaseUrl = await discoverLanBaseUrl(candidates);
    if (discoveredBaseUrl) {
      await rememberResolvedApiBaseUrl(discoveredBaseUrl);
      return discoveredBaseUrl;
    }
  }

  hasValidatedCurrentApiBaseUrl = false;

  throw new Error(
    'The SLAMS Mobile backend could not be reached. Confirm that the backend server is running and that this device is on the same local network.',
  );
}

async function directCandidates(forceRefresh: boolean) {
  const candidates: string[] = [];

  appendCandidate(candidates, currentApiBaseUrl);
  appendExpandedLocalCandidates(candidates, currentApiBaseUrl);
  appendCandidate(candidates, CONFIGURED_API_BASE_URL);
  appendExpandedLocalCandidates(candidates, CONFIGURED_API_BASE_URL);

  const storedApiBaseUrl = await readStoredApiBaseUrl();
  if (!forceRefresh) {
    appendCandidate(candidates, storedApiBaseUrl);
    appendExpandedLocalCandidates(candidates, storedApiBaseUrl);
  } else {
    appendCandidate(candidates, storedApiBaseUrl);
    appendExpandedLocalCandidates(candidates, storedApiBaseUrl);
  }

  for (const host of emulatorHosts()) {
    appendCandidatesForHost(candidates, host);
  }

  return candidates;
}

function emulatorHosts() {
  if (Platform.OS === 'android') {
    return ['10.0.2.2'];
  }

  if (Platform.OS === 'ios') {
    return ['localhost'];
  }

  return [];
}

function shouldAttemptLanDiscovery(candidates: string[]) {
  return APP_VARIANT !== 'production' && candidates.some(isLocalDevelopmentBaseUrl);
}

async function discoverLanBaseUrl(existingCandidates: string[]) {
  const networkState = await Network.getNetworkStateAsync().catch(() => null);
  if (networkState?.isConnected === false) {
    return null;
  }

  const deviceIp = normalizeHost(await Network.getIpAddressAsync().catch(() => ''));
  if (!isPrivateOrLoopbackIpv4(deviceIp) || deviceIp === '0.0.0.0') {
    return null;
  }

  const ipSegments = deviceIp.split('.');
  if (ipSegments.length !== 4) {
    return null;
  }

  const deviceOctet = Number(ipSegments[3]);
  if (!Number.isFinite(deviceOctet)) {
    return null;
  }

  const subnetPrefix = ipSegments.slice(0, 3).join('.');
  const hostCandidates = subnetHostCandidates(subnetPrefix, deviceOctet, existingCandidates);
  for (const pattern of preferredDiscoveryPatterns()) {
    const baseUrlCandidates: string[] = [];

    for (const host of hostCandidates) {
      appendCandidate(baseUrlCandidates, buildBaseUrl(host, pattern));
    }

    for (let start = 0; start < baseUrlCandidates.length; start += DISCOVERY_BATCH_SIZE) {
      const batch = baseUrlCandidates.slice(start, start + DISCOVERY_BATCH_SIZE);
      const results = await Promise.all(
        batch.map(async (candidate) => (
          (await probeApiBaseUrl(candidate, DISCOVERY_PROBE_TIMEOUT_MS)) ? candidate : null
        )),
      );

      const winner = results.find((candidate): candidate is string => typeof candidate === 'string');
      if (winner) {
        return winner;
      }
    }
  }

  return null;
}

function subnetHostCandidates(subnetPrefix: string, deviceOctet: number, hints: string[]) {
  const hosts: string[] = [];
  const seenOctets = new Set<number>();

  const pushOctet = (octet: number) => {
    if (
      !Number.isFinite(octet)
      || octet < 1
      || octet > 254
      || octet === deviceOctet
      || seenOctets.has(octet)
    ) {
      return;
    }

    seenOctets.add(octet);
    hosts.push(`${subnetPrefix}.${octet}`);
  };

  pushOctet(1);

  for (const hint of hints) {
    const parsed = parseBaseUrl(hint);
    if (!parsed || !isPrivateOrLoopbackIpv4(parsed.hostname)) {
      continue;
    }

    const hintOctet = Number(parsed.hostname.split('.')[3] ?? '');
    pushOctet(hintOctet);
  }

  for (let distance = 1; distance <= NEARBY_DEVICE_OCTET_SCAN_RANGE; distance += 1) {
    pushOctet(deviceOctet - distance);
    pushOctet(deviceOctet + distance);
  }

  for (let octet = 2; octet <= 254; octet += 1) {
    pushOctet(octet);
  }

  return hosts;
}

function appendExpandedLocalCandidates(candidates: string[], candidate: string | null | undefined) {
  const parsed = parseBaseUrl(candidate ?? '');
  if (!parsed || !isLocalDevelopmentHost(parsed.hostname)) {
    return;
  }

  appendCandidatesForHost(candidates, parsed.hostname, parsed.protocol);
}

function appendCandidatesForHost(candidates: string[], host: string, protocol = 'http:') {
  for (const pattern of preferredDirectPatterns()) {
    appendCandidate(candidates, buildBaseUrl(host, pattern, protocol));
  }
}

function preferredDirectPatterns() {
  return dedupeBaseUrlPatterns([
    ...hintedBaseUrlPatterns(),
    ...COMMON_LOCAL_BASE_URL_PATTERNS,
  ]);
}

function preferredDiscoveryPatterns() {
  return dedupeBaseUrlPatterns([
    ...COMMON_LOCAL_BASE_URL_PATTERNS,
    ...hintedBaseUrlPatterns(),
  ]);
}

function hintedBaseUrlPatterns() {
  const patterns: BaseUrlPattern[] = [];

  for (const candidate of [currentApiBaseUrl, CONFIGURED_API_BASE_URL, cachedStoredApiBaseUrl ?? '']) {
    const parsed = parseBaseUrl(candidate);
    if (!parsed || !isLocalDevelopmentHost(parsed.hostname)) {
      continue;
    }

    const port = extractPort(candidate);
    if (port === null) {
      continue;
    }

    patterns.push({
      port,
      pathPrefix: getBaseUrlPathPrefix(candidate),
    });
  }

  return patterns;
}

function dedupeBaseUrlPatterns(patterns: BaseUrlPattern[]) {
  const seenPatterns = new Set<string>();
  const dedupedPatterns: BaseUrlPattern[] = [];

  for (const pattern of patterns) {
    const normalizedPattern = normalizeBaseUrlPattern(pattern);
    const key = `${normalizedPattern.port}|${normalizedPattern.pathPrefix}`;
    if (seenPatterns.has(key)) {
      continue;
    }

    seenPatterns.add(key);
    dedupedPatterns.push(normalizedPattern);
  }

  return dedupedPatterns;
}

function normalizeBaseUrlPattern(pattern: BaseUrlPattern) {
  return {
    port: pattern.port,
    pathPrefix: normalizePathPrefix(pattern.pathPrefix),
  };
}

function normalizePathPrefix(pathPrefix: string | null | undefined) {
  const normalized = (pathPrefix ?? '').trim();
  if (!normalized || normalized === '/') {
    return '';
  }

  return `/${normalized.replace(/^\/+|\/+$/g, '')}`;
}

function buildBaseUrl(host: string, pattern: BaseUrlPattern, protocol = 'http:') {
  const normalizedProtocol = protocol.replace(/:$/, '');
  const defaultPort = normalizedProtocol === 'https' ? 443 : 80;
  const portSegment = pattern.port === defaultPort ? '' : `:${pattern.port}`;

  return `${normalizedProtocol}://${host}${portSegment}${normalizePathPrefix(pattern.pathPrefix)}`;
}

async function rememberResolvedApiBaseUrl(baseUrl: string) {
  currentApiBaseUrl = baseUrl;
  hasValidatedCurrentApiBaseUrl = true;
  cachedStoredApiBaseUrl = baseUrl;

  try {
    await SecureStore.setItemAsync(STORED_API_BASE_URL_KEY, baseUrl);
  } catch {
    // Ignore persistence failures and keep the in-memory value.
  }
}

async function readStoredApiBaseUrl() {
  if (cachedStoredApiBaseUrl !== undefined) {
    return cachedStoredApiBaseUrl;
  }

  try {
    cachedStoredApiBaseUrl = normalizeBaseUrl(await SecureStore.getItemAsync(STORED_API_BASE_URL_KEY));
  } catch {
    cachedStoredApiBaseUrl = null;
  }

  return cachedStoredApiBaseUrl;
}

async function probeApiBaseUrl(baseUrl: string, timeoutMs: number) {
  if (!baseUrl) {
    return false;
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(`${baseUrl}${HEALTH_PATH}`, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
      },
      signal: controller.signal,
    });

    if (!response.ok) {
      return false;
    }

    const payload = (await response.json().catch(() => null)) as
      | { status?: string; service?: string }
      | null;

    return payload?.status === 'success' && HEALTH_SERVICES.has(payload?.service ?? '');
  } catch {
    return false;
  } finally {
    clearTimeout(timeout);
  }
}

function appendCandidate(candidates: string[], candidate: string | null | undefined) {
  const normalized = normalizeBaseUrl(candidate);
  if (!normalized || candidates.includes(normalized)) {
    return;
  }

  candidates.push(normalized);
}

function normalizeBaseUrl(url: string | null | undefined) {
  return (url ?? '').trim().replace(/\/+$/, '');
}

function parseBaseUrl(url: string) {
  try {
    return new URL(url);
  } catch {
    return null;
  }
}

function extractPort(url: string) {
  const parsed = parseBaseUrl(url);
  if (!parsed) {
    return null;
  }

  if (parsed.port) {
    const parsedPort = Number(parsed.port);
    return Number.isFinite(parsedPort) ? parsedPort : null;
  }

  return parsed.protocol === 'https:' ? 443 : 80;
}

function isLocalDevelopmentBaseUrl(url: string) {
  const parsed = parseBaseUrl(url);
  return parsed ? isLocalDevelopmentHost(parsed.hostname) : false;
}
