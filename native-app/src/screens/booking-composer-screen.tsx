import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  checkBookingSlotRequest,
  getLabRequest,
  listDaySlotsRequest,
  listFacultiesRequest,
  listRecommendedSlotsRequest,
  submitBookingRequest,
} from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { PickerField } from '../components/picker-field';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import type { BookingApplicantInput } from '../types/api';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateLabel } from '../utils/format';
import { readErrorMessage } from '../utils/error-message';
import type { RootStackParamList } from '../navigation/types';

type SelectedAssetState = {
  checked: boolean;
  quantity: string;
};

function emptyApplicant(emailDomainLabel?: string): BookingApplicantInput {
  return {
    name: '',
    matric_id: '',
    email: '',
    phone: '',
    faculty_id: null,
  };
}

export function BookingComposerScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'BookingComposer'>>();
  const queryClient = useQueryClient();
  const user = useAuthStore((state) => state.user);

  const [selectedServiceId, setSelectedServiceId] = useState<number | null>(null);
  const [selectedAssets, setSelectedAssets] = useState<Record<number, SelectedAssetState>>({});
  const [applicants, setApplicants] = useState<BookingApplicantInput[]>([
    {
      name: user?.full_name || user?.username || '',
      matric_id: '',
      email: user?.email || '',
      phone: user?.phone || '',
      faculty_id: user?.faculty_id ?? null,
    },
  ]);
  const [facultyModalIndex, setFacultyModalIndex] = useState<number | null>(null);
  const [selectedDate, setSelectedDate] = useState('');
  const [startTime, setStartTime] = useState('');
  const [endTime, setEndTime] = useState('');
  const [supervisorName, setSupervisorName] = useState('');
  const [supervisorEmail, setSupervisorEmail] = useState('');
  const [supervisorPhone, setSupervisorPhone] = useState('');
  const [activity, setActivity] = useState('');
  const [pickedPdf, setPickedPdf] = useState<{
    uri: string;
    name: string;
    mimeType: string;
  } | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [slotMessage, setSlotMessage] = useState<{ tone: 'success' | 'warning'; text: string } | null>(
    null,
  );

  const labQuery = useQuery({
    queryKey: ['lab', route.params.labId],
    queryFn: () => getLabRequest(route.params.labId),
  });

  const facultiesQuery = useQuery({
    queryKey: ['faculties'],
    queryFn: listFacultiesRequest,
  });

  const selectedService = useMemo(
    () => labQuery.data?.lab.services.find((service) => service.id === selectedServiceId) ?? null,
    [labQuery.data?.lab.services, selectedServiceId],
  );

  const availableServiceAssets = useMemo(() => {
    if (!labQuery.data?.lab || !selectedServiceId) {
      return [];
    }

    return labQuery.data.lab.assets.filter((asset) => {
      const status = asset.status.toLowerCase();
      return (
        asset.lab_service_id === selectedServiceId &&
        asset.quantity > 0 &&
        status !== 'maintenance' &&
        status !== 'faulty'
      );
    });
  }, [labQuery.data?.lab, selectedServiceId]);

  useEffect(() => {
    if (!selectedServiceId) {
      setSelectedAssets({});
      return;
    }

    const nextState: Record<number, SelectedAssetState> = {};
    availableServiceAssets.forEach((asset) => {
      nextState[asset.id] = {
        checked: true,
        quantity: '1',
      };
    });

    setSelectedAssets(nextState);
  }, [availableServiceAssets, selectedServiceId]);

  const assetSelectionString = useMemo(() => {
    return Object.entries(selectedAssets)
      .filter(([, asset]) => asset.checked && Number(asset.quantity || 0) > 0)
      .map(([assetId, asset]) => `${assetId}:${Math.max(Number(asset.quantity || 0), 1)}`)
      .join(',');
  }, [selectedAssets]);

  const recommendedSlotsQuery = useQuery({
    queryKey: ['recommended-slots', route.params.labId, selectedServiceId, assetSelectionString],
    queryFn: () =>
      listRecommendedSlotsRequest(route.params.labId, {
        service_id: selectedServiceId!,
        assets: assetSelectionString,
      }),
    enabled: Boolean(selectedServiceId && assetSelectionString),
  });

  const selectedDaySlotsQuery = useQuery({
    queryKey: ['day-slots', route.params.labId, selectedServiceId, assetSelectionString, selectedDate],
    queryFn: () =>
      listDaySlotsRequest(route.params.labId, selectedDate, {
        service_id: selectedServiceId!,
        assets: assetSelectionString,
      }),
    enabled: Boolean(selectedServiceId && assetSelectionString && selectedDate),
  });

  const slotCheckMutation = useMutation({
    mutationFn: checkBookingSlotRequest,
    onSuccess: (data) => {
      if (data.conflict) {
        setSlotMessage({
          tone: 'warning',
          text: data.reason || 'Selected slot is not available.',
        });
      } else {
        setSlotMessage({
          tone: 'success',
          text: 'Selected slot is available.',
        });
      }
    },
    onError: (error: unknown) => {
      setSlotMessage({
        tone: 'warning',
        text: readErrorMessage(error, 'Could not verify slot availability.'),
      });
    },
  });

  const submitMutation = useMutation({
    mutationFn: submitBookingRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['bookings'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
      navigation.navigate('Main', { screen: 'Bookings' });
    },
    onError: (error: unknown) => {
      setErrorMessage(readErrorMessage(error, 'Could not submit booking.'));
    },
  });

  if (labQuery.isLoading || facultiesQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Preparing booking composer..." />
      </Screen>
    );
  }

  if (labQuery.isError || facultiesQuery.isError || !labQuery.data || !facultiesQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The booking composer could not be loaded."
          onRetry={() => {
            void labQuery.refetch();
            void facultiesQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const { lab } = labQuery.data;
  const faculties = facultiesQuery.data.faculties;
  const selectedFaculty =
    facultyModalIndex !== null ? applicants[facultyModalIndex]?.faculty_id?.toString() ?? null : null;

  const serviceOptions = lab.services.map((service) => ({
    id: String(service.id),
    label: service.service_name,
    subtitle: [service.field_name, service.equipment_models].filter(Boolean).join('  |  '),
  }));

  async function pickPdf() {
    const result = await DocumentPicker.getDocumentAsync({
      copyToCacheDirectory: true,
      multiple: false,
      type: 'application/pdf',
    });

    if (result.canceled || !result.assets[0]) {
      return;
    }

    const asset = result.assets[0];
    setPickedPdf({
      uri: asset.uri,
      name: asset.name,
      mimeType: asset.mimeType || 'application/pdf',
    });
  }

  function updateApplicant(index: number, patch: Partial<BookingApplicantInput>) {
    setApplicants((current) =>
      current.map((applicant, currentIndex) =>
        currentIndex === index ? { ...applicant, ...patch } : applicant,
      ),
    );
  }

  function addApplicant() {
    setApplicants((current) => [...current, emptyApplicant()]);
  }

  function removeApplicant(index: number) {
    setApplicants((current) => (current.length > 1 ? current.filter((_, i) => i !== index) : current));
  }

  function setAssetChecked(assetId: number, checked: boolean) {
    setSelectedAssets((current) => ({
      ...current,
      [assetId]: {
        checked,
        quantity: current[assetId]?.quantity || '1',
      },
    }));
  }

  function setAssetQuantity(assetId: number, quantity: string) {
    const asset = availableServiceAssets.find((item) => item.id === assetId);
    const numeric = Number(quantity.replace(/[^0-9]/g, ''));
    const normalized =
      Number.isFinite(numeric) && numeric > 0
        ? String(Math.min(numeric, asset?.quantity ?? numeric))
        : '';

    setSelectedAssets((current) => ({
      ...current,
      [assetId]: {
        checked: current[assetId]?.checked ?? true,
        quantity: normalized,
      },
    }));
  }

  async function verifySlotBeforeSubmit() {
    if (!selectedServiceId || !selectedDate || !startTime || !endTime || !assetSelectionString) {
      return false;
    }

    const response = await slotCheckMutation.mutateAsync({
      lab_id: lab.id,
      service_id: selectedServiceId,
      date: selectedDate,
      start_time: startTime,
      end_time: endTime,
      asset_selection: assetSelectionString,
    });

    return !response.conflict;
  }

  async function handleSubmit() {
    setErrorMessage(null);

    if (!selectedServiceId) {
      setErrorMessage('Choose a laboratory service before submitting.');
      return;
    }
    if (!assetSelectionString) {
      setErrorMessage('At least one linked asset must remain selected for the booking.');
      return;
    }
    if (!selectedDate || !startTime || !endTime) {
      setErrorMessage('Date, start time, and end time are required.');
      return;
    }
    if (startTime >= endTime) {
      setErrorMessage('End time must be later than start time.');
      return;
    }
    if (!activity.trim()) {
      setErrorMessage('Activity description is required.');
      return;
    }
    if (!pickedPdf) {
      setErrorMessage('A supporting PDF is required.');
      return;
    }
    if (
      applicants.some(
        (applicant) =>
          !applicant.name.trim() ||
          !applicant.matric_id.trim() ||
          !applicant.email.trim() ||
          !applicant.phone.trim() ||
          !applicant.faculty_id,
      )
    ) {
      setErrorMessage('Each applicant must include name, ID, email, phone, and faculty.');
      return;
    }

    const slotOkay = await verifySlotBeforeSubmit();
    if (!slotOkay) {
      setErrorMessage('Selected slot is not available. Choose another time before submitting.');
      return;
    }

    await submitMutation.mutateAsync({
      lab_id: lab.id,
      service_id: selectedServiceId,
      date: selectedDate,
      start_time: startTime,
      end_time: endTime,
      activity: activity.trim(),
      supervisor_name: supervisorName.trim(),
      supervisor_email: supervisorEmail.trim(),
      supervisor_phone: supervisorPhone.trim(),
      asset_selection: assetSelectionString,
      applicants,
      pdf: pickedPdf,
    });
  }

  return (
    <Screen>
      <View
        style={[
          styles.hero,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.title, { color: theme.colors.text }]}>{lab.name}</Text>
        <Text style={[styles.subtitle, { color: theme.colors.primary }]}>{lab.room}</Text>
        <Text style={[styles.description, { color: theme.colors.textMuted }]}>
          Choose a service, confirm linked equipment, validate a slot, then submit the full booking package.
        </Text>
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>1. Laboratory Service</Text>
        {serviceOptions.length === 0 ? (
          <EmptyState
            title="No services available"
            message="This laboratory does not have an active service catalog yet."
          />
        ) : (
          <>
            <View style={styles.choiceWrap}>
              {serviceOptions.map((service) => {
                const selected = Number(service.id) === selectedServiceId;
                return (
                  <Pressable
                    key={service.id}
                    onPress={() => {
                      setSelectedServiceId(Number(service.id));
                      setSelectedDate('');
                      setStartTime('');
                      setEndTime('');
                      setSlotMessage(null);
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
                      {service.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            {selectedService ? (
              <View
                style={[
                  styles.infoCard,
                  {
                    backgroundColor: theme.colors.primarySoft,
                  },
                ]}
              >
                <Text style={[styles.infoTitle, { color: theme.colors.primary }]}>{selectedService.service_name}</Text>
                {selectedService.equipment_models ? (
                  <Text style={[styles.infoText, { color: theme.colors.text }]}>
                    Equipment: {selectedService.equipment_models}
                  </Text>
                ) : null}
                {selectedService.acceptance_criteria ? (
                  <Text style={[styles.infoText, { color: theme.colors.textMuted }]}>
                    Criteria: {selectedService.acceptance_criteria}
                  </Text>
                ) : null}
              </View>
            ) : null}
          </>
        )}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>2. Linked Equipment</Text>
        {!selectedServiceId ? (
          <EmptyState
            title="Select a service first"
            message="The service determines which equipment can be booked."
          />
        ) : availableServiceAssets.length === 0 ? (
          <EmptyState
            title="No bookable equipment"
            message="No available equipment is currently linked to this service."
          />
        ) : (
          availableServiceAssets.map((asset) => {
            const selected = selectedAssets[asset.id]?.checked ?? true;
            return (
              <View
                key={asset.id}
                style={[
                  styles.assetRow,
                  {
                    backgroundColor: theme.colors.surfaceMuted,
                  },
                ]}
              >
                <View style={styles.assetMeta}>
                  <Text style={[styles.assetName, { color: theme.colors.text }]}>{asset.name}</Text>
                  <Text style={[styles.assetDetail, { color: theme.colors.textMuted }]}>
                    Available: {asset.quantity} / {asset.total_quantity}
                  </Text>
                  {asset.model ? (
                    <Text style={[styles.assetDetail, { color: theme.colors.textMuted }]}>
                      Model: {asset.model}
                    </Text>
                  ) : null}
                </View>
                <View style={styles.assetControls}>
                  <Pressable
                    onPress={() => setAssetChecked(asset.id, !selected)}
                    style={[
                      styles.toggleButton,
                      {
                        backgroundColor: selected ? theme.colors.primary : theme.colors.surface,
                        borderColor: theme.colors.border,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.toggleText,
                        {
                          color: selected ? '#ffffff' : theme.colors.text,
                        },
                      ]}
                    >
                      {selected ? 'Selected' : 'Select'}
                    </Text>
                  </Pressable>
                  <TextField
                    keyboardType="number-pad"
                    label="Qty"
                    onChangeText={(value) => setAssetQuantity(asset.id, value)}
                    style={styles.qtyField}
                    value={selectedAssets[asset.id]?.quantity ?? '1'}
                  />
                </View>
              </View>
            );
          })
        )}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>3. Applicants</Text>
        {applicants.map((applicant, index) => {
          const faculty = faculties.find((item) => item.id === applicant.faculty_id);

          return (
            <View
              key={`applicant-${index}`}
              style={[
                styles.applicantCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <View style={styles.applicantHeader}>
                <Text style={[styles.applicantTitle, { color: theme.colors.text }]}>
                  Applicant {index + 1}
                </Text>
                {applicants.length > 1 ? (
                  <Pressable onPress={() => removeApplicant(index)}>
                    <Text style={[styles.removeText, { color: theme.colors.danger }]}>Remove</Text>
                  </Pressable>
                ) : null}
              </View>

              <TextField
                label="Name"
                onChangeText={(value) => updateApplicant(index, { name: value })}
                placeholder="Applicant name"
                value={applicant.name}
              />
              <TextField
                label="Matric / Staff ID"
                onChangeText={(value) => updateApplicant(index, { matric_id: value })}
                placeholder="ID number"
                value={applicant.matric_id}
              />
              <TextField
                autoCapitalize="none"
                keyboardType="email-address"
                label="Email"
                onChangeText={(value) => updateApplicant(index, { email: value })}
                placeholder="applicant@example.com"
                value={applicant.email}
              />
              <TextField
                keyboardType="phone-pad"
                label="Phone"
                onChangeText={(value) => updateApplicant(index, { phone: value })}
                placeholder="Phone number"
                value={applicant.phone}
              />
              <Pressable
                onPress={() => setFacultyModalIndex(index)}
                style={[
                  styles.selectorButton,
                  {
                    backgroundColor: theme.colors.surface,
                    borderColor: theme.colors.border,
                  },
                ]}
              >
                <Text style={[styles.selectorLabel, { color: theme.colors.text }]}>
                  {faculty ? faculty.label : 'Choose Faculty'}
                </Text>
              </Pressable>
            </View>
          );
        })}

        <Pressable
          onPress={addApplicant}
          style={[
            styles.secondaryButton,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>Add Applicant</Text>
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>4. Slot Selection</Text>
        {recommendedSlotsQuery.data?.slots?.length ? (
          <View style={styles.choiceWrap}>
            {recommendedSlotsQuery.data.slots.map((slot) => (
              <Pressable
                key={`${slot.date}-${slot.start}-${slot.end}`}
                onPress={() => {
                  setSelectedDate(slot.date);
                  setStartTime(slot.start);
                  setEndTime(slot.end);
                  setSlotMessage(null);
                }}
                style={[
                  styles.choiceChip,
                  {
                    backgroundColor: theme.colors.primarySoft,
                  },
                ]}
              >
                <Text style={[styles.choiceText, { color: theme.colors.primary }]}>
                  {formatDateLabel(slot.date)} {slot.start}-{slot.end}
                </Text>
              </Pressable>
            ))}
          </View>
        ) : null}

        <PickerField
          label="Date"
          mode="date"
          onChangeValue={setSelectedDate}
          placeholder="Select booking date"
          value={selectedDate}
        />
        <PickerField
          label="Start Time"
          mode="time"
          onChangeValue={setStartTime}
          placeholder="Select start time"
          value={startTime}
        />
        <PickerField
          label="End Time"
          mode="time"
          onChangeValue={setEndTime}
          placeholder="Select end time"
          value={endTime}
        />

        {selectedDate && selectedDaySlotsQuery.data?.slots?.length ? (
          <View
            style={[
              styles.daySlotCard,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.daySlotTitle, { color: theme.colors.text }]}>
              {formatDateLabel(selectedDate)} available slots
            </Text>
            {selectedDaySlotsQuery.data.slots.map((slot) => (
              <Text
                key={`${slot.label}-${slot.start}-${slot.end}`}
                style={[
                  styles.daySlotText,
                  {
                    color: slot.can_book ? theme.colors.success : theme.colors.textMuted,
                  },
                ]}
              >
                {slot.start}-{slot.end}  |  {slot.can_book ? 'Available' : slot.reason || 'Unavailable'}
              </Text>
            ))}
          </View>
        ) : null}

        <Pressable
          disabled={slotCheckMutation.isPending || !selectedServiceId || !assetSelectionString}
          onPress={() => {
            setErrorMessage(null);
            void slotCheckMutation.mutate({
              lab_id: lab.id,
              service_id: selectedServiceId!,
              date: selectedDate,
              start_time: startTime,
              end_time: endTime,
              asset_selection: assetSelectionString,
            });
          }}
          style={[
            styles.secondaryButton,
            {
              backgroundColor: theme.colors.surfaceMuted,
              opacity: slotCheckMutation.isPending || !selectedServiceId || !assetSelectionString ? 0.6 : 1,
            },
          ]}
        >
          <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>Check Slot Availability</Text>
        </Pressable>

        {slotMessage ? (
          <View
            style={[
              styles.statusCard,
              {
                backgroundColor:
                  slotMessage.tone === 'success' ? theme.colors.successSoft : theme.colors.warningSoft,
              },
            ]}
          >
            <Text
              style={[
                styles.statusText,
                {
                  color: slotMessage.tone === 'success' ? theme.colors.success : theme.colors.warning,
                },
              ]}
            >
              {slotMessage.text}
            </Text>
          </View>
        ) : null}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>5. Activity and PDF</Text>
        <TextField
          label="Supervisor Name"
          onChangeText={setSupervisorName}
          placeholder="Required for student workflows when applicable"
          value={supervisorName}
        />
        <TextField
          autoCapitalize="none"
          keyboardType="email-address"
          label="Supervisor Email"
          onChangeText={setSupervisorEmail}
          placeholder="supervisor@example.com"
          value={supervisorEmail}
        />
        <TextField
          keyboardType="phone-pad"
          label="Supervisor Phone"
          onChangeText={setSupervisorPhone}
          placeholder="Phone number"
          value={supervisorPhone}
        />
        <TextField
          label="Activity Description"
          multiline
          onChangeText={setActivity}
          placeholder="Describe the laboratory activity"
          style={styles.multiline}
          value={activity}
        />

        <Pressable
          onPress={() => {
            void pickPdf();
          }}
          style={[
            styles.selectorButton,
            {
              backgroundColor: theme.colors.surfaceMuted,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.selectorLabel, { color: theme.colors.text }]}>
            {pickedPdf ? pickedPdf.name : 'Choose PDF (SOP / SWP / SDS)'}
          </Text>
        </Pressable>
      </View>

      {errorMessage ? (
        <View
          style={[
            styles.statusCard,
            {
              backgroundColor: theme.colors.dangerSoft,
            },
          ]}
        >
          <Text style={[styles.statusText, { color: theme.colors.danger }]}>{errorMessage}</Text>
        </View>
      ) : null}

      <Pressable
        disabled={submitMutation.isPending}
        onPress={() => {
          void handleSubmit();
        }}
        style={[
          styles.submitButton,
          {
            backgroundColor: theme.colors.primary,
            opacity: submitMutation.isPending ? 0.7 : 1,
          },
        ]}
      >
        <Text style={styles.submitButtonText}>
          {submitMutation.isPending ? 'Submitting Booking...' : 'Submit Booking'}
        </Text>
      </Pressable>

      <SelectionModal
        onClose={() => setFacultyModalIndex(null)}
        onSelect={(id) => {
          if (facultyModalIndex === null) {
            return;
          }
          updateApplicant(facultyModalIndex, { faculty_id: Number(id) });
        }}
        options={faculties.map((faculty) => ({
          id: String(faculty.id),
          label: faculty.label,
          subtitle: faculty.is_fkmp ? 'FKMP faculty' : 'Non-FKMP faculty',
        }))}
        selectedId={selectedFaculty}
        title="Choose Faculty"
        visible={facultyModalIndex !== null}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: {
    borderRadius: 22,
    borderWidth: 1,
    gap: 8,
    padding: 20,
  },
  title: {
    fontSize: 24,
    fontWeight: '800',
  },
  subtitle: {
    fontSize: 14,
    fontWeight: '700',
  },
  description: {
    fontSize: 14,
    lineHeight: 20,
  },
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
  assetRow: {
    borderRadius: 14,
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    padding: 14,
  },
  assetMeta: {
    flex: 1,
    gap: 4,
    paddingRight: 10,
  },
  assetName: {
    fontSize: 15,
    fontWeight: '800',
  },
  assetDetail: {
    fontSize: 13,
    lineHeight: 18,
  },
  assetControls: {
    alignItems: 'flex-end',
    gap: 8,
    width: 112,
  },
  toggleButton: {
    borderRadius: 12,
    borderWidth: 1,
    paddingHorizontal: 10,
    paddingVertical: 9,
  },
  toggleText: {
    fontSize: 12,
    fontWeight: '800',
  },
  qtyField: {
    width: 92,
  },
  applicantCard: {
    borderRadius: 14,
    gap: 12,
    padding: 14,
  },
  applicantHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  applicantTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  removeText: {
    fontSize: 13,
    fontWeight: '700',
  },
  selectorButton: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  selectorLabel: {
    fontSize: 14,
    fontWeight: '600',
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
  daySlotCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  daySlotTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  daySlotText: {
    fontSize: 13,
    lineHeight: 18,
  },
  statusCard: {
    borderRadius: 14,
    padding: 14,
  },
  statusText: {
    fontSize: 13,
    fontWeight: '700',
    lineHeight: 18,
  },
  multiline: {
    minHeight: 104,
    textAlignVertical: 'top',
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
