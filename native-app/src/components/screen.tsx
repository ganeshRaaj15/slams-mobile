import type { PropsWithChildren } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAppTheme } from '../theme/use-app-theme';

type ScreenProps = PropsWithChildren<{
  scroll?: boolean;
}>;

export function Screen({ children, scroll = true }: ScreenProps) {
  const theme = useAppTheme();

  if (!scroll) {
    return (
      <SafeAreaView
        style={[
          styles.safeArea,
        {
          backgroundColor: theme.colors.background,
        },
      ]}
      >
        <Backdrop />
        <View style={styles.fill}>{children}</View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView
      style={[
        styles.safeArea,
        {
          backgroundColor: theme.colors.background,
        },
      ]}
    >
      <Backdrop />
      <ScrollView
        contentContainerStyle={[
          styles.content,
          {
            padding: theme.spacing.lg,
          },
        ]}
        showsVerticalScrollIndicator={false}
      >
        {children}
      </ScrollView>
    </SafeAreaView>
  );

  function Backdrop() {
    return (
      <View pointerEvents="none" style={styles.backdrop}>
        <View
          style={[
            styles.glowPrimary,
            {
              backgroundColor: theme.colors.glowPrimary,
            },
          ]}
        />
        <View
          style={[
            styles.glowSecondary,
            {
              backgroundColor: theme.colors.glowAccent,
            },
          ]}
        />
        <View
          style={[
            styles.glowTertiary,
            {
              backgroundColor: theme.colors.primarySoft,
              borderColor: theme.colors.border,
            },
          ]}
        />
      </View>
    );
  }
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    overflow: 'hidden',
  },
  fill: {
    flex: 1,
  },
  backdrop: {
    ...StyleSheet.absoluteFillObject,
  },
  glowPrimary: {
    position: 'absolute',
    top: -90,
    left: -70,
    width: 260,
    height: 260,
    borderRadius: 999,
  },
  glowSecondary: {
    position: 'absolute',
    top: 96,
    right: -84,
    width: 220,
    height: 220,
    borderRadius: 999,
  },
  glowTertiary: {
    position: 'absolute',
    bottom: 118,
    left: '18%',
    width: 180,
    height: 180,
    borderRadius: 999,
    borderWidth: 1,
  },
  content: {
    gap: 14,
    paddingBottom: 44,
  },
});
