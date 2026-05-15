import { useEffect, useMemo, useRef, useState } from 'react';
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
import { isStudentRole } from '../constants/roles';
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

const DEVICE_CLOCK_REFRESH_MS = 30000;

function emptyApplicant(emailDomainLabel?: string): BookingApplicantInput {
  return {
    name: '',
    matric_id: '',
    email: '',
    phone: '',
    faculty_id: null,
  };
}

function readSlotErrorMessage(error: unknown) {
  const message = readErrorMessage(error, 'Could not verify slot availability.');

  if (/network error|network request failed|failed to fetch/i.test(message)) {
    return 'Could not reach the backend to verify slot availability right now. You can keep filling the form, and the server will still run a final availability check when you submit.';
  }

  return message;
}

function twoDigits(value: number) {
  return value.toString().padStart(2, '0');
}

function localDayStart(date: Date) {
  const next = new Date(date);
  next.setHours(0, 0, 0, 0);
  return next;
}

function parseLocalDate(dateValue: string) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateValue.trim());
  if (!match) {
    return null;
  }

  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 0, 0, 0, 0);
}

function parseLocalDateTime(dateValue: string, timeValue: string) {
  const dateMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateValue.trim());
  const timeMatch = /^(\d{2}):(\d{2})$/.exec(timeValue.trim());
  if (!dateMatch || !timeMatch) {
    return null;
  }

  return new Date(
    Number(dateMatch[1]),
    Number(dateMatch[2]) - 1,
    Number(dateMatch[3]),
    Number(timeMatch[1]),
    Number(timeMatch[2]),
    0,
    0,
  );
}

function isPastBookingDate(dateValue: string, now: Date) {
  const date = parseLocalDate(dateValue);
  return date ? date < localDayStart(now) : false;
}

function isPastBookingSlot(dateValue: string, endTimeValue: string, now: Date) {
  if (isPastBookingDate(dateValue, now)) {
    return true;
  }

  const endAt = parseLocalDateTime(dateValue, endTimeValue);
  return endAt ? endAt <= now : false;
}

