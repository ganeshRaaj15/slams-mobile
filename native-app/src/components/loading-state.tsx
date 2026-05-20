import { useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, Animated, StyleSheet, View } from 'react-native';

import { useAppTheme } from '../theme/use-app-theme';

function SkeletonRow({ opacity, width = '100%' }: { opacity: Animated.Value; width?: string | number }) {
  const theme = useAppTheme();
  return (
    <Animated.View
      style={[
        styles.skeletonRow,
        {
          backgroundColor: theme.colors.surfaceMuted,
          borderColor: theme.colors.border,
          opacity,
          width: width as any,
        },
      ]}
    />
  );
}

function SkeletonCard({ opacity }: { opacity: Animated.Value }) {
  const theme = useAppTheme();
  return (
    <Animated.View
      style={[
        styles.skeletonCard,
        {
          backgroundColor: theme.colors.surface,
          borderColor: theme.colors.border,
          opacity,
        },
      ]}
    >
      <SkeletonRow opacity={opacity} width="60%" />
      <SkeletonRow opacity={opacity} width="90%" />
      <SkeletonRow opacity={opacity} width="75%" />
    </Animated.View>
  );
}

export function LoadingState({ label = 'Loading...', rows = 3 }: { label?: string; rows?: number }) {
  const [reduceMotion, setReduceMotion] = useState(false);
  const opacity = useRef(new Animated.Value(0.3)).current;

  useEffect(() => {
    void AccessibilityInfo.isReduceMotionEnabled().then(setReduceMotion);
  }, []);

  useEffect(() => {
    if (reduceMotion) return;
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(opacity, {
          toValue: 0.7,
          duration: 700,
          useNativeDriver: true,
        }),
        Animated.timing(opacity, {
          toValue: 0.3,
          duration: 700,
          useNativeDriver: true,
        }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [opacity, reduceMotion]);

  const staticOpacity = useRef(new Animated.Value(0.5)).current;
  const displayOpacity = reduceMotion ? staticOpacity : opacity;

  return (
    <View style={styles.container}>
      {Array.from({ length: rows }).map((_, i) => (
        <SkeletonCard key={i} opacity={displayOpacity} />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: 14,
    padding: 4,
  },
  skeletonCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  skeletonRow: {
    borderRadius: 6,
    height: 14,
  },
});
