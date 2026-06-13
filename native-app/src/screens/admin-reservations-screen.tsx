import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { listAdminReservationsRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateLabel } from '../utils/format';

export function AdminReservationsScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const [query, setQuery] = useState('');
  const [labId, setLabId] = useState(0);
  const [status, setStatus] = useState('');
  const [labModalOpen, setLabModalOpen] = useState(false);
  const [statusModalOpen, setStatusModalOpen] = useState(false);

  const reservationsQuery = useQuery({
    queryKey: ['admin-reservations', query, labId, status],
    queryFn: () =>
      listAdminReservationsRequest({
        q: query.trim() || undefined,
        lab_id: labId > 0 ? labId : undefined,
        status: status || undefined,
      }),
  });

  const selectedLabLabel = useMemo(() => {
    if (!labId) {
      return 'All laboratories';
    }

    return reservationsQuery.data?.labs.find((lab) => lab.id === labId)?.label ?? 'All laboratories';
  }, [labId, reservationsQuery.data?.labs]);

  const selectedStatusLabel = status ? status.replace(/\b\w/g, (value) => value.toUpperCase()) : 'All statuses';

  if (reservationsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading full-lab reservations..." />
      </Screen>
    );
  }

  if (reservationsQuery.isError || !reservationsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The reservation workspace could not be loaded."
          onRetry={() => {
            void reservationsQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.title, { color: theme.colors.text }]}>Full-Lab Reservations</Text>
        <TextField
          label="Search"
          onChangeText={setQuery}
          placeholder="Search by title or laboratory"
          value={query}
        />

        <View style={styles.filterRow}>
          <Pressable
            onPress={() => setLabModalOpen(true)}
            style={[styles.filterButton, { backgroundColor: theme.colors.surfaceMuted }]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]} numberOfLines={1}>
              {selectedLabLabel}
            </Text>
          </Pressable>
          <Pressable
            onPress={() => setStatusModalOpen(true)}
            style={[styles.filterButton, { backgroundColor: theme.colors.surfaceMuted }]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]}>{selectedStatusLabel}</Text>
          </Pressable>
        </View>

        <Pressable
          onPress={() => navigation.navigate('AdminReservationEditor', {})}
          style={[styles.primaryButton, { backgroundColor: theme.colors.primary }]}
        >
          <Text style={styles.primaryButtonText}>Create Reservation</Text>
        </Pressable>
      </View>

      {reservationsQuery.data.reservations.length === 0 ? (
        <EmptyState title="No reservations found" message="Create a new reservation to block a full laboratory." />
      ) : (
        reservationsQuery.data.reservations.map((reservation) => (
          <Pressable
            key={reservation.id}
            onPress={() => navigation.navigate('AdminReservationEditor', { reservationId: reservation.id })}
            style={[
              styles.reservationCard,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.reservationHeader}>
              <View style={styles.reservationHeaderMeta}>
                <Text style={[styles.reservationTitle, { color: theme.colors.text }]}>{reservation.title}</Text>
                <Text style={[styles.metaText, { color: theme.colors.primary }]}>
                  {reservation.lab_name || 'No lab'} {reservation.lab_room ? `| ${reservation.lab_room}` : ''}
                </Text>
              </View>
              <View
                style={[
                  styles.pill,
                  {
                    backgroundColor: reservation.status === 'active' ? theme.colors.dangerSoft : theme.colors.surfaceMuted,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.pillText,
                    { color: reservation.status === 'active' ? theme.colors.danger : theme.colors.textMuted },
                  ]}
                >
                  {reservation.status === 'active' ? 'Blocking' : 'Cancelled'}
                </Text>
              </View>
            </View>

            <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
              {reservation.reservation_type.replace('_', ' ').replace(/\b\w/g, (value) => value.toUpperCase())}
            </Text>
            <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
              {formatDateLabel(reservation.start_at)} {reservation.start_at.slice(11, 16)} - {formatDateLabel(reservation.end_at)} {reservation.end_at.slice(11, 16)}
            </Text>
            {reservation.notes ? (
              <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>{reservation.notes}</Text>
            ) : null}
          </Pressable>
        ))
      )}

      <SelectionModal
        onClose={() => setLabModalOpen(false)}
        onSelect={(value) => setLabId(value ? Number(value) : 0)}
        options={[
          { id: '', label: 'All laboratories' },
          ...reservationsQuery.data.labs.map((lab) => ({ id: String(lab.id), label: lab.label })),
        ]}
        selectedId={labId ? String(labId) : ''}
        title="Filter by Laboratory"
        visible={labModalOpen}
      />

      <SelectionModal
        onClose={() => setStatusModalOpen(false)}
        onSelect={setStatus}
        options={[
          { id: '', label: 'All statuses' },
          { id: 'active', label: 'Active' },
          { id: 'cancelled', label: 'Cancelled' },
        ]}
        selectedId={status}
        title="Filter by Status"
        visible={statusModalOpen}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 12,
    padding: 16,
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
  },
  filterRow: {
    flexDirection: 'row',
    gap: 10,
  },
  filterButton: {
    borderRadius: 12,
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  filterText: {
    fontSize: 13,
    fontWeight: '700',
  },
  primaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 13,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '800',
  },
  reservationCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  reservationHeader: {
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
  },
  reservationHeaderMeta: {
    flex: 1,
    gap: 4,
  },
  reservationTitle: {
    fontSize: 17,
    fontWeight: '800',
  },
  metaText: {
    fontSize: 13,
    lineHeight: 18,
  },
  pill: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  pillText: {
    fontSize: 12,
    fontWeight: '800',
  },
});
