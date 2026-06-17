import type { PropsWithChildren } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import Animated, { FadeIn, FadeOut, useReducedMotion } from 'react-native-reanimated';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAppTheme } from '../theme/use-app-theme';

type ScreenProps = PropsWithChildren<{
  scroll?: boolean;
  centerContent?: boolean;
  maxWidth?: 'default' | 'wide';
}>;

export function Screen({
  children,
  scroll = true,
  centerContent = false,
  maxWidth = 'default',
}: ScreenProps) {
  const theme = useAppTheme();
  const reduceMotion = useReducedMotion();
  const entering = reduceMotion ? undefined : FadeIn.duration(180);
  const exiting  = reduceMotion ? undefined : FadeOut.duration(140);
  const contentLayout = [
    styles.contentFrame,
    centerContent ? styles.centerContent : null,
    maxWidth === 'wide' ? styles.maxWidthWide : null,
  ];

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
        <Animated.View entering={entering} exiting={exiting} style={styles.fill}>
          <View style={contentLayout}>{children}</View>
        </Animated.View>
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
      <Animated.View entering={entering} exiting={exiting} style={styles.fill}>
        <ScrollView
          automaticallyAdjustKeyboardInsets
          contentContainerStyle={[
            styles.content,
            centerContent ? styles.centerScrollContent : null,
            {
              padding: theme.spacing.lg,
            },
          ]}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View style={contentLayout}>{children}</View>
        </ScrollView>
      </Animated.View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    overflow: 'hidden',
  },
  fill: {
    flex: 1,
  },
  content: {
    gap: 14,
    paddingBottom: 44,
  },
  centerScrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
  },
  contentFrame: {
    alignSelf: 'center',
    width: '100%',
  },
  centerContent: {
    flex: 1,
    justifyContent: 'center',
  },
  maxWidthWide: {
    maxWidth: 1120,
  },
});
