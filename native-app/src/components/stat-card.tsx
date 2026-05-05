import { StyleSheet, Text, View } from 'react-native';

import type { ToneKey } from '../types/api';
import { useAppTheme } from '../theme/use-app-theme';

export function StatCard({
  label,
  value,
  tone = 'neutral',
}: {
  label: string;
  value: number | string;
  tone?: ToneKey;
}) {
  const theme = useAppTheme();
  const cardShadow = {
    elevation: 4,
    shadowColor: theme.colors.shadow,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: theme.tone === 'dark' ? 0.26 : 0.08,
    shadowRadius: 20,
  };

  const toneStyle = {
    primary: { backgroundColor: theme.colors.primarySoft, valueColor: theme.colors.primary },
    success: { backgroundColor: theme.colors.successSoft, valueColor: theme.colors.success },
    warning: { backgroundColor: theme.colors.warningSoft, valueColor: theme.colors.warning },
    danger: { backgroundColor: theme.colors.dangerSoft, valueColor: theme.colors.danger },
    accent: { backgroundColor: theme.colors.accentSoft, valueColor: theme.colors.accent },
    neutral: { backgroundColor: theme.colors.surfaceMuted, valueColor: theme.colors.heading },
  }[tone];

  return (
    <View
      style={[
        styles.card,
        cardShadow,
        {
          backgroundColor: theme.colors.surfaceOverlay,
          borderColor: theme.colors.border,
        },
      ]}
    >
      <Text
        style={[
          styles.label,
          {
            color: theme.colors.textMuted,
          },
        ]}
      >
        {label}
      </Text>
      <View
        style={[
          styles.valueWrap,
          {
            backgroundColor: toneStyle.backgroundColor,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text
          style={[
            styles.value,
            {
              color: toneStyle.valueColor,
            },
          ]}
        >
          {value}
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 20,
    borderWidth: 1,
    gap: 10,
    minWidth: '47%',
    padding: 15,
  },
  label: {
    fontSize: 13,
    fontWeight: '700',
  },
  valueWrap: {
    alignSelf: 'flex-start',
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  value: {
    fontSize: 22,
    fontWeight: '800',
  },
});
