import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { listMaintenanceRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatusPill } from '../components/status-pill';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateLabel } from '../utils/format';

type FilterMode = 'mine' | 'all' | 'testing';

export function MaintenanceScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const [filterMode, setFilterMode] = useState<FilterMode>('mine');

  const canUseMaintenance = role === 'pic';
  const queryParams = useMemo(() => {
    if (filterMode === 'testing') {
      return { status: 'testing' };
    }

    return filterMode === 'mine' ? { scope: 'mine' } : {};
  }, [filterMode]);

  const maintenanceQuery = useQuery({
    queryKey: ['maintenance-workspace', queryParams],
    queryFn: () => listMaintenanceRequest(queryParams),
    enabled: canUseMaintenance,
  });

  if (!canUseMaintenance) {
    return (
      <Screen>
        <EmptyState
          title="No maintenance workspace"
          message="The maintenance queue is only available to PIC users in SLAMS."
        />
      </Screen>
    );
  }

  if (maintenanceQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading maintenance cases..." />
      </Screen>
    );
  }

  if (maintenanceQuery.isError || !maintenanceQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="Maintenance cases could not be loaded."
          onRetry={() => {
            void maintenanceQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const { stats, records } = maintenanceQuery.data;

  return (
    <Screen>
      <View style={styles.statsRow}>
        <View
          style={[
            styles.statBlock,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Assigned</Text>
          <Text style={[styles.statValue, { color: theme.colors.primary }]}>{stats.assigned}</Text>
        </View>
        <View
          style={[
            styles.statBlock,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Open</Text>
          <Text style={[styles.statValue, { color: theme.colors.warning }]}>{stats.open_total}</Text>
        </View>
        <View
          style={[
            styles.statBlock,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Testing</Text>
          <Text style={[styles.statValue, { color: theme.colors.accent }]}>{stats.testing}</Text>
        </View>
        <View
          style={[
            styles.statBlock,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.statLabel, { color: theme.colors.textMuted }]}>Predictive</Text>
          <Text style={[styles.statValue, { color: theme.colors.danger }]}>{stats.predictive}</Text>
        </View>
      </View>

      <View style={styles.filterRow}>
        {[
          { id: 'mine', label: 'My Cases' },
          { id: 'all', label: 'All Cases' },
          { id: 'testing', label: 'Testing' },
        ].map((filter) => {
          const active = filterMode === filter.id;
          return (
            <Pressable
              key={filter.id}
              onPress={() => setFilterMode(filter.id as FilterMode)}
              style={[
                styles.filterChip,
                {
                  backgroundColor: active ? theme.colors.primarySoft : theme.colors.surface,
                  borderColor: active ? theme.colors.primary : theme.colors.border,
                },
              ]}
            >
              <Text
                style={[
                  styles.filterChipText,
                  {
                    color: active ? theme.colors.primary : theme.colors.text,
                  },
                ]}
              >
                {filter.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      <Pressable
        onPress={() => navigation.navigate('MaintenanceForm', {})}
        style={[
          styles.createButton,
          {
            backgroundColor: theme.colors.primary,
          },
        ]}
      >
        <Text style={styles.createButtonText}>Plan Preventive Maintenance</Text>
      </Pressable>

      {maintenanceQuery.data.predictive_alerts.length > 0 ? (
        <View
          style={[
            styles.alertPanel,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.panelTitle, { color: theme.colors.text }]}>Predictive Alerts</Text>
          <Text style={[styles.panelMeta, { color: theme.colors.textMuted }]}>
            High-risk assets are prioritized using booking pressure and maintenance history.
          </Text>
          {maintenanceQuery.data.predictive_alerts.slice(0, 3).map((alert) => (
            <Pressable
              key={`${alert.asset_id}-${alert.next_due_at}`}
              onPress={() =>
                navigation.navigate('MaintenanceForm', {
                  assetId: alert.asset_id,
                })
              }
              style={[
                styles.alertCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <View style={styles.cardHeader}>
                <View style={styles.cardTitleWrap}>
                  <Text style={[styles.cardTitle, { color: theme.colors.text }]}>{alert.asset_name}</Text>
                  <Text style={[styles.cardMeta, { color: theme.colors.primary }]}>
                    {alert.decision_label}
                  </Text>
                </View>
                <Text style={[styles.riskBadge, { color: theme.colors.danger }]}>
                  {alert.risk_percent}%
                </Text>
              </View>
              <Text style={[styles.cardBody, { color: theme.colors.textMuted }]}>
                {alert.lab_name || 'Unassigned lab'}
                {alert.next_due_at ? `  |  Due ${formatDateLabel(alert.next_due_at)}` : ''}
              </Text>
              {alert.reasons[0] ? (
                <Text style={[styles.cardBody, { color: theme.colors.textMuted }]}>{alert.reasons[0]}</Text>
              ) : null}
            </Pressable>
          ))}
        </View>
      ) : null}

      {records.length === 0 ? (
        <EmptyState
          title="No maintenance cases"
          message="The current maintenance queue is clear for this filter."
        />
      ) : (
        records.map((record) => (
          <Pressable
            key={record.id}
            onPress={() => navigation.navigate('MaintenanceForm', { maintenanceId: record.id })}
            style={[
              styles.card,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.cardHeader}>
              <View style={styles.cardTitleWrap}>
                <Text style={[styles.cardTitle, { color: theme.colors.text }]}>{record.title}</Text>
                <Text style={[styles.cardMeta, { color: theme.colors.textMuted }]}>
                  {record.asset_name}  |  {record.lab_name}
                </Text>
              </View>
              <StatusPill kind="maintenance" status={record.status} />
            </View>

            <Text style={[styles.cardMeta, { color: theme.colors.primary }]}>
              {record.scheduled_for ? `Scheduled ${formatDateLabel(record.scheduled_for)}` : 'No schedule set'}
            </Text>
            <Text style={[styles.cardBody, { color: theme.colors.textMuted }]}>
              {record.issue_type.replace('_', ' ').toUpperCase()}  |  Priority {record.priority.toUpperCase()}
            </Text>
            <Text style={[styles.cardBody, { color: theme.colors.textMuted }]}>
              Quantity {record.quantity_affected}
              {record.unit_reference ? `  |  Unit ${record.unit_reference}` : ''}
            </Text>

          </Pressable>
        ))
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  statBlock: {
    borderRadius: 16,
    borderWidth: 1,
    flex: 1,
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
  filterRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  filterChip: {
    borderRadius: 999,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  filterChipText: {
    fontSize: 13,
    fontWeight: '800',
  },
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
  alertPanel: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  panelTitle: {
    fontSize: 16,
    fontWeight: '800',
  },
  panelMeta: {
    fontSize: 13,
    lineHeight: 18,
  },
  alertCard: {
    borderRadius: 14,
    gap: 8,
    padding: 12,
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  cardHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  cardTitleWrap: {
    flex: 1,
    gap: 4,
    paddingRight: 12,
  },
  cardTitle: {
    fontSize: 17,
    fontWeight: '800',
  },
  cardMeta: {
    fontSize: 13,
  },
  cardBody: {
    fontSize: 13,
    lineHeight: 18,
  },
  riskBadge: {
    fontSize: 18,
    fontWeight: '800',
  },
});
