import { useQuery } from '@tanstack/react-query';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';

import { listApprovalQueueRequest } from '../api/endpoints';
import { AnimatedListItem } from '../components/animated-list-item';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateTimeRange } from '../utils/format';

export function ApprovalsScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const canUseApprovals = role === 'pic' || role === 'manager' || role === 'admin';

  const queueQuery = useQuery({
    queryKey: ['approval-queue'],
    queryFn: listApprovalQueueRequest,
    enabled: canUseApprovals,
  });

  if (!canUseApprovals) {
    return (
      <Screen>
        <EmptyState
          title="No approval queue"
          message="Only PIC, Manager, and Admin roles can review booking approvals."
        />
      </Screen>
    );
  }

  if (queueQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading approval queue..." />
      </Screen>
    );
  }

  if (queueQuery.isError || !queueQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="Approval items could not be loaded."
          onRetry={() => {
            void queueQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const stats = queueQuery.data.stats;

  return (
    <Screen>
      <View style={styles.statsRow}>
        <View
          style={[
            styles.statBlock,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Queue</Text>
          <Text style={[styles.statValue, { color: theme.colors.primary }]}>{stats.queue_count ?? 0}</Text>
        </View>

        {typeof stats.pending_pic === 'number' ? (
          <View
            style={[
              styles.statBlock,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>PIC Stage</Text>
            <Text style={[styles.statValue, { color: theme.colors.warning }]}>{stats.pending_pic}</Text>
          </View>
        ) : null}

        {typeof stats.pending_manager === 'number' ? (
          <View
            style={[
              styles.statBlock,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Manager Stage</Text>
            <Text style={[styles.statValue, { color: theme.colors.accent }]}>{stats.pending_manager}</Text>
          </View>
        ) : null}

        <View
          style={[
            styles.statBlock,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Approved</Text>
          <Text style={[styles.statValue, { color: theme.colors.success }]}>{stats.approved ?? 0}</Text>
        </View>
      </View>

      {queueQuery.data.bookings.length === 0 ? (
        <EmptyState
          title="No pending approvals"
          message="The current approval queue is clear."
        />
      ) : (
        queueQuery.data.bookings.map((booking, index) => (
          <AnimatedListItem key={booking.id} index={index}>
          <Pressable
            onPress={() => navigation.navigate('ApprovalDetail', { bookingId: booking.id })}
            style={[
              styles.card,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.cardHeader}>
              <View style={styles.titleWrap}>
                <Text style={[styles.title, { color: theme.colors.text }]}>{booking.lab_name}</Text>
                <Text style={[styles.meta, { color: theme.colors.textMuted }]}>
                  {formatDateTimeRange(booking.date, booking.start_time, booking.end_time)}
                </Text>
              </View>
              <View
                style={[
                  styles.stagePill,
                  {
                    backgroundColor:
                      booking.stage === 'pic_review'
                        ? theme.colors.warningSoft
                        : theme.colors.primarySoft,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.stageText,
                    {
                      color:
                        booking.stage === 'pic_review'
                          ? theme.colors.warning
                          : theme.colors.primary,
                    },
                  ]}
                >
                  {booking.stage === 'pic_review' ? 'PIC Review' : 'Manager Review'}
                </Text>
              </View>
            </View>

            <Text style={[styles.bodyText, { color: theme.colors.text }]}>{booking.activity}</Text>
            <Text style={[styles.meta, { color: theme.colors.textMuted }]}>
              Faculty: {booking.faculty_name || 'Unknown'}
            </Text>

            {booking.assets.length > 0 ? (
              <View style={styles.assetWrap}>
                {booking.assets.slice(0, 3).map((asset) => (
                  <View
                    key={`${booking.id}-${asset.asset_id}`}
                    style={[
                      styles.assetPill,
                      {
                        backgroundColor: theme.colors.surfaceMuted,
                      },
                    ]}
                  >
                    <Text style={[styles.assetPillText, { color: theme.colors.text }]}>
                      {asset.name} x{asset.quantity_used}
                    </Text>
                  </View>
                ))}
              </View>
            ) : null}
          </Pressable>
          </AnimatedListItem>
        ))
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  statBlock: {
    borderRadius: 16,
    borderWidth: 1,
    gap: 6,
    minWidth: '47%',
    padding: 14,
  },
  statLabel: {
    fontSize: 13,
    fontWeight: '700',
  },
  statValue: {
    fontSize: 22,
    fontWeight: '800',
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  cardHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  titleWrap: {
    flex: 1,
    gap: 4,
    paddingRight: 10,
  },
  title: {
    fontSize: 18,
    fontWeight: '800',
  },
  meta: {
    fontSize: 13,
  },
  bodyText: {
    fontSize: 14,
    lineHeight: 20,
  },
  stagePill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  stageText: {
    fontSize: 12,
    fontWeight: '800',
  },
  assetWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  assetPill: {
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  assetPillText: {
    fontSize: 12,
    fontWeight: '700',
  },
});
