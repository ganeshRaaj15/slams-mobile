import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { getAdminSettingsRequest, getReportSnapshotRequest, listAdminLabsRequest, listAdminUsersRequest } from '../api/endpoints';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatCard } from '../components/stat-card';
import { useAppTheme } from '../theme/use-app-theme';

export function AdminWorkspaceScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();

  const usersQuery = useQuery({
    queryKey: ['admin-users', 'summary'],
    queryFn: () => listAdminUsersRequest({ per_page: 10, page: 1 }),
  });

  const settingsQuery = useQuery({
    queryKey: ['admin-settings'],
    queryFn: getAdminSettingsRequest,
  });

  const labsQuery = useQuery({
    queryKey: ['admin-labs', 'summary'],
    queryFn: () => listAdminLabsRequest(),
  });

  const reportQuery = useQuery({
    queryKey: ['report-snapshot', 'admin-workspace'],
    queryFn: getReportSnapshotRequest,
  });

  if (usersQuery.isLoading || settingsQuery.isLoading || labsQuery.isLoading || reportQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading admin workspace..." />
      </Screen>
    );
  }

  if (
    usersQuery.isError ||
    settingsQuery.isError ||
    labsQuery.isError ||
    reportQuery.isError ||
    !usersQuery.data ||
    !settingsQuery.data ||
    !labsQuery.data ||
    !reportQuery.data
  ) {
    return (
      <Screen>
        <ErrorState
          message="The admin workspace could not be loaded."
          onRetry={() => {
            void usersQuery.refetch();
            void settingsQuery.refetch();
            void labsQuery.refetch();
            void reportQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const users = usersQuery.data.users ?? [];
  const labs = labsQuery.data.labs ?? [];
  const managedSettingsCount = Object.keys(settingsQuery.data.settings ?? {}).length;
  const bookingSlotsCount = settingsQuery.data.booking_slots?.length ?? 0;
  const report = reportQuery.data.report;
  const userStats = usersQuery.data.stats ?? {
    total: users.length,
    active: users.filter((record) => record.active).length,
  };
  const labStats = labsQuery.data.stats ?? {
    total_labs: labs.length,
    assigned_pic: labs.filter((record) => record.pic_email.trim() !== '').length,
    unassigned_pic: labs.filter((record) => record.pic_email.trim() === '').length,
  };

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
        <Text style={[styles.title, { color: theme.colors.text }]}>Admin Workspace</Text>
        <Text style={[styles.subtitle, { color: theme.colors.textMuted }]}>
          Manage users, laboratories, assets, settings, and reports from one admin workspace.
        </Text>
      </View>

      <View style={styles.statsRow}>
        <StatCard label="Users" tone="primary" value={userStats.total} />
        <StatCard label="Active Users" tone="success" value={userStats.active} />
        <StatCard label="Labs" tone="accent" value={labStats.total_labs} />
        <StatCard label="Assets" tone="warning" value={Number(report.kpis.total_assets ?? 0)} />
        <StatCard label="Settings" tone="accent" value={managedSettingsCount} />
        <StatCard label="Booking Slots" tone="warning" value={bookingSlotsCount} />
      </View>

      <Pressable
        onPress={() => {
          navigation.navigate('AdminUsers');
        }}
        style={[
          styles.cardButton,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.cardTitle, { color: theme.colors.text }]}>Manage Users</Text>
        <Text style={[styles.cardText, { color: theme.colors.textMuted }]}>
          Search users, edit roles, trigger recovery links, and remove obsolete accounts.
        </Text>
      </Pressable>

      <Pressable
        onPress={() => {
          navigation.navigate('AdminLabs');
        }}
        style={[
          styles.cardButton,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.cardTitle, { color: theme.colors.text }]}>Manage Laboratories</Text>
        <Text style={[styles.cardText, { color: theme.colors.textMuted }]}>
          Update rooms, PIC assignments, images, capacity, and approval-routing readiness.
        </Text>
      </Pressable>

      <Pressable
        onPress={() => {
          navigation.navigate('AdminAssets');
        }}
        style={[
          styles.cardButton,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.cardTitle, { color: theme.colors.text }]}>Manage Assets</Text>
        <Text style={[styles.cardText, { color: theme.colors.textMuted }]}>
          Maintain inventory, quantities, serials, images, and maintenance traceability.
        </Text>
      </Pressable>

      <Pressable
        onPress={() => {
          navigation.navigate('AdminSettings');
        }}
        style={[
          styles.cardButton,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.cardTitle, { color: theme.colors.text }]}>System Settings</Text>
        <Text style={[styles.cardText, { color: theme.colors.textMuted }]}>
          Maintain booking routing, reminders, email settings, and booking slots.
        </Text>
      </Pressable>

      <Pressable
        onPress={() => {
          navigation.navigate('Reports');
        }}
        style={[
          styles.cardButton,
          {
            backgroundColor: theme.colors.primarySoft,
          },
        ]}
      >
        <Text style={[styles.cardTitle, { color: theme.colors.primary }]}>Open Reports</Text>
        <Text style={[styles.cardText, { color: theme.colors.text }]}>
          Review analytics and export PDF or CSV reports for operational reporting.
        </Text>
      </Pressable>
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
  title: {
    fontSize: 22,
    fontWeight: '800',
  },
  subtitle: {
    fontSize: 14,
    lineHeight: 20,
  },
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    justifyContent: 'space-between',
  },
  cardButton: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 6,
    padding: 16,
  },
  cardTitle: {
    fontSize: 17,
    fontWeight: '800',
  },
  cardText: {
    fontSize: 14,
    lineHeight: 20,
  },
});
