import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { getReportSnapshotRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatCard } from '../components/stat-card';
import { StatusPill } from '../components/status-pill';
import type { ReportSnapshot, ToneKey } from '../types/api';
import { useAppTheme } from '../theme/use-app-theme';
import { openProtectedPdf } from '../utils/protected-document';
import { formatDateTimeRange } from '../utils/format';
import { readErrorMessage } from '../utils/error-message';

type ReportFilterState = {
  date_from: string;
  date_to: string;
  lab_id: string;
  asset_id: string;
  booking_status: string;
  maintenance_status: string;
  asset_category: string;
  asset_status: string;
};

const EMPTY_FILTERS: ReportFilterState = {
  date_from: '',
  date_to: '',
  lab_id: '',
  asset_id: '',
  booking_status: '',
  maintenance_status: '',
  asset_category: '',
  asset_status: '',
};

const KPI_TONES: Record<string, ToneKey> = {
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
  approval_rate: 'success',
  asset_availability_rate: 'success',
  notifications_total: 'accent',
};

function normalizeFilters(filters?: ReportSnapshot['filters']): ReportFilterState {
  return {
    date_from: filters?.date_from ?? '',
    date_to: filters?.date_to ?? '',
    lab_id: filters?.lab_id ?? '',
    asset_id: filters?.asset_id ?? '',
    booking_status: filters?.booking_status ?? '',
    maintenance_status: filters?.maintenance_status ?? '',
    asset_category: filters?.asset_category ?? '',
    asset_status: filters?.asset_status ?? '',
  };
}

function activeFilterCount(filters: ReportFilterState) {
  return Object.values(filters).filter((value) => value.trim() !== '').length;
}

type OptionChipRowProps = {
  label: string;
  options: Array<{ value: string; label: string }>;
  selected: string;
  onSelect: (value: string) => void;
  theme: ReturnType<typeof useAppTheme>;
};

