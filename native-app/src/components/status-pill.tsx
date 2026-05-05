import { StyleSheet, Text, View } from 'react-native';

import {
  ASSET_STATUS_LABELS,
  BOOKING_STATUS_LABELS,
  EXTERNAL_REQUEST_STATUS_LABELS,
  MAINTENANCE_STATUS_LABELS,
} from '../constants/statuses';
import { useAppTheme } from '../theme/use-app-theme';

export function StatusPill({
  status,
  kind = 'booking',
}: {
  status: string;
  kind?: 'booking' | 'external' | 'maintenance' | 'asset';
}) {
  const theme = useAppTheme();

  const palette =
    kind === 'external'
      ? {
          submitted: { bg: theme.colors.warningSoft, text: theme.colors.warning },
          under_review: { bg: theme.colors.primarySoft, text: theme.colors.primary },
          needs_information: { bg: theme.colors.surfaceMuted, text: theme.colors.textMuted },
          approved_for_scheduling: { bg: theme.colors.successSoft, text: theme.colors.success },
          rejected: { bg: theme.colors.dangerSoft, text: theme.colors.danger },
          completed: { bg: theme.colors.accentSoft, text: theme.colors.accent },
        }
      : kind === 'maintenance'
        ? {
            reported: { bg: theme.colors.warningSoft, text: theme.colors.warning },
            scheduled: { bg: theme.colors.primarySoft, text: theme.colors.primary },
            in_progress: { bg: theme.colors.accentSoft, text: theme.colors.accent },
          testing: { bg: theme.colors.surfaceMuted, text: theme.colors.text },
          completed: { bg: theme.colors.successSoft, text: theme.colors.success },
          cancelled: { bg: theme.colors.dangerSoft, text: theme.colors.danger },
        }
      : kind === 'asset'
        ? {
            available: { bg: theme.colors.successSoft, text: theme.colors.success },
            maintenance: { bg: theme.colors.warningSoft, text: theme.colors.warning },
            faulty: { bg: theme.colors.dangerSoft, text: theme.colors.danger },
          }
      : {
          PENDING: { bg: theme.colors.warningSoft, text: theme.colors.warning },
          APPROVED: { bg: theme.colors.successSoft, text: theme.colors.success },
          REJECTED: { bg: theme.colors.dangerSoft, text: theme.colors.danger },
          CANCELLED: { bg: theme.colors.surfaceMuted, text: theme.colors.textMuted },
        };

  const style =
    palette[status as keyof typeof palette] ?? {
      bg: theme.colors.surfaceMuted,
      text: theme.colors.text,
    };

  const label =
    kind === 'external'
      ? EXTERNAL_REQUEST_STATUS_LABELS[status] ?? status
      : kind === 'maintenance'
        ? MAINTENANCE_STATUS_LABELS[status] ?? status
        : kind === 'asset'
          ? ASSET_STATUS_LABELS[status] ?? status
        : BOOKING_STATUS_LABELS[status] ?? status;

  return (
    <View
      style={[
        styles.pill,
        {
          backgroundColor: style.bg,
        },
      ]}
    >
      <Text
        style={[
          styles.label,
          {
            color: style.text,
          },
        ]}
      >
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  label: {
    fontSize: 12,
    fontWeight: '700',
  },
});
