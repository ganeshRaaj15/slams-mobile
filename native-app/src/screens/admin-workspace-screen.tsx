import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { useEffect, useState } from 'react';
import { InteractionManager, Pressable, StyleSheet, Text, View } from 'react-native';

import {
  getAdminSettingsRequest,
  getReportSnapshotRequest,
  listAdminAssetsRequest,
  listAdminLabsRequest,
  listAdminUsersRequest,
} from '../api/endpoints';
import { AnimatedPageSection } from '../components/animated-page-section';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatCard } from '../components/stat-card';
import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';

export function AdminWorkspaceScreen() {
  const theme = useAppTheme();
  const responsive = useResponsiveLayout();
  const navigation = useNavigation<any>();
  const [heavyContentReady, setHeavyContentReady] = useState(false);

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
  const assetsQuery = useQuery({
    queryKey: ['admin-assets', 'summary'],
    queryFn: () => listAdminAssetsRequest(),
  });

  useEffect(() => {
    if (
      !usersQuery.data ||
      !settingsQuery.data ||
      !labsQuery.data ||
      !reportQuery.data ||
      !assetsQuery.data
    ) {
      setHeavyContentReady(false);
      return;
    }

    setHeavyContentReady(false);
    const task = InteractionManager.runAfterInteractions(() => {
      setHeavyContentReady(true);
    });

    return () => {
      task.cancel();
    };
  }, [assetsQuery.data, labsQuery.data, reportQuery.data, settingsQuery.data, usersQuery.data]);

  if (usersQuery.isLoading || settingsQuery.isLoading || labsQuery.isLoading || reportQuery.isLoading || assetsQuery.isLoading) {
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
    assetsQuery.isError ||
    !usersQuery.data ||
    !settingsQuery.data ||
    !labsQuery.data ||
    !reportQuery.data ||
    !assetsQuery.data
  ) {
    return (
      <Screen maxWidth="wide">
        <ErrorState
          message="The admin workspace could not be loaded."
          onRetry={() => {
            void usersQuery.refetch();
            void settingsQuery.refetch();
            void labsQuery.refetch();
            void reportQuery.refetch();
            void assetsQuery.refetch();
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
  const assetStats = assetsQuery.data.stats;
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
    <Screen maxWidth="wide">
      <AnimatedPageSection index={0} variant="hero">
        {heavyContentReady ? (
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
              Manage users, laboratories, assets, settings, and reports from one workspace with predictive asset risk visibility.
            </Text>
          </View>
        ) : (
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
              Manage users, laboratories, assets, settings, and reports from one workspace with predictive asset risk visibility.
            </Text>
          </View>
        )}
      </AnimatedPageSection>

      <View style={styles.statsRow}>
        {heavyContentReady ? (
          <>
            <AnimatedPageSection index={1} variant="card" style={styles.statCardWrap}>
              <StatCard label="Users" tone="primary" value={userStats.total} />
            </AnimatedPageSection>
            <AnimatedPageSection index={2} variant="card" style={styles.statCardWrap}>
              <StatCard label="Active Users" tone="success" value={userStats.active} />
            </AnimatedPageSection>
            <AnimatedPageSection index={3} variant="card" style={styles.statCardWrap}>
              <StatCard label="Labs" tone="accent" value={labStats.total_labs} />
            </AnimatedPageSection>
            <AnimatedPageSection index={4} variant="card" style={styles.statCardWrap}>
              <StatCard label="Assets" tone="warning" value={Number(report.kpis.total_assets ?? 0)} />
            </AnimatedPageSection>
            <AnimatedPageSection index={5} variant="card" style={styles.statCardWrap}>
              <StatCard label="High Risk" tone="danger" value={assetStats.high_risk} />
            </AnimatedPageSection>
            <AnimatedPageSection index={6} variant="card" style={styles.statCardWrap}>
              <StatCard label="Due Soon" tone="accent" value={assetStats.due_soon} />
            </AnimatedPageSection>
            <AnimatedPageSection index={7} variant="card" style={styles.statCardWrap}>
              <StatCard label="Settings" tone="accent" value={managedSettingsCount} />
            </AnimatedPageSection>
            <AnimatedPageSection index={8} variant="card" style={styles.statCardWrap}>
              <StatCard label="Booking Slots" tone="warning" value={bookingSlotsCount} />
            </AnimatedPageSection>
          </>
        ) : (
          <AnimatedPageSection index={1} variant="section" style={styles.pendingCard}>
            <View
              style={[
                styles.pendingCardInner,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                  borderColor: theme.colors.border,
                },
              ]}
            >
              <Text style={[styles.pendingText, { color: theme.colors.textMuted }]}>
                Preparing workspace cards...
              </Text>
            </View>
          </AnimatedPageSection>
        )}
      </View>

      {heavyContentReady ? <View style={styles.actionsGrid}>
        <AnimatedPageSection index={9} variant="section" style={responsive.isTabletLandscape ? styles.cardButtonWide : undefined}>
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
        </AnimatedPageSection>

        <AnimatedPageSection index={10} variant="section" style={responsive.isTabletLandscape ? styles.cardButtonWide : undefined}>
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
        </AnimatedPageSection>

        <AnimatedPageSection index={11} variant="section" style={responsive.isTabletLandscape ? styles.cardButtonWide : undefined}>
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
              Maintain inventory, quantities, serials, images, maintenance traceability, and predictive maintenance risk.
            </Text>
          </Pressable>
        </AnimatedPageSection>

        <AnimatedPageSection index={12} variant="section" style={responsive.isTabletLandscape ? styles.cardButtonWide : undefined}>
          <Pressable
            onPress={() => {
              navigation.navigate('Reservations');
            }}
            style={[
              styles.cardButton,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.cardTitle, { color: theme.colors.text }]}>Lab Reservations</Text>
            <Text style={[styles.cardText, { color: theme.colors.textMuted }]}>
              Manage one-off and recurring time blocks that prevent student bookings during classes and events.
            </Text>
          </Pressable>
        </AnimatedPageSection>

        <AnimatedPageSection index={13} variant="section" style={responsive.isTabletLandscape ? styles.cardButtonWide : undefined}>
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
        </AnimatedPageSection>

        <AnimatedPageSection index={14} variant="section" style={responsive.isTabletLandscape ? styles.cardButtonWide : undefined}>
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
        </AnimatedPageSection>
      </View> : null}
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
  statCardWrap: {
    width: '47%',
  },
  pendingCard: {
    width: '100%',
  },
  pendingCardInner: {
    borderRadius: 18,
    borderWidth: 1,
    padding: 16,
  },
  pendingText: {
    fontSize: 13,
    fontWeight: '700',
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 14,
  },
  cardButton: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 6,
    padding: 16,
    width: '100%',
  },
  cardButtonWide: {
    width: '48.8%',
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
