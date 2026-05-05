import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { listAdminLabsRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { StatCard } from '../components/stat-card';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';

const PIC_FILTER_OPTIONS = [
  { id: '', label: 'All laboratories' },
  { id: 'assigned', label: 'PIC assigned' },
  { id: 'unassigned', label: 'PIC missing' },
];

export function AdminLabsScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const [query, setQuery] = useState('');
  const [picFilter, setPicFilter] = useState('');
  const [filterModalOpen, setFilterModalOpen] = useState(false);

  const labsQuery = useQuery({
    queryKey: ['admin-labs', query, picFilter],
    queryFn: () =>
      listAdminLabsRequest({
        q: query.trim() || undefined,
        pic: picFilter || undefined,
      }),
  });

  const picFilterLabel =
    PIC_FILTER_OPTIONS.find((option) => option.id === picFilter)?.label ?? 'All laboratories';

  if (labsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading laboratories..." />
      </Screen>
    );
  }

  if (labsQuery.isError || !labsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The laboratory workspace could not be loaded."
          onRetry={() => {
            void labsQuery.refetch();
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
        <Text style={[styles.title, { color: theme.colors.text }]}>Laboratory Management</Text>
        <TextField
          label="Search"
          onChangeText={setQuery}
          placeholder="Search by lab name, room, PIC name, or PIC email"
          value={query}
        />

        <View style={styles.filterRow}>
          <Pressable
            onPress={() => {
              setFilterModalOpen(true);
            }}
            style={[
              styles.filterButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]}>{picFilterLabel}</Text>
          </Pressable>
          <Pressable
            onPress={() => {
              navigation.navigate('AdminLabEditor', {});
            }}
            style={[
              styles.primaryButtonCompact,
              {
                backgroundColor: theme.colors.primary,
              },
            ]}
          >
            <Text style={styles.primaryButtonText}>Create Lab</Text>
          </Pressable>
        </View>
      </View>

      <View style={styles.statsRow}>
        <StatCard label="Labs" tone="primary" value={labsQuery.data.stats.total_labs} />
        <StatCard label="PIC Assigned" tone="success" value={labsQuery.data.stats.assigned_pic} />
        <StatCard label="PIC Missing" tone="warning" value={labsQuery.data.stats.unassigned_pic} />
      </View>

      {labsQuery.data.labs.length === 0 ? (
        <EmptyState title="No laboratories found" message="Adjust the filters or create a new laboratory." />
      ) : (
        labsQuery.data.labs.map((lab) => (
          <Pressable
            key={lab.id}
            onPress={() => {
              navigation.navigate('AdminLabEditor', { labId: lab.id });
            }}
            style={[
              styles.labCard,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.labHeader}>
              <View style={styles.labHeaderMeta}>
                <Text style={[styles.labTitle, { color: theme.colors.text }]}>{lab.name}</Text>
                <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
                  Room {lab.room || 'Not set'}  |  Capacity {lab.capacity || 0}
                </Text>
              </View>
              <View
                style={[
                  styles.pill,
                  {
                    backgroundColor: lab.pic_account_linked ? theme.colors.successSoft : theme.colors.warningSoft,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.pillText,
                    {
                      color: lab.pic_account_linked ? theme.colors.success : theme.colors.warning,
                    },
                  ]}
                >
                  {lab.pic_account_linked ? 'PIC Linked' : 'PIC Pending'}
                </Text>
              </View>
            </View>

            <Text style={[styles.metaText, { color: theme.colors.primary }]}>
              PIC: {lab.pic_name || 'No PIC name'} {lab.pic_email ? `| ${lab.pic_email}` : ''}
            </Text>
            {lab.description ? (
              <Text numberOfLines={3} style={[styles.metaText, { color: theme.colors.textMuted }]}>
                {lab.description}
              </Text>
            ) : null}

            <View style={styles.summaryRow}>
              <View
                style={[
                  styles.summaryPill,
                  {
                    backgroundColor: theme.colors.primarySoft,
                  },
                ]}
              >
                <Text style={[styles.summaryText, { color: theme.colors.primary }]}>
                  Assets {lab.asset_total}
                </Text>
              </View>
              <View
                style={[
                  styles.summaryPill,
                  {
                    backgroundColor: theme.colors.warningSoft,
                  },
                ]}
              >
                <Text style={[styles.summaryText, { color: theme.colors.warning }]}>
                  Maintenance {lab.assets_in_maintenance}
                </Text>
              </View>
              <View
                style={[
                  styles.summaryPill,
                  {
                    backgroundColor: theme.colors.dangerSoft,
                  },
                ]}
              >
                <Text style={[styles.summaryText, { color: theme.colors.danger }]}>
                  Faulty {lab.faulty_assets}
                </Text>
              </View>
            </View>

            {!lab.pic_email ? (
              <Text style={[styles.noticeText, { color: theme.colors.warning }]}>
                This lab has no PIC email. Approval routing will not work until a PIC is assigned.
              </Text>
            ) : !lab.pic_account_has_role ? (
              <Text style={[styles.noticeText, { color: theme.colors.warning }]}>
                The linked PIC account is missing the PIC role.
              </Text>
            ) : null}
          </Pressable>
        ))
      )}

      <SelectionModal
        onClose={() => {
          setFilterModalOpen(false);
        }}
        onSelect={setPicFilter}
        options={PIC_FILTER_OPTIONS}
        selectedId={picFilter}
        title="Filter Laboratories"
        visible={filterModalOpen}
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
  primaryButtonCompact: {
    alignItems: 'center',
    borderRadius: 12,
    justifyContent: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '800',
  },
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    justifyContent: 'space-between',
  },
  labCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  labHeader: {
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
  },
  labHeaderMeta: {
    flex: 1,
    gap: 4,
  },
  labTitle: {
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
  noticeText: {
    fontSize: 12,
    fontWeight: '700',
    lineHeight: 18,
  },
});
