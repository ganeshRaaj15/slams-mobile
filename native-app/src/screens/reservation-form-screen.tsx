import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import {
  createReservationRequest,
  deleteReservationRequest,
  getReservationRequest,
  listReservationsRequest,
  updateReservationRequest,
} from '../api/endpoints';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import type { RootStackParamList } from '../navigation/types';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';

const DAY_OPTIONS = [
  { label: 'Monday', value: '0' },
  { label: 'Tuesday', value: '1' },
  { label: 'Wednesday', value: '2' },
  { label: 'Thursday', value: '3' },
  { label: 'Friday', value: '4' },
  { label: 'Saturday', value: '5' },
  { label: 'Sunday', value: '6' },
];

const TYPE_OPTIONS = [
  { label: 'Manual', value: 'manual' },
  { label: 'Class Schedule', value: 'class' },
];

const RECURRENCE_OPTIONS = [
  { label: 'One-off (single date)', value: 'none' },
  { label: 'Weekly (recurring)', value: 'weekly' },
];

function selectionOptions(options: Array<{ label: string; value: string; subtitle?: string }>) {
  return options.map((option) => ({
    id: option.value,
    label: option.label,
    subtitle: option.subtitle,
  }));
}

export function ReservationFormScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'ReservationForm'>>();
  const queryClient = useQueryClient();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const reservationId = route.params?.reservationId;
  const isEdit = typeof reservationId === 'number' && reservationId > 0;

  const [labId, setLabId] = useState<number | null>(null);
  const [type, setType] = useState('manual');
  const [title, setTitle] = useState('');
  const [recurrence, setRecurrence] = useState('none');
  const [date, setDate] = useState('');
  const [dayOfWeek, setDayOfWeek] = useState('0');
  const [startTime, setStartTime] = useState('');
  const [endTime, setEndTime] = useState('');
  const [validFrom, setValidFrom] = useState('');
  const [validUntil, setValidUntil] = useState('');
  const [notes, setNotes] = useState('');
  const [localError, setLocalError] = useState<string | null>(null);
  const [initialized, setInitialized] = useState(false);

  const [showLabPicker, setShowLabPicker] = useState(false);
  const [showTypePicker, setShowTypePicker] = useState(false);
  const [showRecurrencePicker, setShowRecurrencePicker] = useState(false);
  const [showDayPicker, setShowDayPicker] = useState(false);

  const canUse = role === 'pic' || role === 'admin';

  // Fetch existing record (edit mode) or just labs list (create mode)
  const detailQuery = useQuery({
    queryKey: ['reservation-detail', reservationId],
    queryFn: () => getReservationRequest(reservationId!),
    enabled: canUse && isEdit,
  });

  const listQuery = useQuery({
    queryKey: ['reservations', {}],
    queryFn: () => listReservationsRequest({}),
    enabled: canUse && !isEdit,
  });

  const labs = isEdit
    ? (detailQuery.data?.labs ?? [])
    : (listQuery.data?.labs ?? []);

  const labOptions = selectionOptions(labs.map((l) => ({ label: l.name, value: String(l.id) })));

  useEffect(() => {
    if (!isEdit || initialized) return;
    const r = detailQuery.data?.reservation;
    if (!r) return;
    setLabId(r.lab_id);
    setType(r.type);
    setTitle(r.title);
    setRecurrence(r.recurrence);
    setDate(r.date ?? '');
    setDayOfWeek(r.day_of_week !== null ? String(r.day_of_week) : '0');
    setStartTime(r.start_time);
    setEndTime(r.end_time);
    setValidFrom(r.valid_from ?? '');
    setValidUntil(r.valid_until ?? '');
    setNotes(r.notes ?? '');
    setInitialized(true);
  }, [detailQuery.data, isEdit, initialized]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (!labId) throw new Error('Please select a laboratory.');
      const payload = {
        lab_id: labId,
        type,
        title: title.trim(),
        recurrence,
        date: recurrence === 'none' ? date.trim() : undefined,
        day_of_week: recurrence === 'weekly' ? parseInt(dayOfWeek, 10) : undefined,
        start_time: startTime.trim(),
        end_time: endTime.trim(),
        valid_from: recurrence === 'weekly' && validFrom.trim() ? validFrom.trim() : undefined,
        valid_until: recurrence === 'weekly' && validUntil.trim() ? validUntil.trim() : undefined,
        notes: notes.trim() || undefined,
      };
      if (isEdit) {
        await updateReservationRequest(reservationId!, payload);
        return;
      }

      await createReservationRequest(payload);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['reservations'] });
      navigation.goBack();
    },
    onError: (err) => {
      setLocalError(readErrorMessage(err, 'Could not save reservation.'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteReservationRequest(reservationId!),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['reservations'] });
      navigation.goBack();
    },
    onError: (err) => {
      setLocalError(readErrorMessage(err, 'Could not delete reservation.'));
    },
  });

  function handleDelete() {
    Alert.alert(
      'Delete Reservation',
      'Are you sure you want to delete this reservation? This cannot be undone.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: () => deleteMutation.mutate(),
        },
      ],
    );
  }

  function handleSave() {
    setLocalError(null);
    if (!labId) { setLocalError('Please select a laboratory.'); return; }
    if (!title.trim()) { setLocalError('Please enter a title.'); return; }
    if (!startTime.trim() || !endTime.trim()) { setLocalError('Please enter start and end times (HH:MM).'); return; }
    if (recurrence === 'none' && !date.trim()) { setLocalError('Please enter a date (YYYY-MM-DD).'); return; }
    saveMutation.mutate();
  }

  const isLoading = isEdit ? detailQuery.isLoading : listQuery.isLoading;
  const isLoadError = isEdit ? detailQuery.isError : listQuery.isError;

  if (!canUse) {
    return (
      <Screen>
        <ErrorState message="Reservation management is only available to PIC and admin users." />
      </Screen>
    );
  }

  if (isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label={isEdit ? 'Loading reservation...' : 'Loading...'} />
      </Screen>
    );
  }

  if (isLoadError) {
    return (
      <Screen>
        <ErrorState
          message="Could not load reservation data."
          onRetry={() => { isEdit ? void detailQuery.refetch() : void listQuery.refetch(); }}
        />
      </Screen>
    );
  }

  const isBusy = saveMutation.isPending || deleteMutation.isPending;
  const selectedLab = labs.find((l) => l.id === labId);

  return (
    <Screen>
      <TextField
        label="Laboratory"
        value={selectedLab?.name ?? 'Select laboratory...'}
        editable={false}
        pointerEvents="none"
      />
      <Pressable onPress={() => setShowLabPicker(true)} style={styles.pickerButton}>
        <Text style={[styles.pickerButtonText, { color: theme.colors.primary }]}>Choose Laboratory</Text>
      </Pressable>
      <TextField
        label="Type"
        value={TYPE_OPTIONS.find((o) => o.value === type)?.label ?? type}
        editable={false}
        pointerEvents="none"
      />
      <Pressable onPress={() => setShowTypePicker(true)} style={styles.pickerButton}>
        <Text style={[styles.pickerButtonText, { color: theme.colors.primary }]}>Choose Type</Text>
      </Pressable>
      <TextField
        label="Title"
        value={title}
        onChangeText={setTitle}
        placeholder="e.g. Physics 101 Lab, Maintenance Block"
      />
      <TextField
        label="Recurrence"
        value={RECURRENCE_OPTIONS.find((o) => o.value === recurrence)?.label ?? recurrence}
        editable={false}
        pointerEvents="none"
      />
      <Pressable onPress={() => setShowRecurrencePicker(true)} style={styles.pickerButton}>
        <Text style={[styles.pickerButtonText, { color: theme.colors.primary }]}>Choose Recurrence</Text>
      </Pressable>

      {recurrence === 'none' ? (
        <TextField
          label="Date"
          value={date}
          onChangeText={setDate}
          placeholder="YYYY-MM-DD"
          autoCapitalize="none"
          keyboardType="numbers-and-punctuation"
        />
      ) : (
        <>
          <TextField
            label="Day of Week"
            value={DAY_OPTIONS.find((o) => o.value === dayOfWeek)?.label ?? dayOfWeek}
            editable={false}
            pointerEvents="none"
          />
          <Pressable onPress={() => setShowDayPicker(true)} style={styles.pickerButton}>
            <Text style={[styles.pickerButtonText, { color: theme.colors.primary }]}>Choose Day</Text>
          </Pressable>
          <View style={styles.row}>
            <View style={styles.halfField}>
              <TextField
                label="Valid From (optional)"
                value={validFrom}
                onChangeText={setValidFrom}
                placeholder="YYYY-MM-DD"
                autoCapitalize="none"
                keyboardType="numbers-and-punctuation"
              />
            </View>
            <View style={styles.halfField}>
              <TextField
                label="Valid Until (optional)"
                value={validUntil}
                onChangeText={setValidUntil}
                placeholder="YYYY-MM-DD"
                autoCapitalize="none"
                keyboardType="numbers-and-punctuation"
              />
            </View>
          </View>
        </>
      )}

      <View style={styles.row}>
        <View style={styles.halfField}>
          <TextField
            label="Start Time"
            value={startTime}
            onChangeText={setStartTime}
            placeholder="HH:MM"
            autoCapitalize="none"
            keyboardType="numbers-and-punctuation"
          />
        </View>
        <View style={styles.halfField}>
          <TextField
            label="End Time"
            value={endTime}
            onChangeText={setEndTime}
            placeholder="HH:MM"
            autoCapitalize="none"
            keyboardType="numbers-and-punctuation"
          />
        </View>
      </View>

      <TextField
        label="Notes (optional)"
        value={notes}
        onChangeText={setNotes}
        placeholder="Internal notes..."
        multiline
      />

      {localError ? (
        <View style={[styles.errorBox, { backgroundColor: theme.colors.dangerSoft, borderColor: theme.colors.danger }]}>
          <Text style={[styles.errorText, { color: theme.colors.danger }]}>{localError}</Text>
        </View>
      ) : null}

      <Pressable
        onPress={handleSave}
        disabled={isBusy}
        style={[styles.saveButton, { backgroundColor: isBusy ? theme.colors.textMuted : theme.colors.primary }]}
      >
        <Text style={styles.saveButtonText}>
          {saveMutation.isPending ? 'Saving...' : isEdit ? 'Update Reservation' : 'Create Reservation'}
        </Text>
      </Pressable>

      {isEdit ? (
        <Pressable
          onPress={handleDelete}
          disabled={isBusy}
          style={[styles.deleteButton, { borderColor: theme.colors.danger }]}
        >
          <Text style={[styles.deleteButtonText, { color: theme.colors.danger }]}>
            {deleteMutation.isPending ? 'Deleting...' : 'Delete Reservation'}
          </Text>
        </Pressable>
      ) : null}

      <SelectionModal
        visible={showLabPicker}
        title="Select Laboratory"
        options={labOptions}
        selectedId={labId !== null ? String(labId) : ''}
        onSelect={(val) => { setLabId(parseInt(val, 10)); }}
        onClose={() => setShowLabPicker(false)}
      />
      <SelectionModal
        visible={showTypePicker}
        title="Reservation Type"
        options={selectionOptions(TYPE_OPTIONS)}
        selectedId={type}
        onSelect={(val) => { setType(val); }}
        onClose={() => setShowTypePicker(false)}
      />
      <SelectionModal
        visible={showRecurrencePicker}
        title="Recurrence"
        options={selectionOptions(RECURRENCE_OPTIONS)}
        selectedId={recurrence}
        onSelect={(val) => { setRecurrence(val); }}
        onClose={() => setShowRecurrencePicker(false)}
      />
      <SelectionModal
        visible={showDayPicker}
        title="Day of Week"
        options={selectionOptions(DAY_OPTIONS)}
        selectedId={dayOfWeek}
        onSelect={(val) => { setDayOfWeek(val); }}
        onClose={() => setShowDayPicker(false)}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  halfField: {
    flex: 1,
  },
  pickerButton: {
    alignItems: 'flex-start',
    marginTop: -4,
  },
  pickerButtonText: {
    fontSize: 13,
    fontWeight: '700',
  },
  errorBox: {
    borderRadius: 12,
    borderWidth: 1,
    padding: 12,
  },
  errorText: {
    fontSize: 14,
    lineHeight: 20,
  },
  saveButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  saveButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
  deleteButton: {
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1.5,
    paddingVertical: 13,
  },
  deleteButtonText: {
    fontSize: 15,
    fontWeight: '800',
  },
});
