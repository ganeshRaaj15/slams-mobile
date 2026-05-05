import 'react-native-gesture-handler';

import { useEffect, useRef } from 'react';
import { QueryClient, QueryClientProvider, useQueryClient } from '@tanstack/react-query';
import * as Notifications from 'expo-notifications';
import { StatusBar } from 'expo-status-bar';
import { Text, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { Screen } from './src/components/screen';
import { getRuntimeConfigurationIssues } from './src/config/env';
import { openNotificationResponse } from './src/notifications/notification-target';
import { clearStoredNativePushToken, syncNativePushRegistration } from './src/notifications/native-push';
import { RootNavigator } from './src/navigation/root-navigator';
import { useAuthStore } from './src/state/auth-store';
import { useAppTheme } from './src/theme/use-app-theme';

const queryClient = new QueryClient();

function AppShell() {
  const bootstrap = useAuthStore((state) => state.bootstrap);
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const status = useAuthStore((state) => state.status);
  const theme = useAppTheme();
  const queryClient = useQueryClient();
  const lastHandledNotificationId = useRef<string | null>(null);
  const runtimeIssues = getRuntimeConfigurationIssues();

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

    if (status === 'authenticated') {
      void syncNativePushRegistration({ prompt: false }).catch(() => undefined);
      return;
    }

    void clearStoredNativePushToken();
  }, [runtimeIssues.length, status]);

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
              Native configuration issue
            </Text>
            <Text style={{ color: theme.colors.textMuted, fontSize: 14, lineHeight: 20 }}>
              This build is blocked from starting because the native environment is not safe for a release deployment.
            </Text>
            {runtimeIssues.map((issue) => (
              <Text key={issue} style={{ color: theme.colors.danger, fontSize: 14, lineHeight: 20 }}>
                - {issue}
              </Text>
            ))}
          </View>
        </Screen>
      </>
    );
  }

  return (
    <>
      <StatusBar style={theme.tone === 'dark' ? 'light' : 'dark'} />
      <RootNavigator />
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
