import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useRoute } from '@react-navigation/native';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { cancelBookingRequest, getBookingRequest } from '../api/endpoints';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatusPill } from '../components/status-pill';
import { useAppTheme } from '../theme/use-app-theme';
import { useNavigation } from '@react-navigation/native';
import { openProtectedPdf } from '../utils/protected-document';
import { formatDateTimeRange } from '../utils/format';
import { getBookingDisplayStatus, getBookingStageSubtitle } from '../utils/booking-status';
import type { RootStackParamList } from '../navigation/types';
import { readErrorMessage } from '../utils/error-message';

export function BookingDetailScreen() {
  const theme = useAppTheme();
  const route = useRoute<RouteProp<RootStackParamList, 'BookingDetail'>>();
  const navigation = useNavigation<any>();
  const queryClient = useQueryClient();
  const [documentError, setDocumentError] = useState<string | null>(null);
  const [documentBusy, setDocumentBusy] = useState(false);

  const bookingQuery = useQuery({
    queryKey: ['booking', route.params.bookingId],
    queryFn: () => getBookingRequest(route.params.bookingId),
  });

  const cancelMutation = useMutation({
    mutationFn: cancelBookingRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['bookings'] });
      await queryClient.invalidateQueries({ queryKey: ['booking', route.params.bookingId] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
    },
  });

  if (bookingQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading booking details..." />
      </Screen>
    );
  }

  if (bookingQuery.isError || !bookingQuery.data) {
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

  const { booking } = bookingQuery.data;

  async function handleOpenDocument() {
    setDocumentError(null);
    setDocumentBusy(true);

    try {
      await openProtectedPdf(
        booking.document_url,
        booking.pdf_path.split('/').pop() || `booking-${booking.id}.pdf`,
      );
    } catch (error) {
      setDocumentError(readErrorMessage(error, 'The document could not be opened.'));
    } finally {
      setDocumentBusy(false);
    }
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
        <View style={styles.header}>
          <View style={styles.headerTitleWrap}>
            <Text style={[styles.title, { color: theme.colors.text }]}>{booking.lab_name}</Text>
            <Text style={[styles.subtitle, { color: theme.colors.textMuted }]}>
              {booking.service_name || 'General booking'}
            </Text>
          </View>
          <StatusPill status={getBookingDisplayStatus(booking)} />
        </View>
        {getBookingStageSubtitle(booking) ? (
          <Text style={[styles.stageSub, { color: theme.colors.textMuted }]}>
            {getBookingStageSubtitle(booking)}
          </Text>
        ) : null}

        <Text style={[styles.meta, { color: theme.colors.primary }]}>
          {formatDateTimeRange(booking.date, booking.start_time, booking.end_time)}
        </Text>
        <Text style={[styles.bodyText, { color: theme.colors.text }]}>{booking.activity}</Text>
        <Text style={[styles.bodyText, { color: theme.colors.textMuted }]}>Room: {booking.lab_room || '-'}</Text>

        {booking.document_url ? (
          <View
            style={[
              styles.note,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.noteTitle, { color: theme.colors.text }]}>Supporting document attached</Text>
            <Text style={[styles.noteText, { color: theme.colors.textMuted }]}>
              Open the supporting document in your device viewer when you need to review it.
            </Text>
            <Pressable
              disabled={documentBusy}
              onPress={() => {
                void handleOpenDocument();
              }}
              style={[
                styles.documentButton,
                {
                  backgroundColor: theme.colors.primary,
                  opacity: documentBusy ? 0.7 : 1,
                },
              ]}
            >
              <Text style={styles.documentButtonText}>{documentBusy ? 'Opening...' : 'Open Document'}</Text>
            </Pressable>
            {documentError ? (
              <Text style={[styles.documentError, { color: theme.colors.danger }]}>{documentError}</Text>
            ) : null}
          </View>
        ) : null}

        {booking.can_edit ? (
          <Pressable
            onPress={() => {
              navigation.navigate('BookingEdit', { bookingId: booking.id });
            }}
            style={[
              styles.editButton,
              {
                backgroundColor: theme.colors.primarySoft,
              },
            ]}
          >
            <Text style={[styles.editButtonText, { color: theme.colors.primary }]}>Edit Booking</Text>
          </Pressable>
        ) : null}
        {booking.can_cancel ? (
          <Pressable
            disabled={cancelMutation.isPending}
            onPress={() => {
              void cancelMutation.mutateAsync(booking.id);
            }}
            style={[
              styles.cancelButton,
              {
                backgroundColor: theme.colors.dangerSoft,
              },
            ]}
          >
            <Text style={[styles.cancelButtonText, { color: theme.colors.danger }]}>Cancel Pending Booking</Text>
          </Pressable>
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Applicants</Text>
        {booking.applicants.map((applicant) => (
          <View
            key={applicant.id}
            style={[
              styles.innerCard,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.innerTitle, { color: theme.colors.text }]}>{applicant.name}</Text>
            <Text style={[styles.innerText, { color: theme.colors.textMuted }]}>
              {applicant.matric_id}  |  {applicant.email}
            </Text>
          </View>
        ))}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Assets</Text>
        {booking.assets.map((asset) => (
          <View
            key={asset.id}
            style={[
              styles.innerCard,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.innerTitle, { color: theme.colors.text }]}>{asset.name}</Text>
            <Text style={[styles.innerText, { color: theme.colors.textMuted }]}>
              Quantity used: {asset.quantity_used}
            </Text>
          </View>
        ))}
      </View>
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
  header: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  headerTitleWrap: {
    flex: 1,
    gap: 4,
    paddingRight: 10,
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
  },
  subtitle: {
    fontSize: 13,
  },
  stageSub: {
    fontSize: 12,
    marginTop: 2,
  },
  meta: {
    fontSize: 13,
    fontWeight: '700',
  },
  bodyText: {
    fontSize: 14,
    lineHeight: 20,
  },
  note: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  noteTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  noteText: {
    fontSize: 13,
    lineHeight: 18,
  },
  editButton: {
    alignItems: 'center',
    borderRadius: 12,
    paddingVertical: 12,
  },
  editButtonText: {
    fontSize: 13,
    fontWeight: '800',
  },
  cancelButton: {
    alignItems: 'center',
    borderRadius: 12,
    paddingVertical: 12,
  },
  cancelButtonText: {
    fontSize: 13,
    fontWeight: '800',
  },
  documentButton: {
    alignItems: 'center',
    borderRadius: 12,
    marginTop: 4,
    paddingVertical: 11,
  },
  documentButtonText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '800',
  },
  documentError: {
    fontSize: 12,
    lineHeight: 18,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  innerCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  innerTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  innerText: {
    fontSize: 13,
  },
});
