import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { listReservationsRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';

const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
type TypeFilter = 'all' | 'manual' | 'class';

function scheduleLabel(
  recurrence: string,
  date: string,
  dayOfWeek: number | null,
  startTime: string,
  endTime: string,
  validFrom: string,
  validUntil: string,
): string {
  const timeRange = `${startTime}–${endTime}`;
  if (recurrence === 'weekly' && dayOfWeek !== null) {
    const day = DAY_NAMES[dayOfWeek] ?? '?';
    const window = validFrom && validUntil ? `  ${validFrom} – ${validUntil}` : '';
    return `Weekly ${day}  ${timeRange}${window}`;
  }
  return `${date}  ${timeRange}`;
}

export function ReservationsScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const canUse = role === 'pic' || role === 'admin';

  const [typeFilter, setTypeFilter] = useState<TypeFilter>('all');
  const [labFilter, setLabFilter] = useState<number>(0);

  const queryParams = useMemo(() => {
    const p: { type?: string; lab_id?: number } = {};
    if (typeFilter !== 'all') p.type = typeFilter;
    if (labFilter > 0) p.lab_id = labFilter;
    return p;
  }, [typeFilter, labFilter]);

  const query = useQuery({
    queryKey: ['reservations', queryParams],
    queryFn: () => listReservationsRequest(queryParams),
    enabled: canUse,
  });

  if (!canUse) {
    return (
      <Screen>
        <EmptyState
          title="No access"
          message="Reservation management is only available to PIC and admin users."
        />
      </Screen>
    );
  }

  if (query.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading reservations..." />
      </Screen>
    );
  }

  if (query.isError || !query.data) {
    return (
      <Screen>
        <ErrorState
          message="Reservations could not be loaded."
          onRetry={() => { void query.refetch(); }}
        />
      </Screen>
    );
  }

  const { reservations, labs } = query.data;

  return (
    <Screen maxWidth="wide">
      <View style={styles.filterRow}>
        {(['all', 'manual', 'class'] as TypeFilter[]).map((f) => {
          const active = typeFilter === f;
          return (
            <Pressable
              key={f}
              onPress={() => setTypeFilter(f)}
              style={[
                styles.filterChip,
                {
                  backgroundColor: active ? theme.colors.primarySoft : theme.colors.surface,
                  borderColor: active ? theme.colors.primary : theme.colors.border,
                },
              ]}
            >
              <Text style={[styles.filterChipText, { color: active ? theme.colors.primary : theme.colors.text }]}>
                {f === 'all' ? 'All Types' : f === 'manual' ? 'Manual' : 'Class Schedule'}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {labs.length > 1 && (
        <View style={styles.filterRow}>
          <Pressable
            onPress={() => setLabFilter(0)}
            style={[
              styles.filterChip,
              {
                backgroundColor: labFilter === 0 ? theme.colors.primarySoft : theme.colors.surface,
                borderColor: labFilter === 0 ? theme.colors.primary : theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.filterChipText, { color: labFilter === 0 ? theme.colors.primary : theme.colors.text }]}>
              All Labs
            </Text>
          </Pressable>
          {labs.map((lab) => {
            const active = labFilter === lab.id;
            return (
              <Pressable
                key={lab.id}
                onPress={() => setLabFilter(lab.id)}
                style={[
                  styles.filterChip,
                  {
                    backgroundColor: active ? theme.colors.primarySoft : theme.colors.surface,
                    borderColor: active ? theme.colors.primary : theme.colors.border,
                  },
                ]}
              >
                <Text style={[styles.filterChipText, { color: active ? theme.colors.primary : theme.colors.text }]}>
                  {lab.name}
                </Text>
              </Pressable>
            );
          })}
        </View>
      )}

      <Pressable
        onPress={() => navigation.navigate('ReservationForm', {})}
        style={[styles.createButton, { backgroundColor: theme.colors.primary }]}
      >
        <Text style={styles.createButtonText}>Add Reservation</Text>
      </Pressable>

      {reservations.length === 0 ? (
        <EmptyState
          title="No reservations"
          message="No reservations match the current filter."
        />
      ) : (
        reservations.map((r) => (
          <Pressable
            key={r.id}
            onPress={() => navigation.navigate('ReservationForm', { reservationId: r.id })}
            style={[
              styles.card,
              { backgroundColor: theme.colors.surface, borderColor: theme.colors.border },
            ]}
          >
            <View style={styles.cardHeader}>
              <View style={styles.cardTitleWrap}>
                <Text style={[styles.cardTitle, { color: theme.colors.text }]}>{r.title}</Text>
                <Text style={[styles.cardMeta, { color: theme.colors.textMuted }]}>{r.lab_name}</Text>
              </View>
              <View
                style={[
                  styles.typePill,
                  {
                    backgroundColor: r.type === 'class' ? theme.colors.primarySoft : theme.colors.surfaceMuted,
                    borderColor: r.type === 'class' ? theme.colors.primary : theme.colors.border,
                  },
                ]}
              >
                <Text style={[styles.typePillText, { color: r.type === 'class' ? theme.colors.primary : theme.colors.textMuted }]}>
                  {r.type === 'class' ? 'Class' : 'Manual'}
                </Text>
              </View>
            </View>
            <Text style={[styles.scheduleText, { color: theme.colors.primary }]}>
              {scheduleLabel(r.recurrence, r.date, r.day_of_week, r.start_time, r.end_time, r.valid_from, r.valid_until)}
            </Text>
            {r.notes ? (
              <Text style={[styles.notesText, { color: theme.colors.textMuted }]} numberOfLines={1}>
                {r.notes}
              </Text>
            ) : null}
          </Pressable>
        ))
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  filterRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  filterChip: {
    borderRadius: 999,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  filterChipText: {
    fontSize: 13,
    fontWeight: '800',
  },
  createButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  createButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 8,
    padding: 16,
  },
  cardHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  cardTitleWrap: {
    flex: 1,
    gap: 3,
    paddingRight: 10,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '800',
  },
  cardMeta: {
    fontSize: 13,
  },
  typePill: {
    borderRadius: 999,
    borderWidth: 1,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  typePillText: {
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  scheduleText: {
    fontSize: 13,
    fontWeight: '700',
  },
  notesText: {
    fontSize: 13,
    lineHeight: 18,
  },
});
