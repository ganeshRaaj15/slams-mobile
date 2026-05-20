import * as Device from 'expo-device';
import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';

import { loginRequest, logoutRequest, meRequest, registerRequest, verifyOtpRequest } from '../api/endpoints';
import {
  clearBiometricSession,
  getBiometricPreferenceEnabled,
  loadBiometricState,
  readBiometricSessionToken,
  saveBiometricSessionToken,
  type BiometricState,
} from '../auth/biometric-session';
import { setApiAccessToken, setUnauthorizedHandler } from '../api/client';
import {
  clearStoredNativePushToken,
  unregisterNativePushRegistration,
} from '../notifications/native-push';
import type { NativeUser } from '../types/api';
import { readErrorMessage } from '../utils/error-message';

const TOKEN_KEY = 'slams-native-access-token';

type AuthStatus = 'booting' | 'authenticated' | 'unauthenticated' | 'otp_pending';

const defaultBiometricState: BiometricState = {
  isSupported: false,
  isEnabled: false,
  isReady: false,
};

type AuthState = {
  status: AuthStatus;
  token: string | null;
  user: NativeUser | null;
  error: string | null;
  biometric: BiometricState;
  otpToken: string | null;
  otpDeviceName: string | null;
  bootstrap: () => Promise<void>;
  replaceUser: (user: NativeUser) => void;
  signIn: (email: string, password: string) => Promise<void>;
  submitOtp: (otpCode: string) => Promise<void>;
  signInWithBiometrics: () => Promise<void>;
  signUp: (payload: {
    username: string;
    email: string;
    password: string;
    password_confirm: string;
  }) => Promise<void>;
  signOut: () => Promise<void>;
  clearLocalSession: (message?: string) => Promise<void>;
  refreshBiometricState: () => Promise<void>;
  setBiometricPreference: (enabled: boolean) => Promise<void>;
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

async function persistSessionToken(token: string) {
  const biometricEnabled = await getBiometricPreferenceEnabled();

  if (biometricEnabled) {
    try {
      await saveBiometricSessionToken(token);
      await SecureStore.deleteItemAsync(TOKEN_KEY);
      return loadBiometricState();
    } catch (_error) {
      await clearBiometricSession({ clearPreference: true });
    }
  }

  await SecureStore.setItemAsync(TOKEN_KEY, token);
  await clearBiometricSession();

  return loadBiometricState();
}

export const useAuthStore = create<AuthState>((set, get) => ({
  status: 'booting',
  token: null,
  user: null,
  error: null,
  biometric: defaultBiometricState,
  otpToken: null,
  otpDeviceName: null,
  bootstrap: async () => {
    const biometric = await loadBiometricState();

    if (biometric.isEnabled) {
      setApiAccessToken(null);
      set({
        status: 'unauthenticated',
        token: null,
        user: null,
        error: null,
        biometric,
      });
      return;
    }

    const storedToken = await SecureStore.getItemAsync(TOKEN_KEY);

    if (!storedToken) {
      setApiAccessToken(null);
      set({
        status: 'unauthenticated',
        token: null,
        user: null,
        error: null,
        biometric,
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
        biometric,
      });
    } catch (_error) {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
      setApiAccessToken(null);
      set({
        status: 'unauthenticated',
        token: null,
        user: null,
        error: 'Your session expired. Please sign in again.',
        biometric,
      });
    }
  },
  replaceUser: (user) => {
    set((state) =>
      state.status === 'authenticated'
        ? {
            user,
            error: null,
          }
        : state,
    );
  },
  signIn: async (email, password) => {
    set({ error: null });

    try {
      const device = deviceName();
      const response = await loginRequest({
        email: email.trim(),
        password,
        device_name: device,
      });

      if (response.status === 'otp_required') {
        set({
          status: 'otp_pending',
          otpToken: response.otp_token,
          otpDeviceName: device,
          error: null,
        });
        return;
      }

      const biometric = await persistSessionToken(response.token);
      setApiAccessToken(response.token);

      set({
        status: 'authenticated',
        token: response.token,
        user: response.user,
        error: null,
        biometric,
        otpToken: null,
        otpDeviceName: null,
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
  submitOtp: async (otpCode) => {
    set({ error: null });

    const { otpToken, otpDeviceName } = get();

    if (!otpToken || !otpDeviceName) {
      set({ error: 'OTP session expired. Please sign in again.', status: 'unauthenticated' });
      return;
    }

    try {
      const response = await verifyOtpRequest({
        otp_token: otpToken,
        otp_code: otpCode,
        device_name: otpDeviceName,
      });

      const biometric = await persistSessionToken(response.token);
      setApiAccessToken(response.token);

      set({
        status: 'authenticated',
        token: response.token,
        user: response.user,
        error: null,
        biometric,
        otpToken: null,
        otpDeviceName: null,
      });
    } catch (error: unknown) {
      const message = readErrorMessage(error, 'Invalid verification code.');
      set({ error: message });
      throw error;
    }
  },
  signInWithBiometrics: async () => {
    set({ error: null });

    try {
      const token = await readBiometricSessionToken();

      if (!token) {
        await clearBiometricSession();

        set({
          status: 'unauthenticated',
          token: null,
          user: null,
          error: 'Biometric sign-in is no longer available. Please sign in with your email and password.',
          biometric: await loadBiometricState(),
        });
        return;
      }

      setApiAccessToken(token);

      try {
        const response = await meRequest();
        set({
          status: 'authenticated',
          token,
          user: response.user,
          error: null,
          biometric: await loadBiometricState(),
        });
      } catch (_error) {
        await clearBiometricSession();
        setApiAccessToken(null);
        set({
          status: 'unauthenticated',
          token: null,
          user: null,
          error: 'Your saved biometric session expired. Please sign in again.',
          biometric: await loadBiometricState(),
        });
      }
    } catch (error: unknown) {
      await clearBiometricSession();
      setApiAccessToken(null);
      set({
        status: 'unauthenticated',
        token: null,
        user: null,
        error: readErrorMessage(error, 'Biometric verification did not complete.'),
        biometric: await loadBiometricState(),
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

      const biometric = await persistSessionToken(response.token);
      setApiAccessToken(response.token);

      set({
        status: 'authenticated',
        token: response.token,
        user: response.user,
        error: null,
        biometric,
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
      // Ignore transport errors and clear the local session anyway.
    }

    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await clearBiometricSession();
    setApiAccessToken(null);
    set({
      status: 'unauthenticated',
      token: null,
      user: null,
      error: null,
      biometric: await loadBiometricState(),
      otpToken: null,
      otpDeviceName: null,
    });
  },
  clearLocalSession: async (message) => {
    await clearStoredNativePushToken();
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await clearBiometricSession();
    setApiAccessToken(null);
    set({
      status: 'unauthenticated',
      token: null,
      user: null,
      error: message ?? 'Your session expired. Please sign in again.',
      biometric: await loadBiometricState(),
      otpToken: null,
      otpDeviceName: null,
    });
  },
  refreshBiometricState: async () => {
    set({
      biometric: await loadBiometricState(),
    });
  },
  setBiometricPreference: async (enabled) => {
    if (!enabled) {
      const currentToken = useAuthStore.getState().token;

      if (currentToken) {
        await SecureStore.setItemAsync(TOKEN_KEY, currentToken);
      }

      await clearBiometricSession({ clearPreference: true });
      set({
        biometric: await loadBiometricState(),
      });
      return;
    }

    const currentToken = useAuthStore.getState().token;

    if (!currentToken) {
      throw new Error('Sign in before enabling biometric login.');
    }

    await saveBiometricSessionToken(currentToken);
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    set({
      biometric: await loadBiometricState(),
    });
  },
}));

setUnauthorizedHandler(() => {
  void useAuthStore.getState().clearLocalSession();
});
