import { fetch as expoFetch } from 'expo/fetch';

import { buildApiUrl, getCurrentApiBaseUrl, getResolvedApiBaseUrl } from './runtime-base-url';

type ExpoFetchInit = Omit<RequestInit, 'body' | 'signal'> & {
  body?: BodyInit;
  signal?: AbortSignal;
};

export async function fetchApi(path: string, init?: ExpoFetchInit) {
  const initialBaseUrl = await getResolvedApiBaseUrl();

  try {
    return await expoFetch(buildApiUrl(path, initialBaseUrl), init);
  } catch (error) {
    const refreshedBaseUrl = await getResolvedApiBaseUrl({ forceRefresh: true }).catch(() => getCurrentApiBaseUrl());
    if (!refreshedBaseUrl || refreshedBaseUrl === initialBaseUrl) {
      throw error;
    }

    return await expoFetch(buildApiUrl(path, refreshedBaseUrl), init);
  }
}
