import { useQuery } from '@tanstack/react-query';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';

import { listExternalRequestsRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatusPill } from '../components/status-pill';
import { isExternalRole } from '../constants/roles';
import { ExternalRequestReviewQueueScreen } from './external-request-review-queue-screen';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateLabel } from '../utils/format';

export function RequestsScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const canReviewRequests = role === 'pic' || role === 'manager' || role === 'admin';

  const requestsQuery = useQuery({
    queryKey: ['external-requests'],
    queryFn: listExternalRequestsRequest,
    enabled: isExternalRole(role),
  });

  if (canReviewRequests) {
    return <ExternalRequestReviewQueueScreen />;
  }

  if (!isExternalRole(role)) {
    return (
      <Screen>
        <EmptyState
          title="No external request access"
          message="This workspace is only for external request submitters and operational reviewers."
        />
      </Screen>
    );
  }

  if (requestsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading requests..." />
      </Screen>
    );
  }

  if (requestsQuery.isError || !requestsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="External requests could not be loaded."
          onRetry={() => {
            void requestsQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <Pressable
        onPress={() => navigation.navigate('RequestForm', {})}
        style={[
          styles.createButton,
          {
            backgroundColor: theme.colors.primary,
          },
        ]}
      >
        <Text style={styles.createButtonText}>New External Request</Text>
      </Pressable>

      {requestsQuery.data.requests.length === 0 ? (
        <EmptyState
          title="No requests submitted"
          message="Create a request to start the external review workflow."
        />
      ) : (
        requestsQuery.data.requests.map((request) => (
          <Pressable
            key={request.id}
            onPress={() => {
              if (request.can_edit) {
                navigation.navigate('RequestForm', { requestId: request.id });
              }
            }}
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
                <Text style={[styles.subtitle, { color: theme.colors.textMuted }]}>
                  {formatDateLabel(request.preferred_date)}
                </Text>
              </View>
              <StatusPill kind="external" status={request.status} />
            </View>
            <Text style={[styles.message, { color: theme.colors.textMuted }]}>{request.purpose}</Text>
            <Text style={[styles.editHint, { color: theme.colors.textMuted }]}>
              Stage: {request.current_approval_stage_label}
            </Text>
            {request.latest_requester_note ? (
              <Text style={[styles.reviewNotes, { color: theme.colors.primary }]}>
                Latest note: {request.latest_requester_note}
              </Text>
            ) : null}
            {request.can_edit ? (
              <Text style={[styles.editHint, { color: theme.colors.textMuted }]}>
                Tap to update and resubmit.
              </Text>
            ) : null}
          </Pressable>
        ))
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  createButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  createButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
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
    paddingRight: 10,
  },
  title: {
    fontSize: 18,
    fontWeight: '800',
  },
  subtitle: {
    fontSize: 13,
  },
  message: {
    fontSize: 14,
    lineHeight: 20,
  },
  reviewNotes: {
    fontSize: 13,
    fontWeight: '700',
  },
  editHint: {
    fontSize: 12,
  },
});
