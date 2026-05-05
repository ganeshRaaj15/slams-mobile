import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';

import { listExternalRequestReviewQueueRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { StatusPill } from '../components/status-pill';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateLabel } from '../utils/format';

export function ExternalRequestReviewQueueScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [labFilterId, setLabFilterId] = useState(0);
  const [showLabPicker, setShowLabPicker] = useState(false);

  const reviewQueueQuery = useQuery({
    queryKey: ['external-request-review-queue'],
    queryFn: () => listExternalRequestReviewQueueRequest(),
  });

  if (reviewQueueQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading external request queue..." />
      </Screen>
    );
  }

  if (reviewQueueQuery.isError || !reviewQueueQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The external request review queue could not be loaded."
          onRetry={() => {
            void reviewQueueQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const { stats, status_labels: statusLabels, labs, requests, role } = reviewQueueQuery.data;

  const filteredRequests = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return requests.filter((request) => {
      if (statusFilter && request.status !== statusFilter) {
        return false;
      }

      if (labFilterId > 0 && request.lab_id !== labFilterId) {
        return false;
      }

      if (!normalizedSearch) {
        return true;
      }

      return [
        request.lab_name,
        request.lab_room,
        request.organization_name,
        request.requester_name,
        request.contact_name,
        request.contact_email,
        request.purpose,
      ]
        .join(' ')
        .toLowerCase()
        .includes(normalizedSearch);
    });
  }, [labFilterId, requests, search, statusFilter]);

  const activeLab = labs.find((lab) => lab.id === labFilterId);

  return (
    <Screen>
      <View
        style={[
          styles.heroCard,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.heroTitle, { color: theme.colors.text }]}>External Request Queue</Text>
        <Text style={[styles.heroText, { color: theme.colors.textMuted }]}>
          {role === 'pic'
            ? 'Review requests for laboratories assigned to you as PIC.'
            : 'Review and triage external access requests across the SLAMS system.'}
        </Text>
      </View>

      <View style={styles.statsRow}>
        {[
          { id: 'total', label: 'Total', value: stats.total ?? 0, color: theme.colors.text },
          { id: 'submitted', label: 'Submitted', value: stats.submitted ?? 0, color: theme.colors.warning },
          {
            id: 'under_review',
            label: 'Reviewing',
            value: stats.under_review ?? 0,
            color: theme.colors.primary,
          },
          {
            id: 'approved_for_scheduling',
            label: 'Approved',
            value: stats.approved_for_scheduling ?? 0,
            color: theme.colors.success,
          },
        ].map((stat) => (
          <View
            key={stat.id}
            style={[
              styles.statCard,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>{stat.label}</Text>
            <Text style={[styles.statValue, { color: stat.color }]}>{stat.value}</Text>
          </View>
        ))}
      </View>

      <View
        style={[
          styles.filterCard,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <TextField
          label="Search"
          onChangeText={setSearch}
          placeholder="Lab, organization, contact, or purpose"
          value={search}
        />

        <View style={styles.statusChips}>
          <Pressable
            onPress={() => setStatusFilter('')}
            style={[
              styles.chip,
              {
                backgroundColor: statusFilter === '' ? theme.colors.primarySoft : theme.colors.surfaceMuted,
                borderColor: statusFilter === '' ? theme.colors.primary : theme.colors.border,
              },
            ]}
          >
            <Text
              style={[
                styles.chipText,
                {
                  color: statusFilter === '' ? theme.colors.primary : theme.colors.text,
                },
              ]}
            >
              All Statuses
            </Text>
          </Pressable>

          {Object.entries(statusLabels).map(([status, label]) => {
            const active = statusFilter === status;
            return (
              <Pressable
                key={status}
                onPress={() => setStatusFilter(status)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? theme.colors.primarySoft : theme.colors.surfaceMuted,
                    borderColor: active ? theme.colors.primary : theme.colors.border,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.chipText,
                    {
                      color: active ? theme.colors.primary : theme.colors.text,
                    },
                  ]}
                >
                  {label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        <Pressable
          onPress={() => setShowLabPicker(true)}
          style={[
            styles.labSelector,
            {
              backgroundColor: theme.colors.surfaceMuted,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.labSelectorLabel, { color: theme.colors.text }]}>Laboratory Filter</Text>
          <Text style={[styles.labSelectorValue, { color: theme.colors.primary }]}>
            {activeLab ? `${activeLab.name}${activeLab.room ? ` (${activeLab.room})` : ''}` : 'All laboratories'}
          </Text>
        </Pressable>
      </View>

      {filteredRequests.length === 0 ? (
        <EmptyState
          title="No requests matched"
          message="No external requests matched the current queue filters."
        />
      ) : (
        filteredRequests.map((request) => (
          <Pressable
            key={request.id}
            onPress={() => navigation.navigate('ExternalRequestReviewDetail', { requestId: request.id })}
            style={[
              styles.requestCard,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.requestHeader}>
              <View style={styles.requestTitleWrap}>
                <Text style={[styles.requestTitle, { color: theme.colors.text }]}>{request.lab_name}</Text>
                <Text style={[styles.requestMeta, { color: theme.colors.textMuted }]}>
                  {request.requester_name}  |  {request.organization_name}
                </Text>
              </View>
              <StatusPill kind="external" status={request.status} />
            </View>

            <Text style={[styles.requestMeta, { color: theme.colors.primary }]}>
              {formatDateLabel(request.preferred_date)}
              {request.preferred_start_time && request.preferred_end_time
                ? `  |  ${request.preferred_start_time}-${request.preferred_end_time}`
                : ''}
            </Text>
            <Text style={[styles.requestBody, { color: theme.colors.textMuted }]}>{request.purpose}</Text>
            {request.review_notes ? (
              <Text style={[styles.reviewNote, { color: theme.colors.textMuted }]}>
                Last note: {request.review_notes}
              </Text>
            ) : null}
          </Pressable>
        ))
      )}

      <SelectionModal
        onClose={() => setShowLabPicker(false)}
        onSelect={(value) => setLabFilterId(Number(value))}
        options={[
          { id: '0', label: 'All laboratories' },
          ...labs.map((lab) => ({
            id: String(lab.id),
            label: lab.name,
            subtitle: lab.room || undefined,
          })),
        ]}
        selectedId={String(labFilterId)}
        title="Select Laboratory"
        visible={showLabPicker}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  heroCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 6,
    padding: 16,
  },
  heroTitle: {
    fontSize: 19,
    fontWeight: '800',
  },
  heroText: {
    fontSize: 13,
    lineHeight: 19,
  },
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  statCard: {
    borderRadius: 16,
    borderWidth: 1,
    flexBasis: '47%',
    flexGrow: 1,
    gap: 6,
    padding: 14,
  },
  statLabel: {
    fontSize: 13,
    fontWeight: '700',
  },
  statValue: {
    fontSize: 22,
    fontWeight: '800',
  },
  filterCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 12,
    padding: 16,
  },
  statusChips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  chip: {
    borderRadius: 999,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  chipText: {
    fontSize: 12,
    fontWeight: '800',
  },
  labSelector: {
    borderRadius: 14,
    borderWidth: 1,
    gap: 4,
    padding: 14,
  },
  labSelectorLabel: {
    fontSize: 13,
    fontWeight: '700',
  },
  labSelectorValue: {
    fontSize: 15,
    fontWeight: '800',
  },
  requestCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  requestHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  requestTitleWrap: {
    flex: 1,
    gap: 4,
    paddingRight: 12,
  },
  requestTitle: {
    fontSize: 17,
    fontWeight: '800',
  },
  requestMeta: {
    fontSize: 12,
    lineHeight: 18,
  },
  requestBody: {
    fontSize: 13,
    lineHeight: 19,
  },
  reviewNote: {
    fontSize: 12,
    lineHeight: 18,
  },
});
