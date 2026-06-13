import 'react-native-gesture-handler';

import { useEffect, useRef, useState } from 'react';
import { QueryClient, QueryClientProvider, useQueryClient } from '@tanstack/react-query';
import * as Linking from 'expo-linking';
import * as Notifications from 'expo-notifications';
import { StatusBar } from 'expo-status-bar';
import { Alert, InteractionManager, Text, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { Screen } from './src/components/screen';
import { primeResolvedApiBaseUrl } from './src/api/runtime-base-url';
import {
  markBiometricEnablePrompted,
  shouldPromptToEnableBiometrics,
} from './src/auth/biometric-session';
import { getRuntimeConfigurationIssues } from './src/config/env';
import { openNotificationResponse } from './src/notifications/notification-target';
import { navigateToStack, pushToStack } from './src/navigation/navigation-service';
import {
  clearStoredNativePushToken,
  shouldPromptForNativePushPermission,
  syncNativePushRegistration,
} from './src/notifications/native-push';
import { RootNavigator } from './src/navigation/root-navigator';
import { useAuthStore } from './src/state/auth-store';
import { useAppTheme } from './src/theme/use-app-theme';
import { readErrorMessage } from './src/utils/error-message';

const queryClient = new QueryClient();

type PendingQrBookingTarget = {
  type: 'qr-booking';
  labId: number;
  serviceId: number;
  assetId: number;
  qty: number;
};

type PendingBookingDetailTarget = {
  type: 'booking-detail';
  bookingId: number;
};

type PendingMagicLinkTarget = {
  type: 'magic-link-sign-in';
  token: string;
};

type PendingLinkTarget = PendingQrBookingTarget | PendingBookingDetailTarget | PendingMagicLinkTarget;

async function maybeOfferBiometricEnrollment(setBiometricPreference: (enabled: boolean) => Promise<void>) {
  if (!await shouldPromptToEnableBiometrics()) {
    return;
  }

  await new Promise<void>((resolve) => {
    const complete = (enable: boolean) => {
      void (async () => {
        try {
          if (enable) {
            await setBiometricPreference(true);
          }
        } catch (error: unknown) {
          Alert.alert(
            'Biometric sign-in unavailable',
            readErrorMessage(error, 'Biometric sign-in could not be enabled on this device.'),
          );
        } finally {
          await markBiometricEnablePrompted();
          resolve();
        }
      })();
    };

    Alert.alert(
      'Enable biometric sign-in?',
      'Use Face ID, fingerprint, or your device biometrics to unlock SLAMS on this device without entering your password again.',
      [
        {
          text: 'Not Now',
          style: 'cancel',
          onPress: () => {
            complete(false);
          },
        },
        {
          text: 'Enable',
          onPress: () => {
            complete(true);
          },
        },
      ],
      { cancelable: false },
    );
  });
}

async function showPushEnabledNotice() {
  await new Promise<void>((resolve) => {
    Alert.alert(
      'Push notifications enabled',
      'This device will receive SLAMS alerts while you remain signed in.',
      [
        {
          text: 'Continue',
          onPress: () => {
            resolve();
          },
        },
      ],
      { cancelable: false },
    );
  });
}

function numberFromQueryParam(value: string | string[] | undefined) {
  const raw = Array.isArray(value) ? value[0] : value;
  const parsed = Number(raw ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
}

function parseIncomingLinkUrl(url: string): PendingLinkTarget | null {
  const parsed = Linking.parse(url);
  const route = [parsed.hostname, parsed.path]
    .filter((segment): segment is string => typeof segment === 'string' && segment.trim() !== '')
    .join('/')
    .replace(/^\/+|\/+$/g, '')
    .toLowerCase();

  if (route === 'booking') {
    const labId = numberFromQueryParam(parsed.queryParams?.labId ?? parsed.queryParams?.lab_id);
    const serviceId = numberFromQueryParam(parsed.queryParams?.serviceId ?? parsed.queryParams?.service_id);
    const assetId = numberFromQueryParam(parsed.queryParams?.assetId ?? parsed.queryParams?.asset_id);
    const qty = Math.max(numberFromQueryParam(parsed.queryParams?.qty), 1);

    if (labId <= 0) {
      return null;
    }

    return {
      type: 'qr-booking',
      labId,
      serviceId,
      assetId,
      qty,
    };
  }

  if (route === 'booking-detail') {
    const bookingId = numberFromQueryParam(parsed.queryParams?.bookingId ?? parsed.queryParams?.booking_id);
    if (bookingId <= 0) {
      return null;
    }

    return {
      type: 'booking-detail',
      bookingId,
    };
  }

  if (route === 'auth/magic-link') {
    const token = String(parsed.queryParams?.token ?? '').trim();
    if (token === '') {
      return null;
    }

    return {
      type: 'magic-link-sign-in',
      token,
    };
  }

  return null;
}

function AppShell() {
  const bootstrap = useAuthStore((state) => state.bootstrap);
  const signInWithMagicLink = useAuthStore((state) => state.signInWithMagicLink);
  const user = useAuthStore((state) => state.user);
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const setBiometricPreference = useAuthStore((state) => state.setBiometricPreference);
  const status = useAuthStore((state) => state.status);
  const theme = useAppTheme();
  const queryClient = useQueryClient();
  const lastHandledNotificationId = useRef<string | null>(null);
  const lastHandledUrlRef = useRef<string | null>(null);
  const runtimeIssues = getRuntimeConfigurationIssues();
  const [navigationReady, setNavigationReady] = useState(false);
  const [pendingLinkTarget, setPendingLinkTarget] = useState<PendingLinkTarget | null>(null);

  useEffect(() => {
    let active = true;

    const queueUrl = (url: string) => {
      if (!url || lastHandledUrlRef.current === url) {
        return;
      }

      lastHandledUrlRef.current = url;
      const parsed = parseIncomingLinkUrl(url);
      if (parsed) {
        setPendingLinkTarget(parsed);
      }
    };

    void Linking.getInitialURL().then((url) => {
      if (active && url) {
        queueUrl(url);
      }
    });

    const subscription = Linking.addEventListener('url', ({ url }) => {
      queueUrl(url);
    });

    return () => {
      active = false;
      subscription.remove();
    };
  }, []);

  useEffect(() => {
    if (!pendingLinkTarget || !navigationReady) {
      return;
    }

    if (pendingLinkTarget.type === 'magic-link-sign-in') {
      if (status === 'booting') {
        return;
      }

      if (status === 'authenticated') {
        Alert.alert(
          'Already signed in',
          'Sign out first if you want to use a secure sign-in link for another account.',
        );
        setPendingLinkTarget(null);
        return;
      }

      setPendingLinkTarget(null);
      void signInWithMagicLink(pendingLinkTarget.token).catch(() => undefined);
      return;
    }

    if (status !== 'authenticated') {
      return;
    }

    if (pendingLinkTarget.type === 'booking-detail') {
      navigateToStack('BookingDetail', { bookingId: pendingLinkTarget.bookingId });
      setPendingLinkTarget(null);
      return;
    }

    if (user?.primary_role === 'external') {
      navigateToStack('RequestForm', { labId: pendingLinkTarget.labId });
      setPendingLinkTarget(null);
      return;
    }

    // push (not navigate) so each QR scan always mounts a fresh BookingComposer —
    // navigate() reuses an existing stack instance and won't re-run useState initialisers.
    pushToStack('BookingComposer', {
      labId: pendingLinkTarget.labId,
      preselectedServiceId: pendingLinkTarget.serviceId > 0 ? pendingLinkTarget.serviceId : undefined,
      preselectedAssetId: pendingLinkTarget.assetId > 0 ? pendingLinkTarget.assetId : undefined,
      preselectedAssetQty: pendingLinkTarget.qty,
      source: 'qr',
    });
    setPendingLinkTarget(null);
  }, [navigationReady, pendingLinkTarget, signInWithMagicLink, status, user?.primary_role]);

  useEffect(() => {
    if (runtimeIssues.length > 0) {
      return;
    }

    void primeResolvedApiBaseUrl().catch(() => undefined);
  }, [runtimeIssues.length]);

  useEffect(() => {
    if (runtimeIssues.length > 0) {
      return;
    }

    void bootstrap();
  }, [bootstrap, runtimeIssues.length]);

  useEffect(() => {
    if (runtimeIssues.length > 0) {
      return;
    }

    if (status === 'authenticated' && navigationReady) {
      const task = InteractionManager.runAfterInteractions(() => {
        void (async () => {
          const prompt = await shouldPromptForNativePushPermission();
          const result = await syncNativePushRegistration({ prompt });
          await queryClient.invalidateQueries({ queryKey: ['native-push'] });
          if (prompt && result.enabled) {
            await showPushEnabledNotice();
          }
          await maybeOfferBiometricEnrollment(setBiometricPreference);
        })().catch(() => undefined);
      });

      return () => {
        task.cancel();
      };
    }

    if (status === 'authenticated') {
      return;
    }

    void clearStoredNativePushToken();
  }, [navigationReady, queryClient, runtimeIssues.length, setBiometricPreference, status]);

  useEffect(() => {
    if (runtimeIssues.length > 0) {
      return;
    }

    if (status === 'unauthenticated') {
      lastHandledNotificationId.current = null;
      queryClient.clear();
    }
  }, [queryClient, runtimeIssues.length, status]);

  useEffect(() => {
    if (runtimeIssues.length > 0) {
      return;
    }

    if (status !== 'authenticated') {
      return;
    }

    const handleResponse = (response: Notifications.NotificationResponse) => {
      const identifier = response.notification.request.identifier;
      if (lastHandledNotificationId.current === identifier) {
        return;
      }

      lastHandledNotificationId.current = identifier;
      openNotificationResponse(response, role);
    };

    const notificationListener = Notifications.addNotificationReceivedListener(() => {
      void queryClient.invalidateQueries({ queryKey: ['notifications'] });
      void queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
    });

    const responseListener = Notifications.addNotificationResponseReceivedListener(handleResponse);

    void Notifications.getLastNotificationResponseAsync().then((response) => {
      if (response) {
        handleResponse(response);
      }
    });

    return () => {
      notificationListener.remove();
      responseListener.remove();
    };
  }, [queryClient, role, runtimeIssues.length, status]);

  if (runtimeIssues.length > 0) {
    return (
      <>
        <StatusBar style={theme.tone === 'dark' ? 'light' : 'dark'} />
        <Screen>
          <View
            style={{
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
              borderRadius: 18,
              borderWidth: 1,
              gap: 10,
              padding: 18,
            }}
          >
            <Text style={{ color: theme.colors.text, fontSize: 22, fontWeight: '800' }}>
              App temporarily unavailable
            </Text>
            <Text style={{ color: theme.colors.textMuted, fontSize: 14, lineHeight: 20 }}>
              This version of SLAMS Mobile cannot start right now. Update the app or contact the SLAMS administrator for help.
            </Text>
          </View>
        </Screen>
      </>
    );
  }

  return (
    <>
      <StatusBar style={theme.tone === 'dark' ? 'light' : 'dark'} />
      <RootNavigator onReady={() => setNavigationReady(true)} />
    </>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <AppShell />
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}
