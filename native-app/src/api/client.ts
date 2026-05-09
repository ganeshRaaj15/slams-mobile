import axios from 'axios';

import { REQUEST_TIMEOUT_MS } from '../config/env';
import { getCurrentApiBaseUrl, getResolvedApiBaseUrl } from './runtime-base-url';

let accessToken: string | null = null;
let unauthorizedHandler: (() => void) | null = null;

export const api = axios.create({
  baseURL: getCurrentApiBaseUrl(),
  timeout: REQUEST_TIMEOUT_MS,
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use(async (config) => {
  config.baseURL = await getResolvedApiBaseUrl();

  if (accessToken) {
    config.headers.Authorization = `Bearer ${accessToken}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401 && unauthorizedHandler) {
      unauthorizedHandler();
    }

    const originalRequest = error.config as (typeof error.config & {
      _slamsDiscoveryRetry?: boolean;
    }) | undefined;

    if (!error.response && originalRequest && !originalRequest._slamsDiscoveryRetry) {
      const previousBaseUrl = String(originalRequest.baseURL ?? getCurrentApiBaseUrl());
      const refreshedBaseUrl = await getResolvedApiBaseUrl({ forceRefresh: true }).catch(() => previousBaseUrl);

      if (refreshedBaseUrl && refreshedBaseUrl !== previousBaseUrl) {
        originalRequest._slamsDiscoveryRetry = true;
        originalRequest.baseURL = refreshedBaseUrl;

        return api.request(originalRequest);
      }
    }

    return Promise.reject(error);
  },
);

export function setApiAccessToken(token: string | null) {
  accessToken = token;
}

export function setUnauthorizedHandler(handler: () => void) {
  unauthorizedHandler = handler;
}

export function getApiAccessToken() {
  return accessToken;
}
