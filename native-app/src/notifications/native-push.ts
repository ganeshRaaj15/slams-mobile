import { Platform } from 'react-native';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';

import {
  registerNativePushTokenRequest,
  unregisterNativePushTokenRequest,
} from '../api/endpoints';

const PUSH_TOKEN_KEY = 'slams-native-expo-push-token';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldPlaySound: true,
    shouldSetBadge: false,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

function deviceName() {
  const brand = Device.brand?.trim();
  const model = Device.modelName?.trim();

  if (brand && model) {
    return `${brand} ${model}`;
  }

  return model || 'SLAMS Mobile Device';
}

function projectId() {
  const extra = (Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined)?.eas
    ?.projectId;
  const easConfig = (Constants.easConfig as { projectId?: string } | undefined)?.projectId;
  const envProjectId = process.env.EXPO_PUBLIC_EAS_PROJECT_ID?.trim();

  return envProjectId || extra || easConfig || '';
}

async function ensureAndroidChannel() {
  if (Platform.OS !== 'android') {
    return;
  }

  await Notifications.setNotificationChannelAsync('default', {
    name: 'SLAMS Alerts',
    importance: Notifications.AndroidImportance.MAX,
    vibrationPattern: [0, 250, 250, 250],
    lightColor: '#0d5c8b',
  });
}

export async function readNativePushPermissionStatus() {
  const permission = await Notifications.getPermissionsAsync();
  return permission.status;
}

export async function clearStoredNativePushToken() {
  await SecureStore.deleteItemAsync(PUSH_TOKEN_KEY);
}

export async function unregisterNativePushRegistration() {
  const expoPushToken = await SecureStore.getItemAsync(PUSH_TOKEN_KEY);

  try {
    await unregisterNativePushTokenRequest(expoPushToken ? { expo_push_token: expoPushToken } : {});
  } catch (_error) {
    // Ignore transport errors during sign-out cleanup.
  }

  await SecureStore.deleteItemAsync(PUSH_TOKEN_KEY);
}

export async function syncNativePushRegistration({ prompt = false }: { prompt?: boolean } = {}) {
  await ensureAndroidChannel();

  if (!Device.isDevice) {
    return {
      enabled: false,
      message: 'Push notifications require a physical Android or iOS device.',
      permission: 'unavailable',
    };
  }

  const currentPermissions = await Notifications.getPermissionsAsync();
  let finalStatus = currentPermissions.status;

  if (finalStatus !== 'granted' && prompt) {
    finalStatus = (await Notifications.requestPermissionsAsync()).status;
  }

  if (finalStatus !== 'granted') {
    return {
      enabled: false,
      message:
        finalStatus === 'denied'
          ? 'Push notifications are blocked in the device settings.'
          : 'Push notifications are not enabled for this device yet.',
      permission: finalStatus,
    };
  }

  const resolvedProjectId = projectId();
  if (!resolvedProjectId) {
    return {
      enabled: false,
      message: 'Push notifications need an Expo project ID in the native app config.',
      permission: finalStatus,
    };
  }

  const expoPushToken = (
    await Notifications.getExpoPushTokenAsync({
      projectId: resolvedProjectId,
    })
  ).data;

  await registerNativePushTokenRequest({
    expo_push_token: expoPushToken,
    device_name: deviceName(),
    platform: Platform.OS,
  });
  await SecureStore.setItemAsync(PUSH_TOKEN_KEY, expoPushToken);

  return {
    enabled: true,
    message: 'Native push notifications are enabled for this device.',
    permission: finalStatus,
    token: expoPushToken,
  };
}
