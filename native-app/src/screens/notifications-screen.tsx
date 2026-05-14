import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  listNotificationsRequest,
  markAllNotificationsReadRequest,
  markNotificationReadRequest,
} from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { openNotificationItem } from '../notifications/notification-target';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';

export function NotificationsScreen() {
  const theme = useAppTheme();
  const queryClient = useQueryClient();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');

  const notificationsQuery = useQuery({
    queryKey: ['notifications'],
    queryFn: () => listNotificationsRequest(),
  });

  const markOneMutation = useMutation({
    mutationFn: markNotificationReadRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  const markAllMutation = useMutation({
    mutationFn: markAllNotificationsReadRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

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
        </View>
        <View style={styles.topActions}>
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
