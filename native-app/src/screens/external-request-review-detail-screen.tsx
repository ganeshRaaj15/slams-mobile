import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  getExternalRequestReviewRequest,
  updateExternalRequestReviewStatusRequest,
} from '../api/endpoints';
import { EXTERNAL_REQUEST_STATUS_LABELS } from '../constants/statuses';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { StatusPill } from '../components/status-pill';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';
import { formatDateLabel } from '../utils/format';
import type { RootStackParamList } from '../navigation/types';

export function ExternalRequestReviewDetailScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'ExternalRequestReviewDetail'>>();
  const queryClient = useQueryClient();
  const [selectedStatus, setSelectedStatus] = useState('');
  const [reviewNotes, setReviewNotes] = useState('');
  const [showStatusPicker, setShowStatusPicker] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const detailQuery = useQuery({
    queryKey: ['external-request-review', route.params.requestId],
    queryFn: () => getExternalRequestReviewRequest(route.params.requestId),
  });

  const updateMutation = useMutation({
    mutationFn: (payload: { status: string; review_notes: string }) =>
      updateExternalRequestReviewStatusRequest(route.params.requestId, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['external-request-review-queue'] });
      await queryClient.invalidateQueries({ queryKey: ['external-request-review', route.params.requestId] });
      await queryClient.invalidateQueries({ queryKey: ['notifications'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setFormError(readErrorMessage(error, 'Could not save the external request review.'));
    },
  });

  if (detailQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading external request..." />
      </Screen>
    );
  }

  if (detailQuery.isError || !detailQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The external request detail could not be loaded."
          onRetry={() => {
            void detailQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const request = detailQuery.data.request;
  const currentStatus = selectedStatus || request.status;

  useEffect(() => {
    setSelectedStatus(request.status);
    setReviewNotes(request.review_notes || '');
  }, [request.id, request.review_notes, request.status]);

  async function handleSave() {
    setFormError(null);

    await updateMutation.mutateAsync({
      status: currentStatus,
      review_notes: reviewNotes.trim(),
    });
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
          <View style={styles.headerText}>
            <Text style={[styles.title, { color: theme.colors.text }]}>{request.lab_name}</Text>
            <Text style={[styles.meta, { color: theme.colors.textMuted }]}>
              {request.requester_name}  |  {request.organization_name}
            </Text>
          </View>
          <StatusPill kind="external" status={request.status} />
        </View>

        <Text style={[styles.meta, { color: theme.colors.primary }]}>
          Preferred schedule: {formatDateLabel(request.preferred_date)}
          {request.preferred_start_time && request.preferred_end_time
            ? `  |  ${request.preferred_start_time}-${request.preferred_end_time}`
            : ''}
        </Text>

        <View style={styles.summaryGrid}>
          <View style={styles.summaryItem}>
            <Text style={[styles.summaryLabel, { color: theme.colors.textMuted }]}>Organization</Text>
            <Text style={[styles.summaryValue, { color: theme.colors.text }]}>{request.organization_name}</Text>
          </View>
          <View style={styles.summaryItem}>
            <Text style={[styles.summaryLabel, { color: theme.colors.textMuted }]}>Participants</Text>
            <Text style={[styles.summaryValue, { color: theme.colors.text }]}>{request.participant_count}</Text>
          </View>
          <View style={styles.summaryItem}>
            <Text style={[styles.summaryLabel, { color: theme.colors.textMuted }]}>Contact email</Text>
            <Text style={[styles.summaryValue, { color: theme.colors.text }]}>{request.contact_email}</Text>
          </View>
          <View style={styles.summaryItem}>
            <Text style={[styles.summaryLabel, { color: theme.colors.textMuted }]}>Contact phone</Text>
            <Text style={[styles.summaryValue, { color: theme.colors.text }]}>{request.contact_phone}</Text>
          </View>
        </View>

        <View
          style={[
            styles.noteBlock,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.blockTitle, { color: theme.colors.text }]}>Purpose</Text>
          <Text style={[styles.blockText, { color: theme.colors.textMuted }]}>{request.purpose}</Text>
        </View>

        <View
          style={[
            styles.noteBlock,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.blockTitle, { color: theme.colors.text }]}>Equipment / setup notes</Text>
          <Text style={[styles.blockText, { color: theme.colors.textMuted }]}>
            {request.equipment_notes || 'No additional equipment notes were provided.'}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Review Decision</Text>

        <Pressable
          onPress={() => setShowStatusPicker(true)}
          style={[
            styles.selector,
            {
              backgroundColor: theme.colors.surfaceMuted,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.selectorLabel, { color: theme.colors.text }]}>Status</Text>
          <Text style={[styles.selectorValue, { color: theme.colors.primary }]}>
            {EXTERNAL_REQUEST_STATUS_LABELS[currentStatus] ?? currentStatus}
          </Text>
        </Pressable>

        <TextField
          label="Review notes"
          hint="Required when requesting more information or rejecting the request."
          multiline
          numberOfLines={8}
          onChangeText={setReviewNotes}
          placeholder={request.review_notes || 'Explain what the requester should know next.'}
          style={styles.multiline}
          textAlignVertical="top"
          value={reviewNotes}
        />

        <View
          style={[
            styles.historyCard,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.blockTitle, { color: theme.colors.text }]}>Last review history</Text>
          <Text style={[styles.blockText, { color: theme.colors.textMuted }]}>
            Reviewer: {request.reviewer_name || 'No reviewer yet'}
          </Text>
          <Text style={[styles.blockText, { color: theme.colors.textMuted }]}>
            Reviewed: {request.reviewed_at ? formatDateLabel(request.reviewed_at) : 'Not reviewed yet'}
          </Text>
          <Text style={[styles.blockText, { color: theme.colors.textMuted }]}>
            Notes: {request.review_notes || 'No review notes recorded yet.'}
          </Text>
        </View>

        {formError ? <Text style={[styles.errorText, { color: theme.colors.danger }]}>{formError}</Text> : null}

        <Pressable
          disabled={updateMutation.isPending}
          onPress={() => {
            void handleSave();
          }}
          style={[
            styles.saveButton,
            {
              backgroundColor: theme.colors.primary,
              opacity: updateMutation.isPending ? 0.7 : 1,
            },
          ]}
        >
          <Text style={styles.saveButtonText}>Save Review Decision</Text>
        </Pressable>
      </View>

      <SelectionModal
        onClose={() => setShowStatusPicker(false)}
        onSelect={setSelectedStatus}
        options={Object.entries(EXTERNAL_REQUEST_STATUS_LABELS).map(([status, label]) => ({
          id: status,
          label,
        }))}
        selectedId={currentStatus}
        title="Select Status"
        visible={showStatusPicker}
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
  header: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  headerText: {
    flex: 1,
    gap: 4,
    paddingRight: 12,
  },
  title: {
    fontSize: 19,
    fontWeight: '800',
  },
  meta: {
    fontSize: 12,
    lineHeight: 18,
  },
  summaryGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  summaryItem: {
    flexBasis: '47%',
    flexGrow: 1,
    gap: 4,
  },
  summaryLabel: {
    fontSize: 12,
    fontWeight: '700',
  },
  summaryValue: {
    fontSize: 14,
    lineHeight: 19,
  },
  noteBlock: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  blockTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  blockText: {
    fontSize: 13,
    lineHeight: 19,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  selector: {
    borderRadius: 14,
    borderWidth: 1,
    gap: 4,
    padding: 14,
  },
  selectorLabel: {
    fontSize: 13,
    fontWeight: '700',
  },
  selectorValue: {
    fontSize: 15,
    fontWeight: '800',
  },
  multiline: {
    minHeight: 140,
    paddingTop: 12,
  },
  historyCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  errorText: {
    fontSize: 13,
    fontWeight: '700',
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
});
