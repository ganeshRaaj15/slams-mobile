import type { ConfigContext, ExpoConfig } from 'expo/config';

const easProjectId =
  process.env.EXPO_PUBLIC_EAS_PROJECT_ID?.trim() || 'a81d8500-6210-4762-beba-3b20fe4b774a';

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'SLAMS Mobile',
  slug: 'slams-native',
  owner: 'ganeshraaj15',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './assets/icon.png',
  userInterfaceStyle: 'automatic',
  newArchEnabled: true,
  scheme: 'slamsnative',
  runtimeVersion: {
    policy: 'appVersion',
  },
  splash: {
    image: './assets/splash-icon.png',
    resizeMode: 'contain',
    backgroundColor: '#ffffff',
  },
  ios: {
    supportsTablet: true,
    bundleIdentifier: 'com.slams.nativeapp',
    buildNumber: '1.0.0',
  },
  android: {
    package: 'com.slams.nativeapp',
    versionCode: 1,
    adaptiveIcon: {
      foregroundImage: './assets/adaptive-icon.png',
      backgroundColor: '#ffffff',
    },
    edgeToEdgeEnabled: true,
    predictiveBackGestureEnabled: false,
  },
  web: {
    favicon: './assets/favicon.png',
  },
  plugins: ['expo-secure-store', 'expo-notifications', '@react-native-community/datetimepicker'],
  extra: {
    eas: {
      projectId: easProjectId,
    },
  },
});
