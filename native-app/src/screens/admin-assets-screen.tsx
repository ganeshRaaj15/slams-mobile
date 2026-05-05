import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { listAdminAssetsRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { StatusPill } from '../components/status-pill';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';
import { formatDateLabel } from '../utils/format';

export function AdminAssetsScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const [query, setQuery] = useState('');
  const [labId, setLabId] = useState<number>(0);
  const [status, setStatus] = useState('');
  const [labModalOpen, setLabModalOpen] = useState(false);
  const [statusModalOpen, setStatusModalOpen] = useState(false);

  const assetsQuery = useQuery({
    queryKey: ['admin-assets', query, labId, status],
    queryFn: () =>
      listAdminAssetsRequest({
        q: query.trim() || undefined,
        lab_id: labId > 0 ? labId : undefined,
        status: status || undefined,
      }),
  });

  const selectedLabLabel = useMemo(() => {
    if (!labId) {
      return 'All laboratories';
    }

    return assetsQuery.data?.labs.find((lab) => lab.id === labId)?.label ?? 'All laboratories';
  }, [assetsQuery.data?.labs, labId]);
  const selectedStatusLabel = useMemo(() => {
    if (!status) {
      return 'All statuses';
    }

    return status.replace('_', ' ').replace(/\b\w/g, (value) => value.toUpperCase());
  }, [status]);

  if (assetsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading assets..." />
      </Screen>
    );
  }

  if (assetsQuery.isError || !assetsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The asset workspace could not be loaded."
          onRetry={() => {
            void assetsQuery.refetch();
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
        <Text style={[styles.title, { color: theme.colors.text }]}>Asset Management</Text>
        <TextField
          label="Search"
          onChangeText={setQuery}
          placeholder="Search by asset name, code, serial number, model, or lab"
          value={query}
        />

        <View style={styles.filterRow}>
          <Pressable
            onPress={() => {
              setLabModalOpen(true);
            }}
            style={[
              styles.filterButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]} numberOfLines={1}>
              {selectedLabLabel}
            </Text>
          </Pressable>
          <Pressable
            onPress={() => {
              setStatusModalOpen(true);
            }}
            style={[
              styles.filterButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]}>{selectedStatusLabel}</Text>
          </Pressable>
        </View>

        <Pressable
          onPress={() => {
            navigation.navigate('AdminAssetEditor', {});
          }}
          style={[
            styles.primaryButton,
            {
              backgroundColor: theme.colors.primary,
            },
          ]}
        >
          <Text style={styles.primaryButtonText}>Create Asset</Text>
        </Pressable>
      </View>

      {assetsQuery.data.assets.length === 0 ? (
        <EmptyState title="No assets found" message="Adjust the filters or create a new asset record." />
      ) : (
        assetsQuery.data.assets.map((asset) => (
          <Pressable
            key={asset.id}
            onPress={() => {
              navigation.navigate('AdminAssetEditor', { assetId: asset.id });
            }}
            style={[
              styles.assetCard,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.assetHeader}>
              <View style={styles.assetHeaderMeta}>
                <Text style={[styles.assetTitle, { color: theme.colors.text }]}>{asset.name}</Text>
                <Text style={[styles.metaText, { color: theme.colors.primary }]}>
                  {asset.asset_code}  |  {asset.lab_name || 'No lab'} {asset.lab_room ? `| ${asset.lab_room}` : ''}
                </Text>
              </View>
              <StatusPill kind="asset" status={asset.status} />
            </View>

            <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
              {asset.category || 'General Equipment'} {asset.brand ? `| ${asset.brand}` : ''}{' '}
              {asset.model ? `| ${asset.model}` : ''}
            </Text>
            <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
              Available {asset.quantity}/{asset.total_quantity}  |  Under maintenance {asset.maintenance_quantity}
            </Text>

            <View style={styles.summaryRow}>
              <View
                style={[
                  styles.summaryPill,
                  {
                    backgroundColor: theme.colors.warningSoft,
                  },
                ]}
              >
                <Text style={[styles.summaryText, { color: theme.colors.warning }]}>
                  Cases {asset.maintenance_total}
                </Text>
              </View>
              <View
                style={[
                  styles.summaryPill,
                  {
                    backgroundColor: theme.colors.primarySoft,
                  },
                ]}
              >
                <Text style={[styles.summaryText, { color: theme.colors.primary }]}>
                  Open {asset.maintenance_open}
                </Text>
              </View>
            </View>

            {asset.last_reported_at ? (
              <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
                Last reported {formatDateLabel(asset.last_reported_at)}
              </Text>
            ) : null}
          </Pressable>
        ))
      )}

      <SelectionModal
        onClose={() => {
          setLabModalOpen(false);
        }}
        onSelect={(value) => {
          setLabId(value ? Number(value) : 0);
        }}
        options={[
          { id: '', label: 'All laboratories' },
          ...assetsQuery.data.labs.map((lab) => ({
            id: String(lab.id),
            label: lab.label,
          })),
        ]}
        selectedId={labId ? String(labId) : ''}
        title="Filter by Laboratory"
        visible={labModalOpen}
      />

      <SelectionModal
        onClose={() => {
          setStatusModalOpen(false);
        }}
        onSelect={setStatus}
        options={[
          { id: '', label: 'All statuses' },
          ...assetsQuery.data.status_options.map((item) => ({
            id: item,
            label: item.replace('_', ' ').replace(/\b\w/g, (value) => value.toUpperCase()),
          })),
        ]}
        selectedId={status}
        title="Filter by Asset Status"
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
  assetCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  assetHeader: {
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
  },
  assetHeaderMeta: {
    flex: 1,
    gap: 4,
  },
  assetTitle: {
    fontSize: 17,
    fontWeight: '800',
  },
  metaText: {
    fontSize: 13,
    lineHeight: 18,
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
