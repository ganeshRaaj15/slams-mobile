import * as SecureStore from 'expo-secure-store';

const PUSH_PERMISSION_PROMPTED_KEY = 'slams-native-push-permission-prompted';

export async function hasPromptedForNativePushPermission() {
  return (await SecureStore.getItemAsync(PUSH_PERMISSION_PROMPTED_KEY)) === '1';
}

export async function markNativePushPermissionPrompted() {
  await SecureStore.setItemAsync(PUSH_PERMISSION_PROMPTED_KEY, '1');
}