function OptionChipRow({ label, options, selected, onSelect, theme }: OptionChipRowProps) {
  return (
    <View style={styles.filterGroup}>
      <Text style={[styles.filterLabel, { color: theme.colors.text }]}>{label}</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
        <Pressable
          onPress={() => onSelect('')}
          style={[
            styles.filterChip,
            {
              backgroundColor: selected === '' ? theme.colors.primary : theme.colors.surface,
              borderColor: selected === '' ? theme.colors.primary : theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.filterChipText, { color: selected === '' ? '#ffffff' : theme.colors.text }]}>All</Text>
        </Pressable>
        {options.map((option) => (
          <Pressable
            key={`${label}-${option.value}`}
            onPress={() => onSelect(option.value)}
            style={[
              styles.filterChip,
              {
                backgroundColor: selected === option.value ? theme.colors.primary : theme.colors.surface,
                borderColor: selected === option.value ? theme.colors.primary : theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.filterChipText, { color: selected === option.value ? '#ffffff' : theme.colors.text }]}>
              {option.label}
            </Text>
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

function BookingStatusCard({ report, theme }: { report: ReportSnapshot; theme: ReturnType<typeof useAppTheme> }) {
  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Booking Status</Text>
      {Object.entries(report.statusMap).map(([status, total]) => (
        <View key={status} style={styles.row}>
          <StatusPill status={status} />
          <Text style={[styles.rowValue, { color: theme.colors.text }]}>{total}</Text>
        </View>
      ))}
    </View>
  );
}

function MaintenanceStatusCard({ report, theme }: { report: ReportSnapshot; theme: ReturnType<typeof useAppTheme> }) {
  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Maintenance Status</Text>
      {Object.entries(report.maintenanceStatus).map(([status, total]) => (
        <View key={status} style={styles.row}>
          <StatusPill kind="maintenance" status={status} />
          <Text style={[styles.rowValue, { color: theme.colors.text }]}>{total}</Text>
        </View>
      ))}
    </View>
  );
}

function TopLabsCard({
  report,
  theme,
  title,
}: {
  report: ReportSnapshot;
  theme: ReturnType<typeof useAppTheme>;
  title: string;
}) {
  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>{title}</Text>
      {report.topLabs.length === 0 ? (
        <EmptyState title="No booking trend data" message="No laboratory booking totals are available yet." />
      ) : (
        report.topLabs.map((lab) => (
          <View
            key={`${lab.lab_name}-${lab.total}`}
            style={[styles.innerCard, { backgroundColor: theme.colors.surfaceMuted }]}
          >
            <Text style={[styles.innerTitle, { color: theme.colors.text }]}>{lab.lab_name || 'Unknown Lab'}</Text>
            <Text style={[styles.innerMeta, { color: theme.colors.textMuted }]}>{lab.total} booking(s)</Text>
          </View>
        ))
      )}
    </View>
  );
}

function TrendBarsCard({
  title,
  rows,
  theme,
  color,
}: {
  title: string;
  rows: Array<{ month: string; total: number }>;
  theme: ReturnType<typeof useAppTheme>;
  color: string;
}) {
  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>{title}</Text>
      {(() => {
        const maxTotal = Math.max(...rows.map((row) => row.total), 1);
        return rows.map((row) => (
          <View key={row.month} style={styles.barRow}>
            <Text style={[styles.barLabel, { color: theme.colors.textMuted }]}>{row.month}</Text>
            <View style={styles.barTrack}>
              <View
                style={[
                  styles.barFill,
                  {
                    width: `${Math.round((row.total / maxTotal) * 100)}%` as any,
                    backgroundColor: color,
                  },
                ]}
              />
            </View>
            <Text style={[styles.barValue, { color: theme.colors.text }]}>{row.total}</Text>
          </View>
        ));
      })()}
    </View>
  );
}

function PeakHoursCard({ report, theme }: { report: ReportSnapshot; theme: ReturnType<typeof useAppTheme> }) {
  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Peak Usage Hours</Text>
      {(() => {
        const maxTotal = Math.max(...report.peakHours.map((row) => row.total), 1);
        const peakColors = [
          theme.colors.success,
          theme.colors.primary,
          theme.colors.warning,
          theme.colors.danger,
          theme.colors.textMuted,
        ];
        return report.peakHours.map((row, index) => (
          <View key={row.time_slot} style={styles.barRow}>
            <Text style={[styles.barLabel, { color: theme.colors.textMuted }]}>{row.time_slot}</Text>
            <View style={styles.barTrack}>
              <View
                style={[
                  styles.barFill,
                  {
                    width: `${Math.round((row.total / maxTotal) * 100)}%` as any,
                    backgroundColor: peakColors[index % peakColors.length] ?? theme.colors.primary,
                  },
                ]}
              />
            </View>
            <Text style={[styles.barValue, { color: theme.colors.text }]}>{row.total}</Text>
          </View>
        ));
      })()}
    </View>
  );
}

function LabUtilizationCard({
  report,
  theme,
  title,
}: {
  report: ReportSnapshot;
  theme: ReturnType<typeof useAppTheme>;
  title: string;
}) {
  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>{title}</Text>
      {report.labUtilization.map((lab) => (
        <View key={lab.laboratory_name} style={[styles.innerCard, { backgroundColor: theme.colors.surfaceMuted }]}>
          <View style={styles.utilizationHeader}>
            <Text style={[styles.innerTitle, { color: theme.colors.text }]}>{lab.laboratory_name}</Text>
            <Text style={[styles.utilizationPct, { color: theme.colors.primary }]}>{lab.usage_percentage}%</Text>
          </View>
          <View style={styles.barTrack}>
            <View
              style={[
                styles.barFill,
                {
                  width: `${Math.min(lab.usage_percentage, 100)}%` as any,
                  backgroundColor:
                    lab.usage_percentage >= 75
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
  );
}

function UpcomingBookingsCard({
  report,
  theme,
  title,
}: {
  report: ReportSnapshot;
  theme: ReturnType<typeof useAppTheme>;
  title: string;
}) {
  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>{title}</Text>
      {report.upcomingBookings.length === 0 ? (
        <EmptyState title="No upcoming activity" message="No approved or pending booking activity is scheduled yet." />
      ) : (
        report.upcomingBookings.map((booking, index) => (
          <View
            key={`${booking.lab_name}-${booking.date}-${index}`}
            style={[styles.innerCard, { backgroundColor: theme.colors.surfaceMuted }]}
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
  );
}

export function ReportsScreen() {
  const theme = useAppTheme();
  const [filtersExpanded, setFiltersExpanded] = useState(false);
  const [draftFilters, setDraftFilters] = useState<ReportFilterState>(EMPTY_FILTERS);
  const [appliedFilters, setAppliedFilters] = useState<ReportFilterState>(EMPTY_FILTERS);
  const [busyExport, setBusyExport] = useState<'pdf' | null>(null);
  const [exportMessage, setExportMessage] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);

  const reportQuery = useQuery({
    queryKey: ['report-snapshot', appliedFilters],
    queryFn: () => getReportSnapshotRequest(appliedFilters),
  });

  useEffect(() => {
    setDraftFilters(appliedFilters);
  }, [appliedFilters]);

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
  const filterCount = activeFilterCount(appliedFilters);
  const topLabsTitle = report.role === 'pic' ? 'My Laboratories' : report.role === 'manager' ? 'Cross-Lab Demand Leaders' : 'Top Laboratories';
  const bookingTrendTitle = report.role === 'manager' ? 'Cross-Lab Booking Trend' : 'Booking Trend';
  const utilizationTitle = report.role === 'manager' ? 'Laboratory Comparison' : report.role === 'pic' ? 'Assigned Lab Utilization' : 'Lab Utilization';
  const upcomingTitle = report.role === 'pic' ? 'Upcoming Operational Activity' : 'Upcoming Activity';

  const options = report.availableFilters ?? {};

  async function handleExport() {
    setExportMessage(null);
    setExportError(null);
    setBusyExport('pdf');

    try {
      const filename = `slams-${report.role}-analytics-report.pdf`;
      await openProtectedPdf(exports.pdf_url, filename);
      setExportMessage('PDF report opened.');
    } catch (error) {
      setExportError(readErrorMessage(error, 'Unable to open the PDF report.'));
    } finally {
      setBusyExport(null);
    }
  }

  const renderRoleSections = () => {
    if (report.role === 'pic') {
      return (
        <>
          <BookingStatusCard report={report} theme={theme} />
          <MaintenanceStatusCard report={report} theme={theme} />
          {report.labUtilization.length > 0 ? <LabUtilizationCard report={report} theme={theme} title={utilizationTitle} /> : null}
          {report.peakHours.length > 0 ? <PeakHoursCard report={report} theme={theme} /> : null}
          <UpcomingBookingsCard report={report} theme={theme} title={upcomingTitle} />
        </>
      );
    }

    if (report.role === 'manager') {
      return (
        <>
          <TopLabsCard report={report} theme={theme} title={topLabsTitle} />
          {report.monthlyTrend.length > 0 ? (
            <TrendBarsCard title={bookingTrendTitle} rows={report.monthlyTrend} theme={theme} color={theme.colors.primary} />
          ) : null}
          {report.labUtilization.length > 0 ? <LabUtilizationCard report={report} theme={theme} title={utilizationTitle} /> : null}
          {report.maintenanceTrend.length > 0 ? (
            <TrendBarsCard title="Maintenance Trend" rows={report.maintenanceTrend} theme={theme} color={theme.colors.danger} />
          ) : null}
          <MaintenanceStatusCard report={report} theme={theme} />
        </>
      );
    }

    return (
      <>
        <BookingStatusCard report={report} theme={theme} />
        <MaintenanceStatusCard report={report} theme={theme} />
        <TopLabsCard report={report} theme={theme} title={topLabsTitle} />
        {report.monthlyTrend.length > 0 ? (
          <TrendBarsCard title={bookingTrendTitle} rows={report.monthlyTrend} theme={theme} color={theme.colors.primary} />
        ) : null}
        {report.maintenanceTrend.length > 0 ? (
          <TrendBarsCard title="Maintenance Trend" rows={report.maintenanceTrend} theme={theme} color={theme.colors.danger} />
        ) : null}
        {report.peakHours.length > 0 ? <PeakHoursCard report={report} theme={theme} /> : null}
      </>
    );
  };

  return (
    <Screen>
      <View style={[styles.heroCard, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
        <Text style={[styles.title, { color: theme.colors.text }]}>{report.reportTitle}</Text>
        <Text style={[styles.subtitle, { color: theme.colors.textMuted }]}>{report.scopeLabel}</Text>
        <Text style={[styles.meta, { color: theme.colors.textMuted }]}>Generated {report.generatedAt}</Text>

        {report.uiProfile?.headline ? (
          <View style={[styles.rolePanel, { backgroundColor: theme.colors.surfaceMuted }]}>
            <Text style={[styles.roleHeadline, { color: theme.colors.text }]}>{report.uiProfile.headline}</Text>
            {report.uiProfile.subheadline ? (
              <Text style={[styles.roleSubheadline, { color: theme.colors.textMuted }]}>{report.uiProfile.subheadline}</Text>
            ) : null}
            {(report.uiProfile.focusAreas ?? []).length > 0 ? (
              <View style={styles.focusWrap}>
                {report.uiProfile.focusAreas?.map((area) => (
                  <View key={area} style={[styles.focusPill, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
                    <Text style={[styles.focusPillText, { color: theme.colors.text }]}>{area}</Text>
                  </View>
                ))}
              </View>
            ) : null}
          </View>
        ) : null}

        <Pressable
          onPress={() => setFiltersExpanded((value) => !value)}
          style={[styles.filterToggle, { backgroundColor: theme.colors.surfaceMuted, borderColor: theme.colors.border }]}
        >
          <Text style={[styles.filterToggleTitle, { color: theme.colors.text }]}>
            Filters {filterCount > 0 ? `(${filterCount})` : ''}
          </Text>
          <Text style={[styles.filterToggleMeta, { color: theme.colors.textMuted }]}>
            {filtersExpanded ? 'Hide filter form' : 'Show filter form'}
          </Text>
        </Pressable>

        {filtersExpanded ? (
          <View style={[styles.filterPanel, { borderColor: theme.colors.border }]}>
            <View style={styles.dateRow}>
              <View style={styles.dateField}>
                <Text style={[styles.filterLabel, { color: theme.colors.text }]}>From Date</Text>
                <TextInput
                  placeholder="YYYY-MM-DD"
                  placeholderTextColor={theme.colors.textMuted}
                  value={draftFilters.date_from}
                  onChangeText={(value) => setDraftFilters((current) => ({ ...current, date_from: value }))}
                  style={[styles.input, { color: theme.colors.text, borderColor: theme.colors.border, backgroundColor: theme.colors.surface }]}
                />
              </View>
              <View style={styles.dateField}>
                <Text style={[styles.filterLabel, { color: theme.colors.text }]}>To Date</Text>
                <TextInput
                  placeholder="YYYY-MM-DD"
                  placeholderTextColor={theme.colors.textMuted}
                  value={draftFilters.date_to}
                  onChangeText={(value) => setDraftFilters((current) => ({ ...current, date_to: value }))}
                  style={[styles.input, { color: theme.colors.text, borderColor: theme.colors.border, backgroundColor: theme.colors.surface }]}
                />
              </View>
            </View>

            <OptionChipRow label="Laboratory" options={options.labs ?? []} selected={draftFilters.lab_id} onSelect={(value) => setDraftFilters((current) => ({ ...current, lab_id: value, asset_id: '' }))} theme={theme} />
            <OptionChipRow label="Asset" options={options.assets ?? []} selected={draftFilters.asset_id} onSelect={(value) => setDraftFilters((current) => ({ ...current, asset_id: value }))} theme={theme} />
            <OptionChipRow label="Booking Status" options={options.booking_statuses ?? []} selected={draftFilters.booking_status} onSelect={(value) => setDraftFilters((current) => ({ ...current, booking_status: value }))} theme={theme} />
            <OptionChipRow label="Maintenance Status" options={options.maintenance_statuses ?? []} selected={draftFilters.maintenance_status} onSelect={(value) => setDraftFilters((current) => ({ ...current, maintenance_status: value }))} theme={theme} />
            <OptionChipRow label="Asset Category" options={options.asset_categories ?? []} selected={draftFilters.asset_category} onSelect={(value) => setDraftFilters((current) => ({ ...current, asset_category: value }))} theme={theme} />
            <OptionChipRow label="Asset Status" options={options.asset_statuses ?? []} selected={draftFilters.asset_status} onSelect={(value) => setDraftFilters((current) => ({ ...current, asset_status: value }))} theme={theme} />

            <View style={styles.filterActionRow}>
              <Pressable
                onPress={() => setAppliedFilters(draftFilters)}
                style={[styles.primaryAction, { backgroundColor: theme.colors.primary }]}
              >
                <Text style={styles.primaryActionText}>Apply Filters</Text>
              </Pressable>
              <Pressable
                onPress={() => {
                  setDraftFilters(EMPTY_FILTERS);
                  setAppliedFilters(EMPTY_FILTERS);
                }}
                style={[styles.secondaryAction, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}
              >
                <Text style={[styles.secondaryActionText, { color: theme.colors.text }]}>Reset</Text>
              </Pressable>
            </View>
          </View>
        ) : null}

        <View style={styles.exportRow}>
          <Pressable
            disabled={busyExport !== null}
            onPress={() => {
              void handleExport();
            }}
            style={[styles.exportButton, { backgroundColor: theme.colors.primary, opacity: busyExport !== null ? 0.7 : 1 }]}
          >
            <Text style={styles.exportButtonText}>{busyExport === 'pdf' ? 'Preparing PDF...' : 'Export PDF'}</Text>
          </Pressable>
        </View>

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

      {(report.uiProfile?.highlights ?? []).length > 0 ? (
        <View style={styles.statsRow}>
          {report.uiProfile?.highlights?.map((item) => (
            <StatCard key={item.label} label={item.label} tone={item.tone} value={Number(item.value ?? 0)} />
          ))}
        </View>
      ) : null}

      {renderRoleSections()}
    </Screen>
  );
}

const styles = StyleSheet.create({
  heroCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
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
  rolePanel: {
    borderRadius: 14,
    gap: 8,
    padding: 14,
  },
  roleHeadline: {
    fontSize: 16,
    fontWeight: '800',
  },
  roleSubheadline: {
    fontSize: 13,
    lineHeight: 18,
  },
  focusWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 4,
  },
  focusPill: {
    borderRadius: 999,
    borderWidth: 1,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  focusPillText: {
    fontSize: 12,
    fontWeight: '700',
  },
  filterToggle: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  filterToggleTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  filterToggleMeta: {
    fontSize: 12,
    marginTop: 2,
  },
  filterPanel: {
    borderTopWidth: 1,
    gap: 12,
    paddingTop: 12,
  },
  dateRow: {
    flexDirection: 'row',
    gap: 10,
  },
  dateField: {
    flex: 1,
  },
  filterGroup: {
    gap: 8,
  },
  filterLabel: {
    fontSize: 13,
    fontWeight: '700',
  },
  input: {
    borderRadius: 12,
    borderWidth: 1,
    fontSize: 13,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  chipRow: {
    gap: 8,
    paddingRight: 16,
  },
  filterChip: {
    borderRadius: 999,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  filterChipText: {
    fontSize: 12,
    fontWeight: '700',
  },
  filterActionRow: {
    flexDirection: 'row',
    gap: 10,
  },
  primaryAction: {
    alignItems: 'center',
    borderRadius: 12,
    flex: 1,
    paddingVertical: 12,
  },
  primaryActionText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '800',
  },
  secondaryAction: {
    alignItems: 'center',
    borderRadius: 12,
    borderWidth: 1,
    flex: 1,
    paddingVertical: 12,
  },
  secondaryActionText: {
    fontSize: 13,
    fontWeight: '800',
  },
  exportRow: {
    marginTop: 4,
  },
  exportButton: {
    alignItems: 'center',
    borderRadius: 12,
    paddingVertical: 12,
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
