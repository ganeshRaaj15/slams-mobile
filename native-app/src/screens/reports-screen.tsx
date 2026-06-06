import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { InteractionManager, Pressable, StyleSheet, Text, View } from 'react-native';

import { getReportSnapshotRequest } from '../api/endpoints';
import { AnimatedPageSection } from '../components/animated-page-section';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatCard } from '../components/stat-card';
import { StatusPill } from '../components/status-pill';
import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';
import { formatDateTimeRange } from '../utils/format';
import { openProtectedFile } from '../utils/protected-document';
import { readErrorMessage } from '../utils/error-message';

const KPI_TONES: Record<string, 'primary' | 'success' | 'warning' | 'danger' | 'accent' | 'neutral'> = {
  total_bookings: 'primary',
  approved: 'success',
  pending: 'warning',
  rejected: 'danger',
  cancelled: 'neutral',
  total_labs: 'accent',
  total_assets: 'primary',
  users: 'accent',
  maintenance_total: 'warning',
  maintenance_open: 'warning',
  maintenance_completed: 'success',
};

function formatRoleLabel(value: string) {
  return value
    .replace(/[_-]+/g, ' ')
    .toLowerCase()
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function ReportsScreen() {
  const theme = useAppTheme();
  const responsive = useResponsiveLayout();
  const [exportMessage, setExportMessage] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const [busyExport, setBusyExport] = useState<'pdf' | 'csv' | null>(null);
  const [heavyContentReady, setHeavyContentReady] = useState(false);

  const reportQuery = useQuery({
    queryKey: ['report-snapshot'],
    queryFn: getReportSnapshotRequest,
  });

  useEffect(() => {
    if (!reportQuery.data) {
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
  }, [reportQuery.data]);

  if (reportQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading report snapshot..." />
      </Screen>
    );
  }

  if (reportQuery.isError || !reportQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The report snapshot could not be loaded."
          onRetry={() => {
            void reportQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const { report, exports } = reportQuery.data;
  const kpiEntries = Object.entries(report.kpis).filter(([, value]) => value !== null);
  const roleLabel = formatRoleLabel(report.role);
  const reportTitle = report.reportTitle.replace(/\bADMIN\b/g, roleLabel);
  const scopeLabel = report.scopeLabel.replace(/\bADMIN\b/g, roleLabel);

  async function handleExport(kind: 'pdf' | 'csv') {
    setExportMessage(null);
    setExportError(null);
    setBusyExport(kind);

    try {
      const url = kind === 'pdf' ? exports.pdf_url : exports.csv_url;
      const filename = `slams-report-${report.role}.${kind}`;
      await openProtectedFile(url, filename, kind === 'pdf' ? 'application/pdf' : 'text/csv');
      setExportMessage(`${kind.toUpperCase()} export opened in an available viewer.`);
    } catch (error) {
      setExportError(readErrorMessage(error, `Unable to export the ${kind.toUpperCase()} report.`));
    } finally {
      setBusyExport(null);
    }
  }

  return (
    <Screen maxWidth="wide">
      <AnimatedPageSection index={0} variant="hero">
        <View
          style={[
            styles.heroCard,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.title, { color: theme.colors.text }]}>{reportTitle}</Text>
          <Text style={[styles.subtitle, { color: theme.colors.textMuted }]}>{scopeLabel}</Text>
          <Text style={[styles.meta, { color: theme.colors.textMuted }]}>Generated {report.generatedAt}</Text>

          <View style={[styles.exportRow, responsive.isWide ? styles.exportRowWide : null]}>
            <Pressable
              disabled={busyExport !== null}
              onPress={() => {
                void handleExport('pdf');
              }}
              style={[
                styles.exportButton,
                {
                  backgroundColor: theme.colors.primary,
                  opacity: busyExport !== null ? 0.7 : 1,
                },
              ]}
            >
              <Text style={styles.exportButtonText}>{busyExport === 'pdf' ? 'Preparing PDF...' : 'Export PDF'}</Text>
            </Pressable>
            <Pressable
              disabled={busyExport !== null}
              onPress={() => {
                void handleExport('csv');
              }}
              style={[
                styles.secondaryButton,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>
                {busyExport === 'csv' ? 'Preparing CSV...' : 'Export CSV'}
              </Text>
            </Pressable>
          </View>

          {exportMessage ? <Text style={[styles.feedback, { color: theme.colors.success }]}>{exportMessage}</Text> : null}
          {exportError ? <Text style={[styles.feedback, { color: theme.colors.danger }]}>{exportError}</Text> : null}
        </View>
      </AnimatedPageSection>

      {heavyContentReady ? (
        <>
          <View style={styles.statsRow}>
            {kpiEntries.map(([label, value], index) => (
              <AnimatedPageSection
                key={label}
                index={index + 1}
                variant="card"
                style={styles.statCardWrap}
              >
                <StatCard
                  label={label.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())}
                  tone={KPI_TONES[label] ?? 'neutral'}
                  value={Number(value ?? 0)}
                />
              </AnimatedPageSection>
            ))}
          </View>

          <View style={[styles.sectionGrid, responsive.isWide ? styles.sectionGridWide : null]}>
            <AnimatedPageSection index={6} variant="section" style={responsive.isWide ? styles.sectionCardWide : undefined}>
              <View
                style={[
                  styles.card,
                  {
                    backgroundColor: theme.colors.surface,
                    borderColor: theme.colors.border,
                  },
                ]}
              >
                <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Booking Status</Text>
                {Object.entries(report.statusMap).map(([status, total]) => (
                  <View key={status} style={styles.row}>
                    <StatusPill status={status} />
                    <Text style={[styles.rowValue, { color: theme.colors.text }]}>{total}</Text>
                  </View>
                ))}
              </View>
            </AnimatedPageSection>

            <AnimatedPageSection index={7} variant="section" style={responsive.isWide ? styles.sectionCardWide : undefined}>
              <View
                style={[
                  styles.card,
                  {
                    backgroundColor: theme.colors.surface,
                    borderColor: theme.colors.border,
                  },
                ]}
              >
                <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Maintenance Status</Text>
                {Object.entries(report.maintenanceStatus).map(([status, total]) => (
                  <View key={status} style={styles.row}>
                    <StatusPill kind="maintenance" status={status} />
                    <Text style={[styles.rowValue, { color: theme.colors.text }]}>{total}</Text>
                  </View>
                ))}
              </View>
            </AnimatedPageSection>
          </View>

          <AnimatedPageSection index={8} variant="section">
            <View
              style={[
                styles.card,
                {
                  backgroundColor: theme.colors.surface,
                  borderColor: theme.colors.border,
                },
              ]}
            >
              <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Top Laboratories</Text>
              {report.topLabs.length === 0 ? (
                <EmptyState title="No booking trend data" message="No laboratory booking totals are available yet." />
              ) : (
                report.topLabs.map((lab) => (
                  <View
                    key={`${lab.lab_name}-${lab.total}`}
                    style={[
                      styles.innerCard,
                      {
                        backgroundColor: theme.colors.surfaceMuted,
                      },
                    ]}
                  >
                    <Text style={[styles.innerTitle, { color: theme.colors.text }]}>{lab.lab_name || 'Unknown Lab'}</Text>
                    <Text style={[styles.innerMeta, { color: theme.colors.textMuted }]}>{lab.total} booking(s)</Text>
                  </View>
                ))
              )}
            </View>
          </AnimatedPageSection>

          {report.monthlyTrend && report.monthlyTrend.length > 0 ? (
            <AnimatedPageSection index={9} variant="section">
              <View
                style={[
                  styles.card,
                  {
                    backgroundColor: theme.colors.surface,
                    borderColor: theme.colors.border,
                  },
                ]}
              >
                <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Booking Trend</Text>
                {(() => {
                  const maxTotal = Math.max(...report.monthlyTrend.map((m) => m.total), 1);
                  return report.monthlyTrend.map((m) => (
                    <View key={m.month} style={styles.barRow}>
                      <Text style={[styles.barLabel, { color: theme.colors.textMuted }]}>{m.month}</Text>
                      <View style={styles.barTrack}>
                        <View
                          style={[
                            styles.barFill,
                            {
                              width: `${Math.round((m.total / maxTotal) * 100)}%` as const,
                              backgroundColor: theme.colors.primary,
                            },
                          ]}
                        />
                      </View>
                      <Text style={[styles.barValue, { color: theme.colors.text }]}>{m.total}</Text>
                    </View>
                  ));
                })()}
              </View>
            </AnimatedPageSection>
          ) : null}

          {report.peakHours && report.peakHours.length > 0 ? (
            <AnimatedPageSection index={10} variant="section">
              <View
                style={[
                  styles.card,
                  {
                    backgroundColor: theme.colors.surface,
                    borderColor: theme.colors.border,
                  },
                ]}
              >
                <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Peak Usage Hours</Text>
                {(() => {
                  const maxTotal = Math.max(...report.peakHours.map((p) => p.total), 1);
                  const peakColors = [
                    theme.colors.success,
                    theme.colors.primary,
                    theme.colors.warning,
                    theme.colors.danger,
                    theme.colors.textMuted,
                  ];

                  return report.peakHours.map((p, index) => (
                    <View key={p.time_slot} style={styles.barRow}>
                      <Text style={[styles.barLabel, { color: theme.colors.textMuted }]}>{p.time_slot}</Text>
                      <View style={styles.barTrack}>
                        <View
                          style={[
                            styles.barFill,
                            {
                              width: `${Math.round((p.total / maxTotal) * 100)}%` as const,
                              backgroundColor: peakColors[index % peakColors.length] ?? theme.colors.primary,
                            },
                          ]}
                        />
                      </View>
                      <Text style={[styles.barValue, { color: theme.colors.text }]}>{p.total}</Text>
                    </View>
                  ));
                })()}
              </View>
            </AnimatedPageSection>
          ) : null}

          {report.labUtilization && report.labUtilization.length > 0 ? (
            <View
              style={[
                styles.card,
                {
                  backgroundColor: theme.colors.surface,
                  borderColor: theme.colors.border,
                },
              ]}
            >
              <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Lab Utilization</Text>
              {report.labUtilization.map((lab) => (
                <View
                  key={lab.laboratory_name}
                  style={[
                    styles.innerCard,
                    {
                      backgroundColor: theme.colors.surfaceMuted,
                    },
                  ]}
                >
                  <View style={styles.utilizationHeader}>
                    <Text style={[styles.innerTitle, { color: theme.colors.text }]}>{lab.laboratory_name}</Text>
                    <Text style={[styles.utilizationPct, { color: theme.colors.primary }]}>
                      {lab.usage_percentage}%
                    </Text>
                  </View>
                  <View style={styles.barTrack}>
                    <View
                      style={[
                        styles.barFill,
                        {
                          width: `${Math.min(lab.usage_percentage, 100)}%` as const,
                          backgroundColor: lab.usage_percentage >= 75
                            ? theme.colors.danger
                            : lab.usage_percentage >= 40
                              ? theme.colors.warning
                              : theme.colors.success,
                        },
                      ]}
                    />
                  </View>
                  <Text style={[styles.innerMeta, { color: theme.colors.textMuted }]}>
                    {lab.total_bookings} booking(s) | {lab.total_used_hours}h used | Peak: {lab.peak_usage_day}, {lab.peak_usage_time}
                  </Text>
                </View>
              ))}
            </View>
          ) : null}

          {report.maintenanceTrend && report.maintenanceTrend.length > 0 ? (
            <View
              style={[
                styles.card,
                {
                  backgroundColor: theme.colors.surface,
                  borderColor: theme.colors.border,
                },
              ]}
            >
              <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Maintenance Trend</Text>
              {(() => {
                const maxTotal = Math.max(...report.maintenanceTrend.map((m) => m.total), 1);
                return report.maintenanceTrend.map((m) => (
                  <View key={m.month} style={styles.barRow}>
                    <Text style={[styles.barLabel, { color: theme.colors.textMuted }]}>{m.month}</Text>
                    <View style={styles.barTrack}>
                      <View
                        style={[
                          styles.barFill,
                          {
                            width: `${Math.round((m.total / maxTotal) * 100)}%` as const,
                            backgroundColor: theme.colors.danger,
                          },
                        ]}
                      />
                    </View>
                    <Text style={[styles.barValue, { color: theme.colors.text }]}>{m.total}</Text>
                  </View>
                ));
              })()}
            </View>
          ) : null}

          <View
            style={[
              styles.card,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Upcoming Activity</Text>
            {report.upcomingBookings.length === 0 ? (
              <EmptyState title="No upcoming activity" message="No approved or pending booking activity is scheduled yet." />
            ) : (
              report.upcomingBookings.map((booking, index) => (
                <View
                  key={`${booking.lab_name}-${booking.date}-${index}`}
                  style={[
                    styles.innerCard,
                    {
                      backgroundColor: theme.colors.surfaceMuted,
                    },
                  ]}
                >
                  <Text style={[styles.innerTitle, { color: theme.colors.text }]}>{booking.lab_name}</Text>
                  <Text style={[styles.innerMeta, { color: theme.colors.textMuted }]}>
                    {formatDateTimeRange(booking.date, booking.start_time, booking.end_time)}
                  </Text>
                  <StatusPill status={booking.status} />
                </View>
              ))
            )}
          </View>
        </>
      ) : (
        <AnimatedPageSection index={1} variant="section">
          <View
            style={[
              styles.pendingCard,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.pendingText, { color: theme.colors.textMuted }]}>
              Preparing charts and report sections...
            </Text>
          </View>
        </AnimatedPageSection>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  heroCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 8,
    padding: 16,
  },
  title: {
    fontSize: 22,
    fontWeight: '800',
  },
  subtitle: {
    fontSize: 14,
  },
  meta: {
    fontSize: 13,
  },
  exportRow: {
    gap: 10,
    marginTop: 6,
  },
  exportRowWide: {
    flexDirection: 'row',
  },
  exportButton: {
    alignItems: 'center',
    borderRadius: 12,
    flex: 1,
    paddingVertical: 12,
  },
  exportButtonText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '800',
  },
  secondaryButton: {
    alignItems: 'center',
    borderRadius: 12,
    flex: 1,
    paddingVertical: 12,
  },
  secondaryButtonText: {
    fontSize: 13,
    fontWeight: '800',
  },
  feedback: {
    fontSize: 13,
    lineHeight: 18,
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
    borderRadius: 18,
    borderWidth: 1,
    padding: 16,
  },
  pendingText: {
    fontSize: 13,
    fontWeight: '700',
  },
  sectionGrid: {
    gap: 18,
  },
  sectionGridWide: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  sectionCardWide: {
    minWidth: '48%',
    width: '48%',
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  row: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  rowValue: {
    fontSize: 15,
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
  innerMeta: {
    fontSize: 13,
    lineHeight: 18,
  },
  barRow: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  barLabel: {
    fontSize: 12,
    fontWeight: '600',
    minWidth: 88,
  },
  barTrack: {
    backgroundColor: 'rgba(0,0,0,0.07)',
    borderRadius: 6,
    flex: 1,
    height: 10,
    overflow: 'hidden',
  },
  barFill: {
    borderRadius: 6,
    height: '100%',
  },
  barValue: {
    fontSize: 12,
    fontWeight: '800',
    minWidth: 28,
    textAlign: 'right',
  },
  utilizationHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  utilizationPct: {
    fontSize: 15,
    fontWeight: '800',
  },
});
