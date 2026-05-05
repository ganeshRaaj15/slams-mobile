import * as Device from 'expo-device';
import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';

import { loginRequest, logoutRequest, meRequest, registerRequest } from '../api/endpoints';
import { setApiAccessToken, setUnauthorizedHandler } from '../api/client';
import {
  clearStoredNativePushToken,
  unregisterNativePushRegistration,
} from '../notifications/native-push';
import type { NativeUser } from '../types/api';
import { readErrorMessage } from '../utils/error-message';

const TOKEN_KEY = 'slams-native-access-token';

type AuthStatus = 'booting' | 'authenticated' | 'unauthenticated';

type AuthState = {
  status: AuthStatus;
  token: string | null;
  user: NativeUser | null;
  error: string | null;
  bootstrap: () => Promise<void>;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (payload: {
    username: string;
    email: string;
    password: string;
    password_confirm: string;
  }) => Promise<void>;
  signOut: () => Promise<void>;
  clearLocalSession: (message?: string) => Promise<void>;
};

function deviceName() {
  const brand = Device.brand?.trim();
  const model = Device.modelName?.trim();

  if (brand && model) {
    return `${brand} ${model}`;
  }

  if (model) {
    return model;
  }

  return 'SLAMS Mobile Device';
}

export const useAuthStore = create<AuthState>((set) => ({
  status: 'booting',
  token: null,
  user: null,
  error: null,
  bootstrap: async () => {
    const storedToken = await SecureStore.getItemAsync(TOKEN_KEY);

    if (!storedToken) {
      setApiAccessToken(null);
      set({
        status: 'unauthenticated',
        token: null,
        user: null,
        error: null,
      });
      return;
    }

    setApiAccessToken(storedToken);

    try {
      const response = await meRequest();
      set({
        status: 'authenticated',
        token: storedToken,
        user: response.user,
        error: null,
      });
    } catch (_error) {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
      setApiAccessToken(null);
      set({
        status: 'unauthenticated',
        token: null,
        user: null,
        error: 'Your session expired. Please sign in again.',
      });
    }
  },
  signIn: async (email, password) => {
    set({ error: null });

    try {
      const response = await loginRequest({
        email: email.trim(),
        password,
        device_name: deviceName(),
      });

      await SecureStore.setItemAsync(TOKEN_KEY, response.token);
      setApiAccessToken(response.token);

      set({
        status: 'authenticated',
        token: response.token,
        user: response.user,
        error: null,
      });
    } catch (error: unknown) {
      const message = readErrorMessage(error, 'Unable to sign in with those credentials.');

      set({
        status: 'unauthenticated',
        token: null,
        user: null,
        error: message,
      });

      throw error;
    }
  },
  signUp: async (payload) => {
    set({ error: null });

    try {
      const response = await registerRequest({
        ...payload,
        username: payload.username.trim(),
        email: payload.email.trim(),
        device_name: deviceName(),
      });

      await SecureStore.setItemAsync(TOKEN_KEY, response.token);
      setApiAccessToken(response.token);

      set({
        status: 'authenticated',
        token: response.token,
        user: response.user,
        error: null,
      });
    } catch (error: unknown) {
      const message = readErrorMessage(error, 'Unable to create that account.');

      set({
        status: 'unauthenticated',
        token: null,
        user: null,
        error: message,
      });

      throw error;
    }
  },
  signOut: async () => {
    await unregisterNativePushRegistration();

    try {
      await logoutRequest();
    } catch (_error) {
      // Ignore logout transport errors and clear the local session anyway.
    }

    await SecureStore.deleteItemAsync(TOKEN_KEY);
    setApiAccessToken(null);
    set({
      status: 'unauthenticated',
      token: null,
      user: null,
      error: null,
    });
  },
  clearLocalSession: async (message) => {
    await clearStoredNativePushToken();
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    setApiAccessToken(null);
    set({
      status: 'unauthenticated',
      token: null,
      user: null,
      error: message ?? 'Your session expired. Please sign in again.',
    });
  },
}));

setUnauthorizedHandler(() => {
  void useAuthStore.getState().clearLocalSession();
});
