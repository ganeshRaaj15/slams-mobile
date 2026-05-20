import { StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import {
  ASSET_STATUS_LABELS,
  BOOKING_STATUS_LABELS,
  EXTERNAL_REQUEST_STATUS_LABELS,
  MAINTENANCE_STATUS_LABELS,
} from '../constants/statuses';
import { useAppTheme } from '../theme/use-app-theme';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

function getStatusIcon(status: string, kind: string): IoniconName {
  const key = `${kind}:${status}`;
  const iconMap: Record<string, IoniconName> = {
    // booking
    'booking:PENDING': 'time-outline',
    'booking:APPROVED': 'checkmark-circle',
    'booking:REJECTED': 'close-circle-outline',
    'booking:CANCELLED': 'ban-outline',
    // external
    'external:pending_pic_approval': 'time-outline',
    'external:pending_manager_approval': 'time-outline',
    'external:submitted': 'time-outline',
    'external:under_review': 'search-outline',
    'external:needs_information': 'information-circle-outline',
    'external:approved_for_scheduling': 'checkmark-circle',
    'external:rejected': 'close-circle-outline',
    'external:completed': 'checkmark-done-outline',
    // maintenance
    'maintenance:reported': 'alert-circle-outline',
    'maintenance:scheduled': 'calendar-outline',
    'maintenance:in_progress': 'construct-outline',
    'maintenance:testing': 'flask-outline',
    'maintenance:completed': 'checkmark-done-outline',
    'maintenance:cancelled': 'close-circle-outline',
    // asset
    'asset:available': 'checkmark-circle',
    'asset:maintenance': 'construct-outline',
    'asset:faulty': 'close-circle-outline',
  };
  return iconMap[key] ?? 'ellipse-outline';
}

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
          pending_pic_approval: { bg: theme.colors.warningSoft, text: theme.colors.warning },
          pending_manager_approval: { bg: theme.colors.primarySoft, text: theme.colors.primary },
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

  const icon = getStatusIcon(status, kind);

  return (
    <View
      style={[
        styles.pill,
        {
          backgroundColor: style.bg,
        },
      ]}
    >
      <Ionicons color={style.text} name={icon} size={14} />
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
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderRadius: 999,
    flexDirection: 'row',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 5,
  },
  label: {
    fontSize: 12,
    fontWeight: '700',
  },
});
