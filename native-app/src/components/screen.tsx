import type { PropsWithChildren } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import Animated, { FadeIn, useReducedMotion } from 'react-native-reanimated';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAppTheme } from '../theme/use-app-theme';

type ScreenProps = PropsWithChildren<{
  scroll?: boolean;
}>;

export function Screen({ children, scroll = true }: ScreenProps) {
  const theme = useAppTheme();
  const reduceMotion = useReducedMotion();
  const entering = reduceMotion ? undefined : FadeIn.duration(180);

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
        <Animated.View entering={entering} style={styles.fill}>{children}</Animated.View>
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
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.fill}
      >
        <Animated.View entering={entering} style={styles.fill}>
          <ScrollView
            contentContainerStyle={[
              styles.content,
              {
                padding: theme.spacing.lg,
              },
            ]}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            {children}
          </ScrollView>
        </Animated.View>
      </KeyboardAvoidingView>
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
});
