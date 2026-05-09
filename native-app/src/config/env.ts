import { Platform } from 'react-native';

const fallbackBaseUrl =
  Platform.OS === 'android' ? 'http://10.0.2.2:8080' : 'http://localhost:8080';

export const CONFIGURED_API_BASE_URL = (
  process.env.EXPO_PUBLIC_API_BASE_URL?.trim() || fallbackBaseUrl
).replace(/\/+$/, '');

export const API_BASE_URL = CONFIGURED_API_BASE_URL;

export const APP_VARIANT = (
  process.env.EXPO_PUBLIC_APP_VARIANT?.trim().toLowerCase() ||
  (typeof __DEV__ !== 'undefined' && __DEV__ ? 'development' : 'production')
);

export const REQUEST_TIMEOUT_MS = 20000;

const runtimeIsDev = typeof __DEV__ !== 'undefined' ? __DEV__ : false;

function normalizeBasePath(pathname: string) {
  const normalized = pathname.trim();
  if (!normalized || normalized === '/') {
    return '';
  }

  return `/${normalized.replace(/^\/+|\/+$/g, '')}`;
}

function joinBasePath(basePath: string, pathname: string) {
  const normalizedPathname = pathname.startsWith('/') ? pathname : `/${pathname}`;

  if (!basePath) {
    return normalizedPathname;
  }

  if (normalizedPathname === basePath || normalizedPathname.startsWith(`${basePath}/`)) {
    return normalizedPathname;
  }

  if (normalizedPathname === '/') {
    return `${basePath}/`;
  }

  return `${basePath}${normalizedPathname}`;
}

export function normalizeHost(host: string) {
  const normalized = host.trim().toLowerCase();
  if (!normalized) {
    return '';
  }

  if (normalized.startsWith('[')) {
    const closingBracket = normalized.indexOf(']');
    if (closingBracket !== -1) {
      return normalized.slice(1, closingBracket);
    }
  }

  if (normalized.split(':').length === 2 && !normalized.includes('::')) {
    return normalized.split(':')[0];
  }

  return normalized;
}

export function isPrivateOrLoopbackIpv4(host: string) {
  const normalized = normalizeHost(host);
  if (!/^\d{1,3}(?:\.\d{1,3}){3}$/.test(normalized)) {
    return false;
  }

  const octets = normalized.split('.').map((segment) => Number(segment));
  if (octets.some((octet) => Number.isNaN(octet) || octet < 0 || octet > 255)) {
    return false;
  }

  if (
    octets[0] === 10
    || octets[0] === 127
    || (octets[0] === 169 && octets[1] === 254)
    || (octets[0] === 192 && octets[1] === 168)
  ) {
    return true;
  }

  return octets[0] === 172 && octets[1] >= 16 && octets[1] <= 31;
}

export function isLocalDevelopmentHost(host: string) {
  const normalized = normalizeHost(host);
  if (!normalized) {
    return false;
  }

  return (
    normalized === 'localhost'
    || normalized === '127.0.0.1'
    || normalized === '::1'
    || normalized === '10.0.2.2'
    || normalized.endsWith('.test')
    || isPrivateOrLoopbackIpv4(normalized)
  );
}

function isLoopbackUrl(url: string) {
  return /^https?:\/\/(localhost|127\.0\.0\.1|10\.0\.2\.2)(:\d+)?$/i.test(url);
}

export function getBaseUrlPathPrefix(url: string) {
  try {
    return normalizeBasePath(new URL(url).pathname);
  } catch {
    return '';
  }
}

export function resolveBackendUrl(url: string, baseUrl = API_BASE_URL) {
  const trimmedUrl = url.trim();
  if (!trimmedUrl) {
    return '';
  }

  try {
    const resolvedApiBase = new URL(baseUrl);
    const resolvedUrl = new URL(trimmedUrl, baseUrl);
    const resolvedApiBasePath = normalizeBasePath(resolvedApiBase.pathname);

    if (isLocalDevelopmentHost(resolvedUrl.hostname)) {
      resolvedUrl.protocol = resolvedApiBase.protocol;
      resolvedUrl.hostname = resolvedApiBase.hostname;
      resolvedUrl.port = resolvedApiBase.port;
      resolvedUrl.pathname = joinBasePath(resolvedApiBasePath, resolvedUrl.pathname);
    }

    return resolvedUrl.toString();
  } catch {
    if (trimmedUrl.startsWith('/')) {
      return `${baseUrl}${trimmedUrl}`;
    }

    return trimmedUrl;
  }
}

export function getRuntimeConfigurationIssues(): string[] {
  const issues: string[] = [];
  const configuredBaseUrl = process.env.EXPO_PUBLIC_API_BASE_URL?.trim() || '';
  const isProductionVariant = APP_VARIANT === 'production';

  if (!runtimeIsDev && isProductionVariant && configuredBaseUrl === '') {
    issues.push('EXPO_PUBLIC_API_BASE_URL is missing for the release build.');
  }

  if (!runtimeIsDev && isProductionVariant && !CONFIGURED_API_BASE_URL.startsWith('https://')) {
    issues.push('Release builds must use an HTTPS API base URL.');
  }

  if (!runtimeIsDev && isProductionVariant && isLoopbackUrl(CONFIGURED_API_BASE_URL)) {
    issues.push('Release builds cannot point to localhost or emulator loopback addresses.');
  }

  if (!runtimeIsDev && isProductionVariant && !(process.env.EXPO_PUBLIC_EAS_PROJECT_ID?.trim() || '')) {
    issues.push('EXPO_PUBLIC_EAS_PROJECT_ID is missing, so native push registration will fail.');
  }

  return issues;
}
