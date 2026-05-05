import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  createExternalRequestRequest,
  getExternalRequestRequest,
  listLabsRequest,
  updateExternalRequestRequest,
} from '../api/endpoints';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { PickerField } from '../components/picker-field';
import { Screen } from '../components/screen';
import { TextField } from '../components/text-field';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';
import type { RootStackParamList } from '../navigation/types';

type FormState = {
  lab_id: number;
  organization_name: string;
  contact_name: string;
  contact_email: string;
  contact_phone: string;
  participant_count: string;
  preferred_date: string;
  preferred_start_time: string;
  preferred_end_time: string;
  purpose: string;
  equipment_notes: string;
};

export function RequestFormScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const queryClient = useQueryClient();
  const route = useRoute<RouteProp<RootStackParamList, 'RequestForm'>>();
  const user = useAuthStore((state) => state.user);

  const [form, setForm] = useState<FormState>({
    lab_id: route.params?.labId ?? 0,
    organization_name: '',
    contact_name: user?.full_name || user?.username || '',
    contact_email: user?.email || '',
    contact_phone: user?.phone || '',
    participant_count: '1',
    preferred_date: '',
    preferred_start_time: '',
    preferred_end_time: '',
    purpose: '',
    equipment_notes: '',
  });
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const labsQuery = useQuery({
    queryKey: ['labs'],
    queryFn: listLabsRequest,
  });

  const existingRequestQuery = useQuery({
    queryKey: ['external-request', route.params?.requestId],
    queryFn: () => getExternalRequestRequest(route.params.requestId!),
    enabled: Boolean(route.params?.requestId),
  });

  useEffect(() => {
    if (existingRequestQuery.data?.request) {
      const request = existingRequestQuery.data.request;
      setForm({
        lab_id: request.lab_id,
        organization_name: request.organization_name,
        contact_name: request.contact_name,
        contact_email: request.contact_email,
        contact_phone: request.contact_phone,
        participant_count: String(request.participant_count || 1),
        preferred_date: request.preferred_date,
        preferred_start_time: request.preferred_start_time,
        preferred_end_time: request.preferred_end_time,
        purpose: request.purpose,
        equipment_notes: request.equipment_notes,
      });
    }
  }, [existingRequestQuery.data]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        lab_id: form.lab_id,
        organization_name: form.organization_name,
        contact_name: form.contact_name,
        contact_email: form.contact_email,
        contact_phone: form.contact_phone,
        participant_count: Number(form.participant_count || 0),
        preferred_date: form.preferred_date,
        preferred_start_time: form.preferred_start_time,
        preferred_end_time: form.preferred_end_time,
        purpose: form.purpose,
        equipment_notes: form.equipment_notes,
      };

      if (route.params?.requestId) {
        return updateExternalRequestRequest(route.params.requestId, payload);
      }

      return createExternalRequestRequest(payload);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['external-requests'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setErrorMessage(readErrorMessage(error, 'Could not save the external request.'));
    },
  });

  if (labsQuery.isLoading || existingRequestQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Preparing form..." />
      </Screen>
    );
  }

  if (labsQuery.isError || (route.params?.requestId && existingRequestQuery.isError)) {
    return (
      <Screen>
        <ErrorState
          message="The request form could not be prepared."
          onRetry={() => {
            void labsQuery.refetch();
            void existingRequestQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const labs = labsQuery.data?.labs ?? [];

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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Select Laboratory</Text>
        <View style={styles.choiceWrap}>
          {labs.map((lab) => {
            const selected = lab.id === form.lab_id;
            return (
              <Pressable
                key={lab.id}
                onPress={() => setForm((current) => ({ ...current, lab_id: lab.id }))}
                style={[
                  styles.choiceChip,
                  {
                    backgroundColor: selected ? theme.colors.primarySoft : theme.colors.surfaceMuted,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.choiceText,
                    {
                      color: selected ? theme.colors.primary : theme.colors.text,
                    },
                  ]}
                >
                  {lab.name}
                </Text>
              </Pressable>
            );
          })}
        </View>
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
        <TextField
          label="Organization"
          onChangeText={(value) => setForm((current) => ({ ...current, organization_name: value }))}
          placeholder="Organization name"
          value={form.organization_name}
        />
        <TextField
          label="Contact Name"
          onChangeText={(value) => setForm((current) => ({ ...current, contact_name: value }))}
          placeholder="Primary contact"
          value={form.contact_name}
        />
        <TextField
          autoCapitalize="none"
          keyboardType="email-address"
          label="Contact Email"
          onChangeText={(value) => setForm((current) => ({ ...current, contact_email: value }))}
          placeholder="contact@example.com"
          value={form.contact_email}
        />
        <TextField
          keyboardType="phone-pad"
          label="Contact Phone"
          onChangeText={(value) => setForm((current) => ({ ...current, contact_phone: value }))}
          placeholder="Phone number"
          value={form.contact_phone}
        />
        <TextField
          keyboardType="number-pad"
          label="Participant Count"
          onChangeText={(value) => setForm((current) => ({ ...current, participant_count: value }))}
          placeholder="1"
          value={form.participant_count}
        />
        <PickerField
          allowClear
          label="Preferred Date"
          mode="date"
          onChangeValue={(value) => setForm((current) => ({ ...current, preferred_date: value }))}
          placeholder="Select preferred date"
          value={form.preferred_date}
        />
        <PickerField
          allowClear
          hint="Leave blank if no preferred start time is needed."
          label="Preferred Start Time"
          mode="time"
          onChangeValue={(value) => setForm((current) => ({ ...current, preferred_start_time: value }))}
          placeholder="Select start time"
          value={form.preferred_start_time}
        />
        <PickerField
          allowClear
          hint="Leave blank if no preferred end time is needed."
          label="Preferred End Time"
          mode="time"
          onChangeValue={(value) => setForm((current) => ({ ...current, preferred_end_time: value }))}
          placeholder="Select end time"
          value={form.preferred_end_time}
        />
        <TextField
          label="Purpose"
          multiline
          onChangeText={(value) => setForm((current) => ({ ...current, purpose: value }))}
          placeholder="Describe the intended lab usage"
          style={styles.multiline}
          value={form.purpose}
        />
        <TextField
          label="Equipment Notes"
          multiline
          onChangeText={(value) => setForm((current) => ({ ...current, equipment_notes: value }))}
          placeholder="Optional equipment requirements"
          style={styles.multiline}
          value={form.equipment_notes}
        />

        {existingRequestQuery.data?.request.review_notes ? (
          <View
            style={[
              styles.reviewCard,
              {
                backgroundColor: theme.colors.warningSoft,
              },
            ]}
          >
            <Text style={[styles.reviewTitle, { color: theme.colors.warning }]}>Reviewer Notes</Text>
            <Text style={[styles.reviewText, { color: theme.colors.text }]}>
              {existingRequestQuery.data.request.review_notes}
            </Text>
          </View>
        ) : null}

        {errorMessage ? <Text style={[styles.errorText, { color: theme.colors.danger }]}>{errorMessage}</Text> : null}

        <Pressable
          disabled={saveMutation.isPending}
          onPress={() => {
            setErrorMessage(null);
            void saveMutation.mutateAsync();
          }}
          style={[
            styles.submitButton,
            {
              backgroundColor: theme.colors.primary,
              opacity: saveMutation.isPending ? 0.7 : 1,
            },
          ]}
        >
          <Text style={styles.submitButtonText}>
            {route.params?.requestId ? 'Update Request' : 'Submit Request'}
          </Text>
        </Pressable>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 14,
    padding: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  choiceWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  choiceChip: {
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  choiceText: {
    fontSize: 13,
    fontWeight: '700',
  },
  multiline: {
    minHeight: 104,
    textAlignVertical: 'top',
  },
  reviewCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  reviewTitle: {
    fontSize: 13,
    fontWeight: '800',
  },
  reviewText: {
    fontSize: 14,
    lineHeight: 20,
  },
  errorText: {
    fontSize: 13,
    fontWeight: '700',
  },
  submitButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  submitButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
});
