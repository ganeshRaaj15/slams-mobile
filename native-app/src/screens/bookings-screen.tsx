import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';

import { cancelBookingRequest, listBookingsRequest } from '../api/endpoints';
import { AnimatedListItem } from '../components/animated-list-item';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatusPill } from '../components/status-pill';
import { isStudentRole } from '../constants/roles';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateTimeRange } from '../utils/format';

export function BookingsScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const queryClient = useQueryClient();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');

  const bookingsQuery = useQuery({
    queryKey: ['bookings'],
    queryFn: () => listBookingsRequest(),
    enabled: isStudentRole(role),
  });

  const cancelMutation = useMutation({
    mutationFn: cancelBookingRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['bookings'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
    },
  });

  if (!isStudentRole(role)) {
    return (
      <Screen>
        <EmptyState
          title="No booking workspace"
          message="This tab is reserved for users who submit and track their own lab bookings."
        />
      </Screen>
    );
  }

  if (bookingsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading bookings..." />
      </Screen>
    );
  }

  if (bookingsQuery.isError || !bookingsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="Bookings could not be loaded."
          onRetry={() => {
            void bookingsQuery.refetch();
          }}
        />
      </Screen>
    );
  }

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
          <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Total</Text>
          <Text style={[styles.statValue, { color: theme.colors.text }]}>{bookingsQuery.data.stats.total}</Text>
        </View>
        <View
          style={[
            styles.statBlock,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Pending</Text>
          <Text style={[styles.statValue, { color: theme.colors.warning }]}>{bookingsQuery.data.stats.pending}</Text>
        </View>
      </View>

      {bookingsQuery.data.bookings.length === 0 ? (
        <EmptyState
          title="No bookings yet"
          message="Bookings you submit in SLAMS will appear here."
        />
      ) : (
        bookingsQuery.data.bookings.map((booking, index) => (
          <AnimatedListItem key={booking.id} index={index}>
          <Pressable
            onPress={() => navigation.navigate('BookingDetail', { bookingId: booking.id })}
            style={[
              styles.card,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.cardHeader}>
              <View style={styles.cardTitleWrap}>
                <Text style={[styles.cardTitle, { color: theme.colors.text }]}>{booking.lab_name}</Text>
                <Text style={[styles.cardSubtitle, { color: theme.colors.textMuted }]}>
                  {booking.service_name || 'General booking'}
                </Text>
              </View>
              <StatusPill status={booking.status} />
            </View>

            <Text style={[styles.cardMeta, { color: theme.colors.primary }]}>
              {formatDateTimeRange(booking.date, booking.start_time, booking.end_time)}
            </Text>
            <Text style={[styles.cardText, { color: theme.colors.textMuted }]}>{booking.activity}</Text>

            {booking.can_cancel ? (
              <Pressable
                disabled={cancelMutation.isPending}
                onPress={() => {
                  void cancelMutation.mutateAsync(booking.id);
                }}
                style={[
                  styles.cancelButton,
                  {
                    backgroundColor: theme.colors.dangerSoft,
                  },
                ]}
              >
                <Text style={[styles.cancelButtonText, { color: theme.colors.danger }]}>Cancel Pending Booking</Text>
              </Pressable>
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
    gap: 12,
  },
  statBlock: {
    borderRadius: 16,
    borderWidth: 1,
    flex: 1,
    gap: 6,
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
  cardTitleWrap: {
    flex: 1,
    gap: 4,
    paddingRight: 12,
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  cardSubtitle: {
    fontSize: 13,
  },
  cardMeta: {
    fontSize: 13,
    fontWeight: '700',
  },
  cardText: {
    fontSize: 14,
    lineHeight: 20,
  },
  cancelButton: {
    alignItems: 'center',
    borderRadius: 12,
    marginTop: 4,
    paddingVertical: 10,
  },
  cancelButtonText: {
    fontSize: 13,
    fontWeight: '800',
  },
});
