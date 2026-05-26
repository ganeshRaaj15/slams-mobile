import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  createExternalRequestRequest,
  getExternalRequestRequest,
  listExternalRequestDaySlotsRequest,
  listExternalRequestLabServicesRequest,
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
import { formatDateLabel } from '../utils/format';
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
  service_id: number;
  selected_assets: string;
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
    service_id: 0,
    selected_assets: '',
  });
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [slotNotice, setSlotNotice] = useState<string | null>(null);

  const minimumDate = useMemo(() => {
    const next = new Date();
    next.setHours(12, 0, 0, 0);
    return next;
  }, []);

  const labsQuery = useQuery({
    queryKey: ['labs'],
    queryFn: listLabsRequest,
  });

  const existingRequestQuery = useQuery({
    queryKey: ['external-request', route.params?.requestId],
    queryFn: () => getExternalRequestRequest(route.params.requestId!),
    enabled: Boolean(route.params?.requestId),
  });

  const daySlotsQuery = useQuery({
    queryKey: ['external-request-day-slots', form.lab_id, form.preferred_date],
    queryFn: () => listExternalRequestDaySlotsRequest(form.lab_id, form.preferred_date),
    enabled: Boolean(form.lab_id && form.preferred_date),
  });

  const labServicesQuery = useQuery({
    queryKey: ['external-request-lab-services', form.lab_id],
    queryFn: () => listExternalRequestLabServicesRequest(form.lab_id),
    enabled: form.lab_id > 0,
  });

  useEffect(() => {
    if (!existingRequestQuery.data?.request) {
      return;
    }

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
      service_id: request.service_id ?? 0,
      selected_assets: request.selected_assets ?? '',
    });
    setSlotNotice(null);
  }, [existingRequestQuery.data]);

  useEffect(() => {
    const currentStart = form.preferred_start_time;
    const currentEnd = form.preferred_end_time;
    if (!currentStart || !currentEnd || !daySlotsQuery.data?.slots) {
      return;
    }

    const matchingSlot = daySlotsQuery.data.slots.find(
      (slot) => slot.start === currentStart && slot.end === currentEnd,
    );

    if (matchingSlot?.can_book) {
      return;
    }

    setForm((current) => {
      if (current.preferred_start_time !== currentStart || current.preferred_end_time !== currentEnd) {
        return current;
      }

      return {
        ...current,
        preferred_start_time: '',
        preferred_end_time: '',
      };
    });
    setSlotNotice(
      matchingSlot?.reason || 'The previously selected slot is no longer available. Please choose another slot.',
    );
  }, [daySlotsQuery.data?.slots, form.preferred_end_time, form.preferred_start_time]);

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
        service_id: form.service_id > 0 ? form.service_id : undefined,
        selected_assets: form.selected_assets || undefined,
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

  const selectedSlotSummary =
    form.preferred_date && form.preferred_start_time && form.preferred_end_time
      ? `${formatDateLabel(form.preferred_date)}  |  ${form.preferred_start_time}-${form.preferred_end_time}`
      : null;
  const latestRequesterNote =
    existingRequestQuery.data?.request.latest_requester_note || existingRequestQuery.data?.request.review_notes || '';

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
                onPress={() => {
                  setErrorMessage(null);
                  setSlotNotice(null);
                  setForm((current) => ({
                    ...current,
                    lab_id: lab.id,
                    preferred_start_time: '',
                    preferred_end_time: '',
                    service_id: 0,
                    selected_assets: '',
                  }));
                }}
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

      {form.lab_id > 0 ? (
        <View
          style={[
            styles.card,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Service / Field of Work</Text>
          <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
            Optional — select the service or field of work you are interested in.
          </Text>

          {labServicesQuery.isLoading ? (
            <Text style={[styles.slotBodyText, { color: theme.colors.textMuted }]}>Loading services...</Text>
          ) : labServicesQuery.data?.services?.length ? (
            <View style={styles.choiceWrap}>
              {labServicesQuery.data.services.map((service) => {
                const selected = service.id === form.service_id;
                return (
                  <Pressable
                    key={service.id}
                    onPress={() => {
                      const isDeselect = service.id === form.service_id;
                      setForm((current) => ({
                        ...current,
                        service_id: isDeselect ? 0 : service.id,
                        selected_assets: isDeselect ? '' : (service.equipment_models ?? ''),
                      }));
                    }}
                    style={[
                      styles.choiceChip,
                      {
                        backgroundColor: selected ? theme.colors.primarySoft : theme.colors.surfaceMuted,
                      },
                    ]}
                  >
                    <Text style={[styles.choiceText, { color: selected ? theme.colors.primary : theme.colors.text }]}>
                      {service.service_name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          ) : (
            <Text style={[styles.slotBodyText, { color: theme.colors.textMuted }]}>
              No services configured for this laboratory.
            </Text>
          )}

          {form.service_id > 0 && form.selected_assets ? (
            <View style={[styles.equipmentInfoCard, { backgroundColor: theme.colors.surfaceMuted }]}>
              <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
                <Text style={{ fontWeight: '700' }}>Equipment: </Text>
                {form.selected_assets}
              </Text>
            </View>
          ) : null}
        </View>
      ) : null}

      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <View
          style={[
            styles.infoCard,
            {
              backgroundColor: theme.colors.primarySoft,
            },
          ]}
        >
          <Text style={[styles.infoTitle, { color: theme.colors.primary }]}>Approval route</Text>
          <Text style={[styles.infoText, { color: theme.colors.text }]}>
            External requests use the same configured booking slots as student bookings. Choosing a slot here does not
            reserve it until PIC and Lab Manager approvals are complete.
          </Text>
        </View>

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
          label="Preferred Date"
          minimumDate={minimumDate}
          mode="date"
          onChangeValue={(value) => {
            setErrorMessage(null);
            setSlotNotice(null);
            setForm((current) => ({
              ...current,
              preferred_date: value,
              preferred_start_time: '',
              preferred_end_time: '',
            }));
          }}
          placeholder="Select preferred date"
          value={form.preferred_date}
        />

        {selectedSlotSummary ? (
          <View
            style={[
              styles.infoCard,
              {
                backgroundColor: theme.colors.successSoft,
              },
            ]}
          >
            <Text style={[styles.infoTitle, { color: theme.colors.success }]}>Selected slot</Text>
            <Text style={[styles.infoText, { color: theme.colors.text }]}>{selectedSlotSummary}</Text>
          </View>
        ) : null}

        <View
          style={[
            styles.slotCard,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.slotTitle, { color: theme.colors.text }]}>Available booking slots</Text>
          <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
            Choose one of the configured booking slots for this laboratory.
          </Text>

          {!form.lab_id ? (
            <Text style={[styles.slotBodyText, { color: theme.colors.textMuted }]}>Select a laboratory first.</Text>
          ) : !form.preferred_date ? (
            <Text style={[styles.slotBodyText, { color: theme.colors.textMuted }]}>
              Choose a preferred date to load the available slots.
            </Text>
          ) : daySlotsQuery.isLoading ? (
            <Text style={[styles.slotBodyText, { color: theme.colors.textMuted }]}>Loading configured slots...</Text>
          ) : daySlotsQuery.isError ? (
            <Text style={[styles.slotBodyText, { color: theme.colors.warning }]}>
              {readErrorMessage(daySlotsQuery.error, 'Could not load booking slots right now.')}
            </Text>
          ) : daySlotsQuery.data?.slots.length ? (
            <View style={styles.choiceWrap}>
              {daySlotsQuery.data.slots.map((slot) => {
                const selected =
                  form.preferred_start_time === slot.start && form.preferred_end_time === slot.end;

                return (
                  <Pressable
                    key={`${slot.label}-${slot.start}-${slot.end}`}
                    disabled={!slot.can_book}
                    onPress={() => {
                      setErrorMessage(null);
                      setSlotNotice(null);
                      setForm((current) => ({
                        ...current,
                        preferred_start_time: slot.start,
                        preferred_end_time: slot.end,
                      }));
                    }}
                    style={[
                      styles.choiceChip,
                      {
                        backgroundColor: selected
                          ? theme.colors.primary
                          : slot.can_book
                            ? theme.colors.successSoft
                            : theme.colors.surface,
                        opacity: slot.can_book ? 1 : 0.65,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.choiceText,
                        {
                          color: selected
                            ? '#ffffff'
                            : slot.can_book
                              ? theme.colors.success
                              : theme.colors.textMuted,
                        },
                      ]}
                    >
                      {slot.label || `${slot.start}-${slot.end}`}
                    </Text>
                    <Text
                      style={[
                        styles.slotMetaText,
                        {
                          color: selected ? 'rgba(255,255,255,0.88)' : theme.colors.textMuted,
                        },
                      ]}
                    >
                      {slot.can_book ? `${slot.start}-${slot.end}` : slot.reason || 'Unavailable'}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          ) : (
            <Text style={[styles.slotBodyText, { color: theme.colors.textMuted }]}>
              No configured booking slots are available for this date.
            </Text>
          )}
        </View>

        {slotNotice ? <Text style={[styles.helperNotice, { color: theme.colors.warning }]}>{slotNotice}</Text> : null}

        <TextField
          label="Purpose"
          multiline
          onChangeText={(value) => setForm((current) => ({ ...current, purpose: value }))}
          placeholder="Describe the intended lab usage"
          style={styles.multiline}
          value={form.purpose}
        />
        <TextField
          label="Setup Notes"
          multiline
          onChangeText={(value) => setForm((current) => ({ ...current, equipment_notes: value }))}
          placeholder="Optional setup or equipment notes"
          style={styles.multiline}
          value={form.equipment_notes}
        />

        {latestRequesterNote ? (
          <View
            style={[
              styles.reviewCard,
              {
                backgroundColor: theme.colors.warningSoft,
              },
            ]}
          >
            <Text style={[styles.reviewTitle, { color: theme.colors.warning }]}>Reviewer Notes</Text>
            <Text style={[styles.reviewText, { color: theme.colors.text }]}>{latestRequesterNote}</Text>
          </View>
        ) : null}

        {errorMessage ? <Text style={[styles.errorText, { color: theme.colors.danger }]}>{errorMessage}</Text> : null}

        <Pressable
          disabled={saveMutation.isPending}
          onPress={() => {
            setErrorMessage(null);
            if (!form.lab_id || !form.preferred_date || !form.preferred_start_time || !form.preferred_end_time) {
              setErrorMessage('Choose a laboratory, date, and one of the configured booking slots.');
              return;
            }
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
  infoCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  infoTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  infoText: {
    fontSize: 13,
    lineHeight: 18,
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
  equipmentInfoCard: {
    borderRadius: 10,
    padding: 10,
  },
  slotCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  slotTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  helperText: {
    fontSize: 12,
    lineHeight: 18,
  },
  slotBodyText: {
    fontSize: 13,
    lineHeight: 18,
  },
  slotMetaText: {
    fontSize: 12,
    lineHeight: 16,
    marginTop: 4,
  },
  helperNotice: {
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
