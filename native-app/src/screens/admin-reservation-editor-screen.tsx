import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import {
  createAdminReservationRequest,
  deleteAdminReservationRequest,
  getAdminReservationRequest,
  listAdminLabsRequest,
  updateAdminReservationRequest,
} from '../api/endpoints';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { PickerField } from '../components/picker-field';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import type { RootStackParamList } from '../navigation/types';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';

export function AdminReservationEditorScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'AdminReservationEditor'>>();
  const queryClient = useQueryClient();
  const reservationId = route.params?.reservationId ?? null;
  const isEditMode = reservationId !== null;

  const [labId, setLabId] = useState<number | null>(null);
  const [title, setTitle] = useState('');
  const [reservationType, setReservationType] = useState('reservation');
  const [startDate, setStartDate] = useState('');
  const [startTime, setStartTime] = useState('');
  const [endDate, setEndDate] = useState('');
  const [endTime, setEndTime] = useState('');
  const [notes, setNotes] = useState('');
  const [status, setStatus] = useState('active');
  const [labModalOpen, setLabModalOpen] = useState(false);
  const [typeModalOpen, setTypeModalOpen] = useState(false);
  const [statusModalOpen, setStatusModalOpen] = useState(false);
  const [initialized, setInitialized] = useState(false);
  const [localMessage, setLocalMessage] = useState<string | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  const detailQuery = useQuery({
    queryKey: ['admin-reservation', reservationId],
    queryFn: () => getAdminReservationRequest(reservationId as number),
    enabled: isEditMode,
  });

  const labsQuery = useQuery({
    queryKey: ['admin-labs', 'reservation-editor'],
    queryFn: () => listAdminLabsRequest(),
  });

  useEffect(() => {
    if (initialized) {
      return;
    }

    if (isEditMode && detailQuery.data?.reservation) {
      const reservation = detailQuery.data.reservation;
      setLabId(reservation.lab_id);
      setTitle(reservation.title ?? '');
      setReservationType(reservation.reservation_type ?? 'reservation');
      setStartDate(reservation.start_at.slice(0, 10));
      setStartTime(reservation.start_at.slice(11, 16));
      setEndDate(reservation.end_at.slice(0, 10));
      setEndTime(reservation.end_at.slice(11, 16));
      setNotes(reservation.notes ?? '');
      setStatus(reservation.status ?? 'active');
      setInitialized(true);
      return;
    }

    if (!isEditMode && labsQuery.data?.labs?.length) {
      setLabId(labsQuery.data.labs[0].id);
      setInitialized(true);
    }
  }, [detailQuery.data?.reservation, initialized, isEditMode, labsQuery.data?.labs]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        lab_id: labId ?? 0,
        title: title.trim(),
        reservation_type: reservationType,
        start_at: `${startDate} ${startTime}:00`,
        end_at: `${endDate} ${endTime}:00`,
        notes: notes.trim(),
        status,
      };

      if (isEditMode) {
        return updateAdminReservationRequest(reservationId as number, payload);
      }

      return createAdminReservationRequest(payload);
    },
    onSuccess: async () => {
      setLocalError(null);
      setLocalMessage(isEditMode ? 'Reservation updated successfully.' : 'Reservation created successfully.');
      await queryClient.invalidateQueries({ queryKey: ['admin-reservations'] });
      if (isEditMode) {
        await queryClient.invalidateQueries({ queryKey: ['admin-reservation', reservationId] });
        return;
      }
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, isEditMode ? 'Reservation update failed.' : 'Reservation save failed.'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteAdminReservationRequest(reservationId as number),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-reservations'] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, 'Reservation deletion failed.'));
    },
  });

  const labs = detailQuery.data?.labs ?? labsQuery.data?.labs ?? [];
  const selectedLab = useMemo(() => labs.find((item) => item.id === labId) ?? null, [labId, labs]);
  const selectedLabLabel = selectedLab
    ? ('label' in selectedLab ? selectedLab.label : `${selectedLab.name} - ${selectedLab.room}`)
    : 'Select laboratory';

  if ((isEditMode && detailQuery.isLoading) || labsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label={isEditMode ? 'Loading reservation...' : 'Loading reservation editor...'} />
      </Screen>
    );
  }

  if ((isEditMode && (detailQuery.isError || !detailQuery.data)) || labsQuery.isError || !labsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The reservation editor could not be loaded."
          onRetry={() => {
            if (isEditMode) {
              void detailQuery.refetch();
            }
            void labsQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  function confirmDelete() {
    Alert.alert('Delete reservation', 'This removes the full-lab reservation block.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () => {
          void deleteMutation.mutateAsync();
        },
      },
    ]);
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
        <Text style={[styles.title, { color: theme.colors.text }]}>
          {isEditMode ? 'Edit Full-Lab Reservation' : 'Create Full-Lab Reservation'}
        </Text>

        <View style={styles.fieldWrap}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Laboratory</Text>
          <Pressable
            onPress={() => setLabModalOpen(true)}
            style={[
              styles.selector,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.selectorText, { color: theme.colors.text }]}>
              {selectedLabLabel}
            </Text>
          </Pressable>
        </View>

        <TextField label="Title" onChangeText={setTitle} value={title} />

        <View style={styles.fieldWrap}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Reservation Type</Text>
          <Pressable
            onPress={() => setTypeModalOpen(true)}
            style={[
              styles.selector,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.selectorText, { color: theme.colors.text }]}>
              {reservationType.replace('_', ' ').replace(/\b\w/g, (value) => value.toUpperCase())}
            </Text>
          </Pressable>
        </View>

        <View style={styles.row}>
          <View style={styles.rowItem}>
            <PickerField label="Start Date" mode="date" onChangeValue={setStartDate} placeholder="Start date" value={startDate} />
          </View>
          <View style={styles.rowItem}>
            <PickerField label="Start Time" mode="time" onChangeValue={setStartTime} placeholder="Start time" value={startTime} />
          </View>
        </View>

        <View style={styles.row}>
          <View style={styles.rowItem}>
            <PickerField label="End Date" mode="date" onChangeValue={setEndDate} placeholder="End date" value={endDate} />
          </View>
          <View style={styles.rowItem}>
            <PickerField label="End Time" mode="time" onChangeValue={setEndTime} placeholder="End time" value={endTime} />
          </View>
        </View>

        <View style={styles.fieldWrap}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Status</Text>
          <Pressable
            onPress={() => setStatusModalOpen(true)}
            style={[
              styles.selector,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.selectorText, { color: theme.colors.text }]}>
              {status.replace(/\b\w/g, (value) => value.toUpperCase())}
            </Text>
          </Pressable>
        </View>

        <TextField
          label="Notes"
          multiline
          onChangeText={setNotes}
          style={styles.multiline}
          value={notes}
        />
      </View>

      {localMessage ? (
        <View style={[styles.feedbackCard, { backgroundColor: theme.colors.successSoft }]}>
          <Text style={[styles.feedbackText, { color: theme.colors.success }]}>{localMessage}</Text>
        </View>
      ) : null}
      {localError ? (
        <View style={[styles.feedbackCard, { backgroundColor: theme.colors.dangerSoft }]}>
          <Text style={[styles.feedbackText, { color: theme.colors.danger }]}>{localError}</Text>
        </View>
      ) : null}

      <Pressable
        disabled={saveMutation.isPending}
        onPress={() => {
          void saveMutation.mutateAsync();
        }}
        style={[
          styles.primaryButton,
          {
            backgroundColor: theme.colors.primary,
            opacity: saveMutation.isPending ? 0.7 : 1,
          },
        ]}
      >
        <Text style={styles.primaryButtonText}>
          {saveMutation.isPending ? 'Saving...' : isEditMode ? 'Save Reservation' : 'Create Reservation'}
        </Text>
      </Pressable>

      {isEditMode ? (
        <View
          style={[
            styles.card,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Delete Reservation</Text>
          <Pressable
            disabled={deleteMutation.isPending}
            onPress={confirmDelete}
            style={[
              styles.dangerButton,
              {
                backgroundColor: theme.colors.dangerSoft,
                opacity: deleteMutation.isPending ? 0.7 : 1,
              },
            ]}
          >
            <Text style={[styles.dangerButtonText, { color: theme.colors.danger }]}>
              {deleteMutation.isPending ? 'Deleting...' : 'Delete Reservation'}
            </Text>
          </Pressable>
        </View>
      ) : null}

      <SelectionModal
        onClose={() => setLabModalOpen(false)}
        onSelect={(value) => setLabId(value ? Number(value) : null)}
        options={labs.map((lab) => ({
          id: String(lab.id),
          label: 'label' in lab ? lab.label : `${lab.name} - ${lab.room}`,
        }))}
        selectedId={labId ? String(labId) : null}
        title="Select Laboratory"
        visible={labModalOpen}
      />

      <SelectionModal
        onClose={() => setTypeModalOpen(false)}
        onSelect={(value) => setReservationType(value || 'reservation')}
        options={[
          { id: 'reservation', label: 'Reservation' },
          { id: 'walk_in', label: 'Walk In' },
          { id: 'class', label: 'Class' },
          { id: 'event', label: 'Event' },
          { id: 'maintenance', label: 'Maintenance' },
        ]}
        selectedId={reservationType}
        title="Reservation Type"
        visible={typeModalOpen}
      />

      <SelectionModal
        onClose={() => setStatusModalOpen(false)}
        onSelect={(value) => setStatus(value || 'active')}
        options={[
          { id: 'active', label: 'Active' },
          { id: 'cancelled', label: 'Cancelled' },
        ]}
        selectedId={status}
        title="Reservation Status"
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
  sectionTitle: {
    fontSize: 17,
    fontWeight: '800',
  },
  fieldWrap: {
    gap: 6,
  },
  fieldLabel: {
    fontSize: 14,
    fontWeight: '700',
  },
  selector: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  selectorText: {
    fontSize: 15,
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  rowItem: {
    flex: 1,
  },
  multiline: {
    minHeight: 104,
    textAlignVertical: 'top',
  },
  feedbackCard: {
    borderRadius: 16,
    padding: 14,
  },
  feedbackText: {
    fontSize: 13,
    fontWeight: '700',
    lineHeight: 18,
  },
  primaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '800',
  },
  dangerButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  dangerButtonText: {
    fontSize: 14,
    fontWeight: '800',
  },
});
