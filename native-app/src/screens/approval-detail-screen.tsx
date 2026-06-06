import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  approveBookingRequest,
  getApprovalQueueItemRequest,
  rejectBookingRequest,
} from '../api/endpoints';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { useAppTheme } from '../theme/use-app-theme';
import { openProtectedPdf } from '../utils/protected-document';
import { formatDateTimeRange } from '../utils/format';
import type { RootStackParamList } from '../navigation/types';
import { readErrorMessage } from '../utils/error-message';

export function ApprovalDetailScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'ApprovalDetail'>>();
  const queryClient = useQueryClient();
  const [documentError, setDocumentError] = useState<string | null>(null);
  const [documentBusy, setDocumentBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const detailQuery = useQuery({
    queryKey: ['approval-queue-item', route.params.bookingId],
    queryFn: () => getApprovalQueueItemRequest(route.params.bookingId),
  });

  const approveMutation = useMutation({
    mutationFn: approveBookingRequest,
    onMutate: () => { setActionError(null); },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['approval-queue'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
      navigation.goBack();
    },
    onError: (error) => {
      setActionError(readErrorMessage(error, 'Approval failed. Please try again.'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: rejectBookingRequest,
    onMutate: () => { setActionError(null); },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['approval-queue'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
      navigation.goBack();
    },
    onError: (error) => {
      setActionError(readErrorMessage(error, 'Rejection failed. Please try again.'));
    },
  });

  if (detailQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading approval details..." />
      </Screen>
    );
  }

  if (detailQuery.isError || !detailQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="Approval details could not be loaded."
          onRetry={() => {
            void detailQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const { booking } = detailQuery.data;

  async function handleOpenDocument() {
    setDocumentError(null);
    setDocumentBusy(true);

    try {
      await openProtectedPdf(booking.pdf_url, `approval-${booking.id}.pdf`);
    } catch (error) {
      setDocumentError(readErrorMessage(error, 'The document could not be opened.'));
    } finally {
      setDocumentBusy(false);
    }
  }

  return (
    <Screen maxWidth="narrow">
      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.title, { color: theme.colors.text }]}>{booking.lab_name}</Text>
        <Text style={[styles.meta, { color: theme.colors.primary }]}>
          {formatDateTimeRange(booking.date, booking.start_time, booking.end_time)}
        </Text>
        <Text style={[styles.bodyText, { color: theme.colors.text }]}>{booking.activity}</Text>
        <Text style={[styles.bodyText, { color: theme.colors.textMuted }]}>
          Faculty: {booking.faculty_name || 'Unknown'}
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
            <Text style={[styles.innerText, { color: theme.colors.textMuted }]}>
              {applicant.phone}
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
            key={`${asset.asset_id}-${asset.name}`}
            style={[
              styles.innerCard,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.innerTitle, { color: theme.colors.text }]}>{asset.name}</Text>
            <Text style={[styles.innerText, { color: theme.colors.textMuted }]}>
              Quantity needed: {asset.quantity_used}
            </Text>
            {asset.model ? (
              <Text style={[styles.innerText, { color: theme.colors.textMuted }]}>Model: {asset.model}</Text>
            ) : null}
          </View>
        ))}
      </View>

      {(booking.supervisor_name || booking.supervisor_email || booking.supervisor_phone) && (
        <View
          style={[
            styles.card,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Supervisor</Text>
          <Text style={[styles.innerText, { color: theme.colors.textMuted }]}>
            {booking.supervisor_name || 'Not provided'}
          </Text>
          <Text style={[styles.innerText, { color: theme.colors.textMuted }]}>
            {booking.supervisor_email || 'No email'}
          </Text>
          <Text style={[styles.innerText, { color: theme.colors.textMuted }]}>
            {booking.supervisor_phone || 'No phone'}
          </Text>
        </View>
      )}

      {booking.pdf_url ? (
        <View
          style={[
            styles.noteCard,
            {
              backgroundColor: theme.colors.primarySoft,
            },
          ]}
        >
          <Text style={[styles.noteTitle, { color: theme.colors.primary }]}>Supporting document attached</Text>
          <Text style={[styles.noteText, { color: theme.colors.text }]}>
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

      {actionError ? (
        <Text style={[styles.actionError, { color: theme.colors.danger }]}>{actionError}</Text>
      ) : null}
      <View style={styles.actionRow}>
        <Pressable
          disabled={rejectMutation.isPending || approveMutation.isPending}
          onPress={() => {
            void rejectMutation.mutateAsync(booking.id);
          }}
          style={[
            styles.rejectButton,
            {
              backgroundColor: theme.colors.dangerSoft,
            },
          ]}
        >
          <Text style={[styles.rejectButtonText, { color: theme.colors.danger }]}>Reject</Text>
        </Pressable>
        <Pressable
          disabled={approveMutation.isPending || rejectMutation.isPending}
          onPress={() => {
            void approveMutation.mutateAsync(booking.id);
          }}
          style={[
            styles.approveButton,
            {
              backgroundColor: theme.colors.success,
            },
          ]}
        >
          <Text style={styles.approveButtonText}>Approve</Text>
        </Pressable>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  title: {
    fontSize: 22,
    fontWeight: '800',
  },
  meta: {
    fontSize: 13,
    fontWeight: '700',
  },
  bodyText: {
    fontSize: 14,
    lineHeight: 20,
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
    lineHeight: 18,
  },
  noteCard: {
    borderRadius: 18,
    gap: 8,
    padding: 16,
  },
  noteTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  noteText: {
    fontSize: 14,
    lineHeight: 20,
  },
  documentButton: {
    alignItems: 'center',
    borderRadius: 12,
    paddingVertical: 12,
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
  actionError: {
    fontSize: 13,
    lineHeight: 18,
    marginBottom: 8,
    textAlign: 'center',
  },
  actionRow: {
    flexDirection: 'row',
    gap: 12,
  },
  rejectButton: {
    alignItems: 'center',
    borderRadius: 14,
    flex: 1,
    paddingVertical: 14,
  },
  rejectButtonText: {
    fontSize: 15,
    fontWeight: '800',
  },
  approveButton: {
    alignItems: 'center',
    borderRadius: 14,
    flex: 1,
    paddingVertical: 14,
  },
  approveButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
});
