import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  getAdminSettingsRequest,
  runAdminScheduledTasksRequest,
  updateAdminBookingSlotsRequest,
  updateAdminSettingsRequest,
} from '../api/endpoints';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';

type SlotDraft = {
  start: string;
  end: string;
};

export function AdminSettingsScreen() {
  const theme = useAppTheme();
  const queryClient = useQueryClient();
  const [settingsState, setSettingsState] = useState<Record<string, string>>({});
  const [slots, setSlots] = useState<SlotDraft[]>([]);
  const [feedback, setFeedback] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const settingsQuery = useQuery({
    queryKey: ['admin-settings'],
    queryFn: getAdminSettingsRequest,
  });

  useEffect(() => {
    if (!settingsQuery.data) {
      return;
    }

    const nextSettings: Record<string, string> = {};
    Object.entries(settingsQuery.data.settings).forEach(([key, meta]) => {
      nextSettings[key] = meta.value ?? '';
    });
    setSettingsState(nextSettings);
    setSlots(
      settingsQuery.data.booking_slots.map((slot) => ({
        start: slot.start ?? '',
        end: slot.end ?? '',
      })),
    );
  }, [settingsQuery.data]);

  const updateSettingsMutation = useMutation({
    mutationFn: updateAdminSettingsRequest,
    onSuccess: async () => {
      setErrorMessage(null);
      setFeedback('System settings updated.');
      await queryClient.invalidateQueries({ queryKey: ['admin-settings'] });
    },
    onError: (error: unknown) => {
      setFeedback(null);
      setErrorMessage(readErrorMessage(error, 'System settings could not be updated.'));
    },
  });

  const saveSlotsMutation = useMutation({
    mutationFn: updateAdminBookingSlotsRequest,
    onSuccess: async () => {
      setErrorMessage(null);
      setFeedback('Booking slots updated.');
      await queryClient.invalidateQueries({ queryKey: ['admin-settings'] });
    },
    onError: (error: unknown) => {
      setFeedback(null);
      setErrorMessage(readErrorMessage(error, 'Booking slots could not be updated.'));
    },
  });

  const scheduledTasksMutation = useMutation({
    mutationFn: runAdminScheduledTasksRequest,
    onSuccess: (data) => {
      const warning = data.errors.length > 0 ? ` Warning: ${data.errors.join(', ')}.` : '';
      setErrorMessage(null);
      setFeedback(
        `Scheduled tasks completed. Booking reminders: ${data.booking_reminders}. Maintenance reminders: ${data.maintenance_due_reminders}.${warning}`,
      );
    },
    onError: (error: unknown) => {
      setFeedback(null);
      setErrorMessage(readErrorMessage(error, 'Scheduled tasks could not be executed.'));
    },
  });

  if (settingsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading system settings..." />
      </Screen>
    );
  }

  if (settingsQuery.isError || !settingsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The system settings could not be loaded."
          onRetry={() => {
            void settingsQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  function updateSlot(index: number, field: keyof SlotDraft, value: string) {
    setSlots((current) =>
      current.map((slot, slotIndex) => (slotIndex === index ? { ...slot, [field]: value } : slot)),
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
        <Text style={[styles.title, { color: theme.colors.text }]}>Managed Settings</Text>
        {Object.entries(settingsQuery.data.settings).map(([key, meta]) => (
          <TextField
            key={key}
            autoCapitalize="none"
            keyboardType={meta.type === 'integer' ? 'number-pad' : meta.type === 'string' && key.includes('email') ? 'email-address' : 'default'}
            label={meta.label}
            hint={meta.hint ?? undefined}
            onChangeText={(value) => {
              setSettingsState((current) => ({
                ...current,
                [key]: value,
              }));
            }}
            value={settingsState[key] ?? ''}
          />
        ))}

        <Pressable
          disabled={updateSettingsMutation.isPending}
          onPress={() => {
            void updateSettingsMutation.mutateAsync(settingsState);
          }}
          style={[
            styles.primaryButton,
            {
              backgroundColor: theme.colors.primary,
              opacity: updateSettingsMutation.isPending ? 0.7 : 1,
            },
          ]}
        >
          <Text style={styles.primaryButtonText}>
            {updateSettingsMutation.isPending ? 'Saving...' : 'Save Settings'}
          </Text>
        </Pressable>
      </View>

      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.title, { color: theme.colors.text }]}>Booking Slots</Text>
        {slots.map((slot, index) => (
          <View key={`${index}-${slot.start}-${slot.end}`} style={styles.slotRow}>
            <TextField
              autoCapitalize="none"
              label={`Slot ${index + 1} Start`}
              onChangeText={(value) => {
                updateSlot(index, 'start', value);
              }}
              placeholder="08:00"
              value={slot.start}
            />
            <TextField
              autoCapitalize="none"
              label={`Slot ${index + 1} End`}
              onChangeText={(value) => {
                updateSlot(index, 'end', value);
              }}
              placeholder="10:00"
              value={slot.end}
            />
          </View>
        ))}

        <Pressable
          onPress={() => {
            setSlots((current) => [...current, { start: '', end: '' }]);
          }}
          style={[
            styles.secondaryButton,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>Add Slot</Text>
        </Pressable>

        <Pressable
          disabled={saveSlotsMutation.isPending}
          onPress={() => {
            void saveSlotsMutation.mutateAsync(slots);
          }}
          style={[
            styles.primaryButton,
            {
              backgroundColor: theme.colors.primary,
              opacity: saveSlotsMutation.isPending ? 0.7 : 1,
            },
          ]}
        >
          <Text style={styles.primaryButtonText}>
            {saveSlotsMutation.isPending ? 'Saving...' : 'Save Booking Slots'}
          </Text>
        </Pressable>
      </View>

      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.title, { color: theme.colors.text }]}>Scheduled Tasks</Text>
          <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
            Trigger booking reminders and maintenance due reminders from the admin workspace.
          </Text>
        <Pressable
          disabled={scheduledTasksMutation.isPending}
          onPress={() => {
            void scheduledTasksMutation.mutateAsync();
          }}
          style={[
            styles.secondaryButton,
            {
              backgroundColor: theme.colors.warningSoft,
            },
          ]}
        >
          <Text style={[styles.secondaryButtonText, { color: theme.colors.warning }]}>
            {scheduledTasksMutation.isPending ? 'Running...' : 'Run Scheduled Tasks'}
          </Text>
        </Pressable>
      </View>

      {feedback ? <Text style={[styles.feedback, { color: theme.colors.success }]}>{feedback}</Text> : null}
      {errorMessage ? <Text style={[styles.feedback, { color: theme.colors.danger }]}>{errorMessage}</Text> : null}
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
  slotRow: {
    gap: 12,
  },
  helperText: {
    fontSize: 13,
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
  secondaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 12,
  },
  secondaryButtonText: {
    fontSize: 14,
    fontWeight: '800',
  },
  feedback: {
    fontSize: 13,
    lineHeight: 18,
  },
});
