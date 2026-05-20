import { useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, Animated, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import type { ToneKey } from '../types/api';
import { textStyle } from '../theme/palette';
import { useAppTheme } from '../theme/use-app-theme';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

type TrendProp = {
  value: number;
  direction: 'up' | 'down' | 'neutral';
};

function CountUpValue({ value, color }: { value: number | string; color: string }) {
  const animatedValue = useRef(new Animated.Value(0)).current;
  const numericValue = typeof value === 'number' ? value : parseFloat(String(value));
  const isNumeric = !isNaN(numericValue);
  const [reduceMotion, setReduceMotion] = useState(false);

  useEffect(() => {
    void AccessibilityInfo.isReduceMotionEnabled().then(setReduceMotion);
  }, []);

  useEffect(() => {
    if (!isNumeric) return;
    if (reduceMotion) {
      animatedValue.setValue(numericValue);
      return;
    }
    animatedValue.setValue(0);
    Animated.timing(animatedValue, {
      toValue: numericValue,
      duration: 600,
      useNativeDriver: false,
    }).start();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [numericValue, reduceMotion]);

  if (!isNumeric) {
    return (
      <Text style={[textStyle.display, styles.value, { color }]}>{value}</Text>
    );
  }

  return (
    <Animated.Text style={[textStyle.display, styles.value, { color }]}>
      {animatedValue.interpolate({
        inputRange: [0, numericValue === 0 ? 1 : numericValue],
        outputRange: ['0', String(numericValue)],
        extrapolate: 'clamp',
      }) as unknown as string}
    </Animated.Text>
  );
}

export function StatCard({
  label,
  value,
  tone = 'neutral',
  icon,
  trend,
}: {
  label: string;
  value: number | string;
  tone?: ToneKey;
  icon?: IoniconName;
  trend?: TrendProp;
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

  const trendIcon: IoniconName =
    !trend ? 'remove-outline'
    : trend.direction === 'up' ? 'arrow-up-outline'
    : trend.direction === 'down' ? 'arrow-down-outline'
    : 'remove-outline';

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
      {icon ? (
        <View
          style={[
            styles.iconCircle,
            {
              backgroundColor: toneStyle.backgroundColor,
            },
          ]}
        >
          <Ionicons color={toneStyle.valueColor} name={icon} size={theme.iconSize.sm} />
        </View>
      ) : null}
      <Text
        style={[
          textStyle.label,
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
        <CountUpValue color={toneStyle.valueColor} value={value} />
      </View>
      {trend ? (
        <View style={styles.trendRow}>
          <Ionicons
            color={
              trend.direction === 'up'
                ? theme.colors.success
                : trend.direction === 'down'
                  ? theme.colors.danger
                  : theme.colors.textMuted
            }
            name={trendIcon}
            size={theme.iconSize.xs}
          />
          <Text
            style={[
              styles.trendText,
              {
                color:
                  trend.direction === 'up'
                    ? theme.colors.success
                    : trend.direction === 'down'
                      ? theme.colors.danger
                      : theme.colors.textMuted,
              },
            ]}
          >
            {trend.value > 0 ? '+' : ''}{trend.value}
          </Text>
        </View>
      ) : null}
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
  iconCircle: {
    alignItems: 'center',
    borderRadius: 18,
    height: 36,
    justifyContent: 'center',
    width: 36,
  },
  label: {
    // color applied inline via theme
  },
  valueWrap: {
    alignSelf: 'flex-start',
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  value: {
    // fontSize/fontWeight come from textStyle.display; color applied inline
  },
  trendRow: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 3,
  },
  trendText: {
    fontSize: 11,
    fontWeight: '600',
  },
});
