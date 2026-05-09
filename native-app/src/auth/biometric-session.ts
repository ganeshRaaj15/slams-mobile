import * as SecureStore from 'expo-secure-store';

const BIOMETRIC_PREFERENCE_KEY = 'slams-native-biometric-enabled';
const BIOMETRIC_SESSION_READY_KEY = 'slams-native-biometric-session-ready';
const BIOMETRIC_TOKEN_KEY = 'slams-native-biometric-access-token';
const BIOMETRIC_ENABLE_PROMPTED_KEY = 'slams-native-biometric-enable-prompted';
const BIOMETRIC_KEYCHAIN_SERVICE = 'slams-native-biometric-session';

const biometricTokenOptions: SecureStore.SecureStoreOptions = {
  authenticationPrompt: 'Authenticate to unlock your SLAMS Mobile session.',
  keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
  keychainService: BIOMETRIC_KEYCHAIN_SERVICE,
  requireAuthentication: true,
};

const biometricDeleteOptions: SecureStore.SecureStoreOptions = {
  keychainService: BIOMETRIC_KEYCHAIN_SERVICE,
};

export type BiometricState = {
  isSupported: boolean;
  isEnabled: boolean;
  isReady: boolean;
};

export function isBiometricSupported() {
  return SecureStore.canUseBiometricAuthentication();
}

export async function getBiometricPreferenceEnabled() {
  if (!isBiometricSupported()) {
    return false;
  }

  return (await SecureStore.getItemAsync(BIOMETRIC_PREFERENCE_KEY)) === '1';
}

export async function loadBiometricState(): Promise<BiometricState> {
  const isSupported = isBiometricSupported();

  if (!isSupported) {
    return {
      isSupported: false,
      isEnabled: false,
      isReady: false,
    };
  }

  const isEnabled = (await SecureStore.getItemAsync(BIOMETRIC_PREFERENCE_KEY)) === '1';
  const isReady = isEnabled && (await SecureStore.getItemAsync(BIOMETRIC_SESSION_READY_KEY)) === '1';

  return {
    isSupported,
    isEnabled,
    isReady,
  };
}

export async function shouldPromptToEnableBiometrics() {
  if (!isBiometricSupported()) {
    return false;
  }

  const isEnabled = (await SecureStore.getItemAsync(BIOMETRIC_PREFERENCE_KEY)) === '1';
  if (isEnabled) {
    return false;
  }

  return (await SecureStore.getItemAsync(BIOMETRIC_ENABLE_PROMPTED_KEY)) !== '1';
}

export async function markBiometricEnablePrompted() {
  if (!isBiometricSupported()) {
    return;
  }

  await SecureStore.setItemAsync(BIOMETRIC_ENABLE_PROMPTED_KEY, '1');
}

export async function saveBiometricSessionToken(token: string) {
  if (!isBiometricSupported()) {
    throw new Error('Biometric authentication is not available on this device.');
  }

  await SecureStore.setItemAsync(BIOMETRIC_TOKEN_KEY, token, biometricTokenOptions);
  await SecureStore.setItemAsync(BIOMETRIC_PREFERENCE_KEY, '1');
  await SecureStore.setItemAsync(BIOMETRIC_SESSION_READY_KEY, '1');
  await SecureStore.setItemAsync(BIOMETRIC_ENABLE_PROMPTED_KEY, '1');
}

export async function readBiometricSessionToken() {
  if (!isBiometricSupported()) {
    return null;
  }

  return SecureStore.getItemAsync(BIOMETRIC_TOKEN_KEY, biometricTokenOptions);
}

export async function clearBiometricSession(options?: { clearPreference?: boolean }) {
  await SecureStore.deleteItemAsync(BIOMETRIC_TOKEN_KEY, biometricDeleteOptions);
  await SecureStore.deleteItemAsync(BIOMETRIC_SESSION_READY_KEY);

  if (options?.clearPreference) {
    await SecureStore.deleteItemAsync(BIOMETRIC_PREFERENCE_KEY);
  }
}
