import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  getNativePushStatusRequest,
  listNotificationsRequest,
  markAllNotificationsReadRequest,
  markNotificationReadRequest,
} from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { openNotificationItem } from '../notifications/notification-target';
import {
  readNativePushPermissionStatus,
  syncNativePushRegistration,
  unregisterNativePushRegistration,
} from '../notifications/native-push';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';

export function NotificationsScreen() {
  const theme = useAppTheme();
  const queryClient = useQueryClient();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const [pushMessage, setPushMessage] = useState<string | null>(null);
  const [pushPermission, setPushPermission] = useState('undetermined');
  const [pushBusy, setPushBusy] = useState(false);

  const notificationsQuery = useQuery({
    queryKey: ['notifications'],
    queryFn: () => listNotificationsRequest(),
  });

  const nativePushQuery = useQuery({
    queryKey: ['native-push'],
    queryFn: getNativePushStatusRequest,
  });

  const markOneMutation = useMutation({
    mutationFn: markNotificationReadRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['notifications'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
    },
  });

  const markAllMutation = useMutation({
    mutationFn: markAllNotificationsReadRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['notifications'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
    },
  });

  useEffect(() => {
    void readNativePushPermissionStatus().then((status) => {
      setPushPermission(status);

      if (status === 'granted') {
        void syncNativePushRegistration({ prompt: false })
          .then((result) => {
            setPushMessage(result.message);
            void queryClient.invalidateQueries({ queryKey: ['native-push'] });
          })
          .catch(() => undefined);
      }
    });
  }, [queryClient]);

  if (notificationsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading alerts..." />
      </Screen>
    );
  }

  if (notificationsQuery.isError || !notificationsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="Notifications could not be loaded."
          onRetry={() => {
            void notificationsQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  async function enablePush() {
    setPushBusy(true);
    try {
      const result = await syncNativePushRegistration({ prompt: true });
      setPushPermission(result.permission);
      setPushMessage(result.message);
      await queryClient.invalidateQueries({ queryKey: ['native-push'] });
    } catch (error) {
      setPushMessage(readErrorMessage(error, 'Native push could not be enabled on this device.'));
    } finally {
      setPushBusy(false);
    }
  }

  async function disablePush() {
    setPushBusy(true);
    try {
      await unregisterNativePushRegistration();
      setPushMessage('Native push notifications were disabled on this device.');
      await queryClient.invalidateQueries({ queryKey: ['native-push'] });
    } finally {
      setPushBusy(false);
    }
  }

  const hasNativePush = (nativePushQuery.data?.active_tokens ?? 0) > 0;

  return (
    <Screen>
      <View
        style={[
          styles.topCard,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <View>
          <Text style={[styles.topLabel, { color: theme.colors.text }]}>Unread alerts</Text>
          <Text style={[styles.topValue, { color: theme.colors.primary }]}>
            {notificationsQuery.data.unread_count}
          </Text>
          <Text style={[styles.pushMeta, { color: theme.colors.textMuted }]}>
            Native push: {hasNativePush ? 'Enabled' : 'Not active'}
          </Text>
        </View>
        <View style={styles.topActions}>
          <Pressable
            disabled={pushBusy}
            onPress={() => {
              if (hasNativePush) {
                void disablePush();
                return;
              }

              void enablePush();
            }}
            style={[
              styles.markAllButton,
              {
                backgroundColor: hasNativePush ? theme.colors.surfaceMuted : theme.colors.primarySoft,
              },
            ]}
          >
            <Text style={[styles.markAllText, { color: hasNativePush ? theme.colors.text : theme.colors.primary }]}>
              {pushBusy ? 'Updating...' : hasNativePush ? 'Disable Push' : 'Enable Push'}
            </Text>
          </Pressable>
          <Pressable
            disabled={markAllMutation.isPending || notificationsQuery.data.unread_count === 0}
            onPress={() => {
              void markAllMutation.mutateAsync();
            }}
            style={[
              styles.markAllButton,
              {
                backgroundColor: theme.colors.primarySoft,
                opacity: notificationsQuery.data.unread_count === 0 ? 0.5 : 1,
              },
            ]}
          >
            <Text style={[styles.markAllText, { color: theme.colors.primary }]}>Mark All Read</Text>
          </Pressable>
        </View>
      </View>

      {pushMessage ? (
        <View
          style={[
            styles.pushNotice,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.pushNoticeText, { color: theme.colors.text }]}>
            {pushMessage}
          </Text>
          <Text style={[styles.pushMeta, { color: theme.colors.textMuted }]}>
            Permission: {pushPermission}
          </Text>
        </View>
      ) : null}

      {notificationsQuery.data.notifications.length === 0 ? (
        <EmptyState
          title="No alerts yet"
          message="System notifications will appear here."
        />
      ) : (
        notificationsQuery.data.notifications.map((notification) => (
          <Pressable
            key={notification.id}
            onPress={async () => {
              if (!notification.is_read) {
                await markOneMutation.mutateAsync(notification.id);
              }

              openNotificationItem(notification, role);
            }}
            style={[
              styles.card,
              {
                backgroundColor: notification.is_read
                  ? theme.colors.surface
                  : theme.colors.primarySoft,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.notificationHeader}>
              <Text style={[styles.title, { color: theme.colors.text }]}>{notification.title}</Text>
              {!notification.is_read ? (
                <View
                  style={[
                    styles.unreadDot,
                    {
                      backgroundColor: theme.colors.primary,
                    },
                  ]}
                />
              ) : null}
            </View>
            <Text style={[styles.message, { color: theme.colors.textMuted }]}>{notification.message}</Text>
            <Text style={[styles.meta, { color: theme.colors.textMuted }]}>{notification.created_at}</Text>
          </Pressable>
        ))
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  topCard: {
    alignItems: 'center',
    borderRadius: 18,
    borderWidth: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    padding: 16,
  },
  topLabel: {
    fontSize: 14,
    fontWeight: '700',
  },
  topValue: {
    fontSize: 28,
    fontWeight: '800',
  },
  markAllButton: {
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  topActions: {
    alignItems: 'flex-end',
    gap: 8,
  },
  markAllText: {
    fontSize: 13,
    fontWeight: '800',
  },
  pushNotice: {
    borderRadius: 16,
    gap: 4,
    padding: 14,
  },
  pushNoticeText: {
    fontSize: 13,
    lineHeight: 18,
  },
  pushMeta: {
    fontSize: 12,
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 8,
    padding: 16,
  },
  notificationHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'space-between',
  },
  title: {
    flex: 1,
    fontSize: 16,
    fontWeight: '800',
  },
  unreadDot: {
    borderRadius: 999,
    height: 10,
    width: 10,
  },
  message: {
    fontSize: 14,
    lineHeight: 20,
  },
  meta: {
    fontSize: 12,
  },
});
