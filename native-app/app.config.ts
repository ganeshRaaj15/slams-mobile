import type { ConfigContext, ExpoConfig } from 'expo/config';

const easProjectId =
  process.env.EXPO_PUBLIC_EAS_PROJECT_ID?.trim() || 'a81d8500-6210-4762-beba-3b20fe4b774a';

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'SLAMS Mobile',
  slug: 'slams-native',
  owner: 'ganeshraaj15',
  version: '1.0.0',
  icon: './assets/icon.png',
  splash: {
    image: './assets/splash-icon.png',
    resizeMode: 'contain',
    backgroundColor: '#f5f8f6',
  },
  runtimeVersion: {
    policy: 'appVersion',
  },
  android: {
    package: 'com.slams.nativeapp',
    googleServicesFile: './google-services.json',
  },
  web: {
    favicon: './assets/favicon.png',
  },
  plugins: [
    ...(config.plugins ?? []),
    'expo-notifications',
    [
      'expo-secure-store',
      {
        configureAndroidBackup: true,
        faceIDPermission: 'Allow SLAMS Mobile to use Face ID to unlock your saved session.',
      },
    ],
  ],
  extra: {
    eas: {
      projectId: easProjectId,
    },
  },
});