export function BookingComposerScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'BookingComposer'>>();
  const queryClient = useQueryClient();
  const user = useAuthStore((state) => state.user);
  const qrPrefillAppliedRef = useRef(false);
  const preselectedServiceId = route.params?.preselectedServiceId ?? null;
  const preselectedAssetId = route.params?.preselectedAssetId ?? null;
  const preselectedAssetQty = Math.max(route.params?.preselectedAssetQty ?? 1, 1);

  const [selectedServiceId, setSelectedServiceId] = useState<number | null>(preselectedServiceId);
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
  const [deviceNow, setDeviceNow] = useState(() => new Date());
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

  const deviceToday = useMemo(() => {
    const next = new Date(deviceNow);
    next.setHours(12, 0, 0, 0);
    return next;
  }, [deviceNow]);

  useEffect(() => {
    const timer = setInterval(() => {
      setDeviceNow(new Date());
    }, DEVICE_CLOCK_REFRESH_MS);

    return () => {
      clearInterval(timer);
    };
  }, []);

  useEffect(() => {
    if (!labQuery.data?.lab || !preselectedServiceId) {
      return;
    }

    const hasMatchingService = labQuery.data.lab.services.some((service) => service.id === preselectedServiceId);
    if (!hasMatchingService) {
      setErrorMessage('The service linked to this QR code is no longer available. Choose another service.');
    }
  }, [labQuery.data?.lab, preselectedServiceId]);

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

  useEffect(() => {
    qrPrefillAppliedRef.current = false;
  }, [preselectedAssetId, preselectedAssetQty, preselectedServiceId, route.params.labId]);

  useEffect(() => {
    if (!labQuery.data?.lab || !preselectedAssetId || !selectedServiceId || qrPrefillAppliedRef.current) {
      return;
    }

    if (preselectedServiceId && selectedServiceId !== preselectedServiceId) {
      return;
    }

    const selectedAsset = labQuery.data.lab.assets.find((asset) => asset.id === preselectedAssetId);
    if (!selectedAsset) {
      setErrorMessage('The equipment linked to this QR code could not be found in this laboratory.');
      qrPrefillAppliedRef.current = true;
      return;
    }

    if ((selectedAsset.lab_service_id ?? 0) !== selectedServiceId) {
      setErrorMessage('The equipment linked to this QR code is no longer attached to the selected service.');
      qrPrefillAppliedRef.current = true;
      return;
    }

    const status = selectedAsset.status.toLowerCase();
    if (selectedAsset.quantity <= 0 || status === 'maintenance' || status === 'faulty') {
      setErrorMessage('The equipment linked to this QR code is not currently available for booking.');
      qrPrefillAppliedRef.current = true;
      return;
    }

    setSelectedAssets((current) => ({
      ...current,
      [selectedAsset.id]: {
        checked: true,
        quantity: String(Math.min(preselectedAssetQty, selectedAsset.quantity)),
      },
    }));

    qrPrefillAppliedRef.current = true;
  }, [
    labQuery.data?.lab,
    preselectedAssetId,
    preselectedAssetQty,
    preselectedServiceId,
    selectedServiceId,
  ]);

  const assetSelectionString = useMemo(() => {
    return Object.entries(selectedAssets)
      .filter(([, asset]) => asset.checked && Number(asset.quantity || 0) > 0)
      .map(([assetId, asset]) => `${assetId}:${Math.max(Number(asset.quantity || 0), 1)}`)
      .join(',');
  }, [selectedAssets]);

  const hasServiceAssets = availableServiceAssets.length > 0;

  const recommendedSlotsQuery = useQuery({
    queryKey: ['recommended-slots', route.params.labId, selectedServiceId, assetSelectionString],
    queryFn: () =>
      listRecommendedSlotsRequest(route.params.labId, {
        service_id: selectedServiceId!,
        assets: assetSelectionString,
      }),
    enabled: Boolean(selectedServiceId && (assetSelectionString || !hasServiceAssets)),
  });

  const selectedDaySlotsQuery = useQuery({
    queryKey: ['day-slots', route.params.labId, selectedServiceId, assetSelectionString, selectedDate],
    queryFn: () =>
      listDaySlotsRequest(route.params.labId, selectedDate, {
        service_id: selectedServiceId!,
        assets: assetSelectionString,
      }),
    enabled: Boolean(selectedServiceId && selectedDate && (assetSelectionString || !hasServiceAssets)),
  });

  const recommendedSlots = useMemo(() => {
    return (recommendedSlotsQuery.data?.slots ?? []).filter(
      (slot) => !isPastBookingSlot(slot.date, slot.end, deviceNow),
    );
  }, [deviceNow, recommendedSlotsQuery.data?.slots]);

  const selectedDaySlots = useMemo(() => {
    return (selectedDaySlotsQuery.data?.slots ?? []).map((slot) => {
      if (!selectedDate || !isPastBookingSlot(selectedDate, slot.end, deviceNow)) {
        return slot;
      }

      return {
        ...slot,
        can_book: false,
        reason: 'Slot is already in the past.',
      };
    });
  }, [deviceNow, selectedDate, selectedDaySlotsQuery.data?.slots]);

  useEffect(() => {
    if (!selectedDate) {
      return;
    }

    if (isPastBookingDate(selectedDate, deviceNow)) {
      setSelectedDate('');
      setStartTime('');
      setEndTime('');
      setSlotMessage({
        tone: 'warning',
        text: 'The previously selected booking date has already passed on this device. Please choose a new date.',
      });
      return;
    }

    if (startTime && endTime && isPastBookingSlot(selectedDate, endTime, deviceNow)) {
      setStartTime('');
      setEndTime('');
      setSlotMessage({
        tone: 'warning',
        text: 'The previously selected booking session has already ended on this device. Please choose another session.',
      });
    }
  }, [deviceNow, endTime, selectedDate, startTime]);

  useEffect(() => {
    if (!selectedDate || !startTime || !endTime || selectedDaySlotsQuery.isLoading) {
      return;
    }

    const matchingSlot = selectedDaySlots.find((slot) => slot.start === startTime && slot.end === endTime);
    if (!matchingSlot || matchingSlot.can_book) {
      return;
    }

    setStartTime('');
    setEndTime('');
    setSlotMessage({
      tone: 'warning',
      text: matchingSlot.reason || 'The selected booking session is no longer available. Please choose another session.',
    });
  }, [endTime, selectedDate, selectedDaySlots, selectedDaySlotsQuery.isLoading, startTime]);

  const slotCheckMutation = useMutation({
    mutationFn: checkBookingSlotRequest,
    onSuccess: (data) => {
      if (data.conflict) {
        setSlotMessage({
          tone: 'warning',
          text: data.reason || 'Selected booking session is not available.',
        });
      } else {
        setSlotMessage({
          tone: 'success',
          text: 'Selected booking session is available.',
        });
      }
    },
    onError: (error: unknown) => {
      setSlotMessage({
        tone: 'warning',
        text: readSlotErrorMessage(error),
      });
    },
  });

  const submitMutation = useMutation({
    mutationFn: submitBookingRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['bookings'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
      navigation.navigate('Main', { screen: isStudentRole(user?.primary_role ?? '') ? 'Bookings' : 'Home' });
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

  const serviceOptions = lab.services
    .filter((service) =>
      lab.assets.some(
        (asset) =>
          asset.lab_service_id === service.id &&
          asset.quantity > 0 &&
          asset.status.toLowerCase() !== 'maintenance' &&
          asset.status.toLowerCase() !== 'faulty',
      ),
    )
    .map((service) => ({
      id: String(service.id),
      label: service.service_name,
      subtitle: [service.field_name, service.equipment_models].filter(Boolean).join('  |  '),
    }));
  const slotLookupErrorMessage = selectedDaySlotsQuery.isError
    ? readSlotErrorMessage(selectedDaySlotsQuery.error)
    : recommendedSlotsQuery.isError
      ? readSlotErrorMessage(recommendedSlotsQuery.error)
      : null;
  const selectedSessionSummary =
    selectedDate && startTime && endTime ? `${formatDateLabel(selectedDate)}  |  ${startTime}-${endTime}` : null;

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

  function selectBookingSession(dateValue: string, startValue: string, endValue: string) {
    setSelectedDate(dateValue);
    setStartTime(startValue);
    setEndTime(endValue);
    setSlotMessage(null);
  }

  async function verifySlotBeforeSubmit() {
    if (!selectedServiceId || !selectedDate || !startTime || !endTime || !assetSelectionString) {
      return false;
    }

    if (isPastBookingSlot(selectedDate, endTime, deviceNow)) {
      setSlotMessage({
        tone: 'warning',
        text: 'Selected booking session is already in the past on this device. Please choose another session.',
      });
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
      setErrorMessage('Choose a booking date and one of the available sessions.');
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
      setErrorMessage('Selected booking session is not available. Choose another session before submitting.');
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
          {route.params?.source === 'qr'
            ? 'QR scan detected. Review the linked service and equipment, choose an available booking session, then submit the booking package.'
            : 'Choose a service, confirm linked equipment, select an available booking session, then submit the full booking package.'}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>4. Booking Session</Text>
        {recommendedSlots.length ? (
          <>
            <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
              Recommended next sessions based on the current service and equipment selection.
            </Text>
            <View style={styles.choiceWrap}>
              {recommendedSlots.map((slot) => {
                const selected = selectedDate === slot.date && startTime === slot.start && endTime === slot.end;

                return (
                  <Pressable
                    key={`${slot.date}-${slot.start}-${slot.end}`}
                    onPress={() => {
                      selectBookingSession(slot.date, slot.start, slot.end);
                    }}
                    style={[
                      styles.choiceChip,
                      {
                        backgroundColor: selected ? theme.colors.primary : theme.colors.primarySoft,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.choiceText,
                        {
                          color: selected ? '#ffffff' : theme.colors.primary,
                        },
                      ]}
                    >
                      {slot.label || `${slot.start}-${slot.end}`}  |  {formatDateLabel(slot.date)}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </>
        ) : null}

        {slotLookupErrorMessage ? (
          <View
            style={[
              styles.statusCard,
              {
                backgroundColor: theme.colors.warningSoft,
              },
            ]}
          >
            <Text
              style={[
                styles.statusText,
                {
                  color: theme.colors.warning,
                },
              ]}
            >
              {slotLookupErrorMessage}
            </Text>
          </View>
        ) : null}

        <PickerField
          label="Date"
          minimumDate={deviceToday}
          mode="date"
          onChangeValue={(value) => {
            setSelectedDate(value);
            setStartTime('');
            setEndTime('');
            setSlotMessage(null);
          }}
          placeholder="Select booking date"
          value={selectedDate}
        />

        {selectedSessionSummary ? (
          <View
            style={[
              styles.infoCard,
              {
                backgroundColor: theme.colors.primarySoft,
              },
            ]}
          >
            <Text style={[styles.infoTitle, { color: theme.colors.primary }]}>Selected session</Text>
            <Text style={[styles.infoText, { color: theme.colors.text }]}>{selectedSessionSummary}</Text>
          </View>
        ) : null}

        {selectedDate ? (
          selectedDaySlotsQuery.isLoading ? (
            <View
              style={[
                styles.daySlotCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text style={[styles.daySlotText, { color: theme.colors.textMuted }]}>
                Loading available sessions...
              </Text>
            </View>
          ) : selectedDaySlots.length ? (
            <View
              style={[
                styles.daySlotCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text style={[styles.daySlotTitle, { color: theme.colors.text }]}>
                {formatDateLabel(selectedDate)} booking sessions
              </Text>
              <View style={styles.choiceWrap}>
                {selectedDaySlots.map((slot) => {
                  const selected = startTime === slot.start && endTime === slot.end;

                  return (
                    <Pressable
                      key={`${slot.label}-${slot.start}-${slot.end}`}
                      disabled={!slot.can_book}
                      onPress={() => {
                        selectBookingSession(selectedDate, slot.start, slot.end);
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
            </View>
          ) : (
            <View
              style={[
                styles.daySlotCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text style={[styles.daySlotText, { color: theme.colors.textMuted }]}>
                No booking sessions are available for this date with the current equipment selection.
              </Text>
            </View>
          )
        ) : null}

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
        disabled={submitMutation.isPending || slotCheckMutation.isPending}
        onPress={() => {
          void handleSubmit();
        }}
        style={[
          styles.submitButton,
          {
            backgroundColor: theme.colors.primary,
            opacity: submitMutation.isPending || slotCheckMutation.isPending ? 0.7 : 1,
          },
        ]}
      >
        <Text style={styles.submitButtonText}>
          {submitMutation.isPending
            ? 'Submitting Booking...'
            : slotCheckMutation.isPending
              ? 'Verifying slot...'
              : 'Submit Booking'}
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
  helperText: {
    fontSize: 13,
    lineHeight: 18,
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
  slotMetaText: {
    fontSize: 12,
    lineHeight: 16,
    marginTop: 4,
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
