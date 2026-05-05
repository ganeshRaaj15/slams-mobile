import { Platform } from 'react-native';

const fallbackBaseUrl =
  Platform.OS === 'android' ? 'http://10.0.2.2:8080' : 'http://localhost:8080';

export const API_BASE_URL = (
  process.env.EXPO_PUBLIC_API_BASE_URL?.trim() || fallbackBaseUrl
).replace(/\/+$/, '');

export const APP_VARIANT = (
  process.env.EXPO_PUBLIC_APP_VARIANT?.trim().toLowerCase() ||
  (typeof __DEV__ !== 'undefined' && __DEV__ ? 'development' : 'production')
);

export const REQUEST_TIMEOUT_MS = 20000;

const runtimeIsDev = typeof __DEV__ !== 'undefined' ? __DEV__ : false;

function isLoopbackUrl(url: string) {
  return /^https?:\/\/(localhost|127\.0\.0\.1|10\.0\.2\.2)(:\d+)?$/i.test(url);
}

export function resolveBackendUrl(url: string) {
  const trimmedUrl = url.trim();
  if (!trimmedUrl) {
    return '';
  }

  try {
    const resolvedApiBase = new URL(API_BASE_URL);
    const resolvedUrl = new URL(trimmedUrl, API_BASE_URL);

    if (/^(localhost|127\.0\.0\.1|10\.0\.2\.2)$/i.test(resolvedUrl.hostname)) {
      resolvedUrl.protocol = resolvedApiBase.protocol;
      resolvedUrl.hostname = resolvedApiBase.hostname;
      resolvedUrl.port = resolvedApiBase.port;
    }

    return resolvedUrl.toString();
  } catch {
    if (trimmedUrl.startsWith('/')) {
      return `${API_BASE_URL}${trimmedUrl}`;
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

  if (!runtimeIsDev && isProductionVariant && !API_BASE_URL.startsWith('https://')) {
    issues.push('Release builds must use an HTTPS API base URL.');
  }

  if (!runtimeIsDev && isProductionVariant && isLoopbackUrl(API_BASE_URL)) {
    issues.push('Release builds cannot point to localhost or emulator loopback addresses.');
  }

  if (!runtimeIsDev && isProductionVariant && !(process.env.EXPO_PUBLIC_EAS_PROJECT_ID?.trim() || '')) {
    issues.push('EXPO_PUBLIC_EAS_PROJECT_ID is missing, so native push registration will fail.');
  }

  return issues;
}
