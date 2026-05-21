import { useQuery } from '@tanstack/react-query';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { getReportSnapshotRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatCard } from '../components/stat-card';
import { StatusPill } from '../components/status-pill';
import { useAppTheme } from '../theme/use-app-theme';
import { openProtectedFile } from '../utils/protected-document';
import { formatDateTimeRange } from '../utils/format';
import { readErrorMessage } from '../utils/error-message';
import { useState } from 'react';

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

export function ReportsScreen() {
  const theme = useAppTheme();
  const [exportMessage, setExportMessage] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const [busyExport, setBusyExport] = useState<'pdf' | 'excel' | 'csv' | null>(null);
  const [exportFormat, setExportFormat] = useState<'pdf' | 'excel' | 'csv'>('pdf');

  const reportQuery = useQuery({
    queryKey: ['report-snapshot'],
    queryFn: getReportSnapshotRequest,
  });

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

  async function handleExport(kind: 'pdf' | 'excel' | 'csv') {
    setExportMessage(null);
    setExportError(null);
    setBusyExport(kind);

    try {
      const urlMap = { pdf: exports.pdf_url, excel: exports.excel_url, csv: exports.csv_url };
      const mimeMap = {
        pdf: 'application/pdf',
        excel: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        csv: 'text/csv',
      };
      const ext = kind === 'excel' ? 'xlsx' : kind;
      const url = urlMap[kind];
      const filename = `slams-report-${report.role}.${ext}`;
      await openProtectedFile(url, filename, mimeMap[kind]);
      setExportMessage(`${kind.toUpperCase()} export prepared.`);
    } catch (error) {
      setExportError(readErrorMessage(error, `Unable to export the ${kind.toUpperCase()} report.`));
    } finally {
      setBusyExport(null);
    }
  }

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
        <Text style={[styles.title, { color: theme.colors.text }]}>{report.reportTitle}</Text>
        <Text style={[styles.subtitle, { color: theme.colors.textMuted }]}>{report.scopeLabel}</Text>
        <Text style={[styles.meta, { color: theme.colors.textMuted }]}>Generated {report.generatedAt}</Text>

        <Text style={[styles.exportLabel, { color: theme.colors.textMuted }]}>Export report as</Text>
        <View style={styles.exportRow}>
          {(['pdf', 'excel', 'csv'] as const).map((fmt) => (
            <Pressable
              key={fmt}
              onPress={() => { setExportFormat(fmt); }}
              style={[
                styles.formatChip,
                {
                  backgroundColor: exportFormat === fmt ? theme.colors.primary : theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text
                style={[
                  styles.formatChipText,
                  { color: exportFormat === fmt ? '#ffffff' : theme.colors.textMuted },
                ]}
              >
                {fmt === 'excel' ? 'Excel' : fmt.toUpperCase()}
              </Text>
            </Pressable>
          ))}
        </View>
        <Pressable
          disabled={busyExport !== null}
          onPress={() => { void handleExport(exportFormat); }}
          style={[
            styles.exportButton,
            {
              backgroundColor: theme.colors.primary,
              opacity: busyExport !== null ? 0.7 : 1,
            },
          ]}
        >
          <Text style={styles.exportButtonText}>
            {busyExport !== null ? `Preparing ${busyExport.toUpperCase()}...` : 'Export'}
          </Text>
        </Pressable>

        {exportMessage ? <Text style={[styles.feedback, { color: theme.colors.success }]}>{exportMessage}</Text> : null}
        {exportError ? <Text style={[styles.feedback, { color: theme.colors.danger }]}>{exportError}</Text> : null}
      </View>

      <View style={styles.statsRow}>
        {kpiEntries.map(([label, value]) => (
          <StatCard
            key={label}
            label={label.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())}
            tone={KPI_TONES[label] ?? 'neutral'}
            value={Number(value ?? 0)}
          />
        ))}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Booking Status</Text>
        {Object.entries(report.statusMap).map(([status, total]) => (
          <View key={status} style={styles.row}>
            <StatusPill status={status} />
            <Text style={[styles.rowValue, { color: theme.colors.text }]}>{total}</Text>
          </View>
        ))}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Maintenance Status</Text>
        {Object.entries(report.maintenanceStatus).map(([status, total]) => (
          <View key={status} style={styles.row}>
            <StatusPill kind="maintenance" status={status} />
            <Text style={[styles.rowValue, { color: theme.colors.text }]}>{total}</Text>
          </View>
        ))}
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

      {report.monthlyTrend && report.monthlyTrend.length > 0 ? (
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
                        width: `${Math.round((m.total / maxTotal) * 100)}%` as any,
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
      ) : null}

      {report.peakHours && report.peakHours.length > 0 ? (
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
            return report.peakHours.map((p, i) => (
              <View key={p.time_slot} style={styles.barRow}>
                <Text style={[styles.barLabel, { color: theme.colors.textMuted }]}>{p.time_slot}</Text>
                <View style={styles.barTrack}>
                  <View
                    style={[
                      styles.barFill,
                      {
                        width: `${Math.round((p.total / maxTotal) * 100)}%` as any,
                        backgroundColor: peakColors[i % peakColors.length] ?? theme.colors.primary,
                      },
                    ]}
                  />
                </View>
                <Text style={[styles.barValue, { color: theme.colors.text }]}>{p.total}</Text>
              </View>
            ));
          })()}
        </View>
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
              style={[styles.innerCard, { backgroundColor: theme.colors.surfaceMuted }]}
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
                      width: `${Math.min(lab.usage_percentage, 100)}%` as any,
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
                {lab.total_bookings} booking(s)  ·  {lab.total_used_hours}h used  ·  Peak: {lab.peak_usage_day}, {lab.peak_usage_time}
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
                        width: `${Math.round((m.total / maxTotal) * 100)}%` as any,
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
  exportLabel: {
    fontSize: 12,
    fontWeight: '600',
    marginTop: 6,
  },
  exportRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 6,
  },
  formatChip: {
    borderRadius: 20,
    flex: 1,
    alignItems: 'center',
    paddingVertical: 8,
  },
  formatChipText: {
    fontSize: 12,
    fontWeight: '700',
  },
  exportButton: {
    alignItems: 'center',
    borderRadius: 12,
    paddingVertical: 12,
    marginTop: 6,
  },
  exportButtonText: {
    color: '#ffffff',
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
