import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { listAdminServicesRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';

export function AdminServicesScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const [query, setQuery] = useState('');
  const [labId, setLabId] = useState(0);
  const [active, setActive] = useState('');
  const [labModalOpen, setLabModalOpen] = useState(false);
  const [statusModalOpen, setStatusModalOpen] = useState(false);

  const servicesQuery = useQuery({
    queryKey: ['admin-services', query, labId, active],
    queryFn: () =>
      listAdminServicesRequest({
        q: query.trim() || undefined,
        lab_id: labId > 0 ? labId : undefined,
        active: active || undefined,
      }),
  });

  const selectedLabLabel = useMemo(() => {
    if (!labId) {
      return 'All laboratories';
    }

    return servicesQuery.data?.labs.find((lab) => lab.id === labId)?.label ?? 'All laboratories';
  }, [labId, servicesQuery.data?.labs]);

  const selectedStatusLabel = active === '1' ? 'Active only' : active === '0' ? 'Inactive only' : 'All statuses';

  if (servicesQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading service bundles..." />
      </Screen>
    );
  }

  if (servicesQuery.isError || !servicesQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The service workspace could not be loaded."
          onRetry={() => {
            void servicesQuery.refetch();
          }}
        />
      </Screen>
    );
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
        <Text style={[styles.title, { color: theme.colors.text }]}>Service Bundles</Text>
        <TextField
          label="Search"
          onChangeText={setQuery}
          placeholder="Search by service or laboratory"
          value={query}
        />

        <View style={styles.filterRow}>
          <Pressable
            onPress={() => setLabModalOpen(true)}
            style={[styles.filterButton, { backgroundColor: theme.colors.surfaceMuted }]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]} numberOfLines={1}>
              {selectedLabLabel}
            </Text>
          </Pressable>
          <Pressable
            onPress={() => setStatusModalOpen(true)}
            style={[styles.filterButton, { backgroundColor: theme.colors.surfaceMuted }]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]}>{selectedStatusLabel}</Text>
          </Pressable>
        </View>

        <Pressable
          onPress={() => navigation.navigate('AdminServiceEditor', {})}
          style={[styles.primaryButton, { backgroundColor: theme.colors.primary }]}
        >
          <Text style={styles.primaryButtonText}>Create Service</Text>
        </Pressable>
      </View>

      {servicesQuery.data.services.length === 0 ? (
        <EmptyState title="No service bundles found" message="Adjust the filters or create a new service bundle." />
      ) : (
        servicesQuery.data.services.map((service) => (
          <Pressable
            key={service.id}
            onPress={() => navigation.navigate('AdminServiceEditor', { serviceId: service.id })}
            style={[
              styles.serviceCard,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.serviceHeader}>
              <View style={styles.serviceHeaderMeta}>
                <Text style={[styles.serviceTitle, { color: theme.colors.text }]}>{service.service_name}</Text>
                <Text style={[styles.metaText, { color: theme.colors.primary }]}>
                  {service.lab_name || 'No lab'} {service.lab_room ? `| ${service.lab_room}` : ''}
                </Text>
              </View>
              <View
                style={[
                  styles.pill,
                  {
                    backgroundColor: service.is_bookable ? theme.colors.successSoft : theme.colors.warningSoft,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.pillText,
                    { color: service.is_bookable ? theme.colors.success : theme.colors.warning },
                  ]}
                >
                  {service.is_bookable ? 'Available' : 'Unavailable'}
                </Text>
              </View>
            </View>

            <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
              {service.field_name || 'General service'}
            </Text>
            <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
              {service.bundle_summary || service.equipment_models || 'No linked bundle'}
            </Text>
            <View style={styles.summaryRow}>
              <View style={[styles.summaryPill, { backgroundColor: theme.colors.primarySoft }]}>
                <Text style={[styles.summaryText, { color: theme.colors.primary }]}>
                  Assets {service.required_assets.length}
                </Text>
              </View>
              <View style={[styles.summaryPill, { backgroundColor: theme.colors.surfaceMuted }]}>
                <Text style={[styles.summaryText, { color: theme.colors.text }]}>
                  {service.is_active ? 'Active' : 'Inactive'}
                </Text>
              </View>
            </View>
          </Pressable>
        ))
      )}

      <SelectionModal
        onClose={() => setLabModalOpen(false)}
        onSelect={(value) => setLabId(value ? Number(value) : 0)}
        options={[
          { id: '', label: 'All laboratories' },
          ...servicesQuery.data.labs.map((lab) => ({ id: String(lab.id), label: lab.label })),
        ]}
        selectedId={labId ? String(labId) : ''}
        title="Filter by Laboratory"
        visible={labModalOpen}
      />

      <SelectionModal
        onClose={() => setStatusModalOpen(false)}
        onSelect={setActive}
        options={[
          { id: '', label: 'All statuses' },
          { id: '1', label: 'Active only' },
          { id: '0', label: 'Inactive only' },
        ]}
        selectedId={active}
        title="Filter by Status"
        visible={statusModalOpen}
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
  title: {
    fontSize: 20,
    fontWeight: '800',
  },
  filterRow: {
    flexDirection: 'row',
    gap: 10,
  },
  filterButton: {
    borderRadius: 12,
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  filterText: {
    fontSize: 13,
    fontWeight: '700',
  },
  primaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 13,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '800',
  },
  serviceCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  serviceHeader: {
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
  },
  serviceHeaderMeta: {
    flex: 1,
    gap: 4,
  },
  serviceTitle: {
    fontSize: 17,
    fontWeight: '800',
  },
  metaText: {
    fontSize: 13,
    lineHeight: 18,
  },
  pill: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  pillText: {
    fontSize: 12,
    fontWeight: '800',
  },
  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  summaryPill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  summaryText: {
    fontSize: 12,
    fontWeight: '800',
  },
});
