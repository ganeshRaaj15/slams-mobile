import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  getBookingRequest,
  listDaySlotsRequest,
  listFacultiesRequest,
  updateBookingRequest,
} from '../api/endpoints';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { PickerField } from '../components/picker-field';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateLabel } from '../utils/format';
import { readErrorMessage } from '../utils/error-message';
import type { BookingApplicantInput } from '../types/api';
import type { RootStackParamList } from '../navigation/types';

type EditApplicant = BookingApplicantInput;

function emptyApplicant(): EditApplicant {
  return { name: '', matric_id: '', email: '', phone: '', faculty_id: null };
}

export function BookingEditScreen() {
  const theme = useAppTheme();
  const route = useRoute<RouteProp<RootStackParamList, 'BookingEdit'>>();
  const navigation = useNavigation<any>();
  const queryClient = useQueryClient();

  const [date, setDate] = useState('');
  const [startTime, setStartTime] = useState('');
  const [endTime, setEndTime] = useState('');
  const [activity, setActivity] = useState('');
  const [supervisorName, setSupervisorName] = useState('');
  const [supervisorEmail, setSupervisorEmail] = useState('');
  const [supervisorPhone, setSupervisorPhone] = useState('');
  const [applicants, setApplicants] = useState<EditApplicant[]>([emptyApplicant()]);
  const [pickedPdf, setPickedPdf] = useState<{ uri: string; name: string; mimeType: string } | null>(null);
  const [facultyModalIndex, setFacultyModalIndex] = useState<number | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [initialized, setInitialized] = useState(false);

  const minimumDate = useMemo(() => {
    const next = new Date();
    next.setHours(12, 0, 0, 0);
    return next;
  }, []);

  const bookingQuery = useQuery({
    queryKey: ['booking', route.params.bookingId],
    queryFn: () => getBookingRequest(route.params.bookingId),
  });

  const facultiesQuery = useQuery({
    queryKey: ['faculties'],
    queryFn: listFacultiesRequest,
  });

  const booking = bookingQuery.data?.booking;

  const daySlotsQuery = useQuery({
    queryKey: ['day-slots-edit', booking?.lab_id, date, booking?.id],
    queryFn: () =>
      listDaySlotsRequest(booking!.lab_id, date, {
        service_id: booking!.service_id ?? 0,
        assets: '',
        exclude_booking_id: booking!.id,
      }),
    enabled: Boolean(booking?.lab_id && date),
  });

  useEffect(() => {
    if (!booking || initialized) {
      return;
    }

    setDate(booking.date);
    setStartTime(booking.start_time);
    setEndTime(booking.end_time);
    setActivity(booking.activity);
    setSupervisorName(booking.supervisor_name);
    setSupervisorEmail(booking.supervisor_email);
    setSupervisorPhone(booking.supervisor_phone);
    setApplicants(
      booking.applicants.length > 0
        ? booking.applicants.map((a) => ({
            name: a.name,
            matric_id: a.matric_id,
            email: a.email,
            phone: a.phone,
            faculty_id: a.faculty ? Number(a.faculty) : null,
          }))
        : [emptyApplicant()],
    );
    setInitialized(true);
  }, [booking, initialized]);

  useEffect(() => {
    if (!daySlotsQuery.data?.slots || !startTime || !endTime) {
      return;
    }

    const match = daySlotsQuery.data.slots.find((s) => s.start === startTime && s.end === endTime);
    if (!match?.can_book) {
      setStartTime('');
      setEndTime('');
    }
  }, [daySlotsQuery.data?.slots, startTime, endTime]);

  const saveMutation = useMutation({
    mutationFn: () =>
      updateBookingRequest(route.params.bookingId, {
        date,
        start_time: startTime,
        end_time: endTime,
        activity,
        supervisor_name: supervisorName,
        supervisor_email: supervisorEmail,
        supervisor_phone: supervisorPhone,
        applicants,
        pdf: pickedPdf ?? undefined,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['bookings'] });
      await queryClient.invalidateQueries({ queryKey: ['booking', route.params.bookingId] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setErrorMessage(readErrorMessage(error, 'Could not update the booking.'));
    },
  });

  function updateApplicant(index: number, patch: Partial<EditApplicant>) {
    setApplicants((current) =>
      current.map((applicant, i) => (i === index ? { ...applicant, ...patch } : applicant)),
    );
  }

  function addApplicant() {
    setApplicants((current) => [...current, emptyApplicant()]);
  }

  function removeApplicant(index: number) {
    setApplicants((current) => (current.length > 1 ? current.filter((_, i) => i !== index) : current));
  }

  async function pickPdf() {
    const result = await DocumentPicker.getDocumentAsync({
      copyToCacheDirectory: true,
      multiple: false,
      type: 'application/pdf',
    });

    if (result.canceled || !result.assets?.length) {
      return;
    }

    const asset = result.assets[0];
    setPickedPdf({
      uri: asset.uri,
      name: asset.name,
      mimeType: asset.mimeType || 'application/pdf',
    });
  }

  function handleSubmit() {
    setErrorMessage(null);

    if (!date || !startTime || !endTime) {
      setErrorMessage('Please choose a date and booking session.');
      return;
    }

    if (!activity.trim()) {
      setErrorMessage('Activity description is required.');
      return;
    }

    if (
      applicants.some(
        (a) => !a.name.trim() || !a.matric_id.trim() || !a.email.trim() || !a.phone.trim() || !a.faculty_id,
      )
    ) {
      setErrorMessage('Each applicant must include name, ID, email, phone, and faculty.');
      return;
    }

    void saveMutation.mutateAsync();
  }

  if (bookingQuery.isLoading || facultiesQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Preparing edit form..." />
      </Screen>
    );
  }

  if (bookingQuery.isError || !booking) {
    return (
      <Screen>
        <ErrorState
          message="Booking details could not be loaded."
          onRetry={() => {
            void bookingQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const faculties = facultiesQuery.data?.faculties ?? [];
  const selectedFaculty =
    facultyModalIndex !== null ? applicants[facultyModalIndex]?.faculty_id?.toString() ?? null : null;

  const selectedSessionSummary =
    date && startTime && endTime ? `${formatDateLabel(date)}  |  ${startTime}-${endTime}` : null;

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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>{booking.lab_name}</Text>
        {booking.service_name ? (
          <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>{booking.service_name}</Text>
        ) : null}
        <View
          style={[
            styles.readOnlyBadge,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.readOnlyText, { color: theme.colors.textMuted }]}>
            Lab and service cannot be changed
          </Text>
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>1. Applicants</Text>

        {applicants.map((applicant, index) => {
          const faculty = faculties.find((f) => f.id === applicant.faculty_id);

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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>2. Booking Session</Text>

        <PickerField
          label="Date"
          minimumDate={minimumDate}
          mode="date"
          onChangeValue={(value) => {
            setDate(value);
            setStartTime('');
            setEndTime('');
          }}
          placeholder="Select booking date"
          value={date}
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

        {date ? (
          daySlotsQuery.isLoading ? (
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
          ) : (daySlotsQuery.data?.slots ?? []).length > 0 ? (
            <View
              style={[
                styles.daySlotCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text style={[styles.daySlotLabel, { color: theme.colors.textMuted }]}>
                Available sessions for {formatDateLabel(date)}:
              </Text>
              <View style={styles.choiceWrap}>
                {(daySlotsQuery.data?.slots ?? []).map((slot) => {
                  const selected = startTime === slot.start && endTime === slot.end;

                  return (
                    <Pressable
                      disabled={!slot.can_book}
                      key={`${slot.start}-${slot.end}`}
                      onPress={() => {
                        if (slot.can_book) {
                          setStartTime(slot.start);
                          setEndTime(slot.end);
                        }
                      }}
                      style={[
                        styles.slotChip,
                        {
                          backgroundColor: !slot.can_book
                            ? theme.colors.surfaceMuted
                            : selected
                              ? theme.colors.primary
                              : theme.colors.primarySoft,
                          opacity: slot.can_book ? 1 : 0.5,
                        },
                      ]}
                    >
                      <Text
                        style={[
                          styles.slotChipLabel,
                          {
                            color: !slot.can_book
                              ? theme.colors.textMuted
                              : selected
                                ? '#ffffff'
                                : theme.colors.primary,
                          },
                        ]}
                      >
                        {slot.label || `${slot.start}-${slot.end}`}
                      </Text>
                      <Text
                        style={[
                          styles.slotChipSub,
                          {
                            color: !slot.can_book
                              ? theme.colors.textMuted
                              : selected
                                ? 'rgba(255,255,255,0.75)'
                                : theme.colors.primary,
                          },
                        ]}
                      >
                        {slot.can_book ? `${slot.start}–${slot.end}` : (slot.reason ?? 'Unavailable')}
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
                  backgroundColor: theme.colors.warningSoft,
                },
              ]}
            >
              <Text style={[styles.daySlotText, { color: theme.colors.warning }]}>
                No booking sessions available for this date.
              </Text>
            </View>
          )
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>3. Activity &amp; Supervisor</Text>

        <TextField
          label="Supervisor Name"
          onChangeText={setSupervisorName}
          placeholder="Supervisor name (optional)"
          value={supervisorName}
        />
        <TextField
          autoCapitalize="none"
          keyboardType="email-address"
          label="Supervisor Email"
          onChangeText={setSupervisorEmail}
          placeholder="supervisor@example.com (optional)"
          value={supervisorEmail}
        />
        <TextField
          keyboardType="phone-pad"
          label="Supervisor Phone"
          onChangeText={setSupervisorPhone}
          placeholder="Phone number (optional)"
          value={supervisorPhone}
        />
        <TextField
          label="Activity Description *"
          multiline
          numberOfLines={4}
          onChangeText={setActivity}
          placeholder="Describe the activity"
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
            {pickedPdf ? pickedPdf.name : booking.pdf_path ? 'Replace existing PDF (optional)' : 'Upload PDF (optional)'}
          </Text>
        </Pressable>
        {booking.pdf_path && !pickedPdf ? (
          <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
            A document is currently attached. Upload a new file only if you want to replace it.
          </Text>
        ) : null}
      </View>

      {errorMessage ? (
        <View
          style={[
            styles.errorCard,
            {
              backgroundColor: theme.colors.dangerSoft,
            },
          ]}
        >
          <Text style={[styles.errorText, { color: theme.colors.danger }]}>{errorMessage}</Text>
        </View>
      ) : null}

      <Pressable
        disabled={saveMutation.isPending}
        onPress={handleSubmit}
        style={[
          styles.submitButton,
          {
            backgroundColor: theme.colors.primary,
            opacity: saveMutation.isPending ? 0.7 : 1,
          },
        ]}
      >
        <Text style={styles.submitButtonText}>
          {saveMutation.isPending ? 'Saving...' : 'Save Changes'}
        </Text>
      </Pressable>

      <SelectionModal
        onClose={() => setFacultyModalIndex(null)}
        onSelect={(id) => {
          if (facultyModalIndex === null) {
            return;
          }
          updateApplicant(facultyModalIndex, { faculty_id: Number(id) });
          setFacultyModalIndex(null);
        }}
        options={faculties.map((faculty) => ({
          id: String(faculty.id),
          label: faculty.label,
        }))}
        selectedId={selectedFaculty}
        title="Choose Faculty"
        visible={facultyModalIndex !== null}
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
  readOnlyBadge: {
    alignSelf: 'flex-start',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  readOnlyText: {
    fontSize: 12,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  helperText: {
    fontSize: 13,
    lineHeight: 18,
  },
  applicantCard: {
    borderRadius: 14,
    gap: 8,
    padding: 14,
  },
  applicantHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  applicantTitle: {
    fontSize: 14,
    fontWeight: '700',
  },
  removeText: {
    fontSize: 13,
    fontWeight: '700',
  },
  selectorButton: {
    borderRadius: 10,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  selectorLabel: {
    fontSize: 14,
  },
  secondaryButton: {
    alignItems: 'center',
    borderRadius: 12,
    paddingVertical: 12,
  },
  secondaryButtonText: {
    fontSize: 13,
    fontWeight: '700',
  },
  infoCard: {
    borderRadius: 14,
    gap: 4,
    padding: 12,
  },
  infoTitle: {
    fontSize: 12,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  infoText: {
    fontSize: 14,
  },
  daySlotCard: {
    borderRadius: 14,
    gap: 8,
    padding: 12,
  },
  daySlotLabel: {
    fontSize: 12,
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  daySlotText: {
    fontSize: 13,
  },
  choiceWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  slotChip: {
    borderRadius: 10,
    gap: 2,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  slotChipLabel: {
    fontSize: 13,
    fontWeight: '700',
  },
  slotChipSub: {
    fontSize: 11,
  },
  errorCard: {
    borderRadius: 14,
    padding: 12,
  },
  errorText: {
    fontSize: 13,
    lineHeight: 18,
  },
  submitButton: {
    alignItems: 'center',
    borderRadius: 14,
    marginBottom: 8,
    paddingVertical: 14,
  },
  submitButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
});
