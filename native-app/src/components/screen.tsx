import type { PropsWithChildren } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';

type ScreenProps = PropsWithChildren<{
  scroll?: boolean;
  maxWidth?: 'narrow' | 'default' | 'wide' | 'full';
  centerContent?: boolean;
}>;

export function Screen({ children, scroll = true, maxWidth = 'default', centerContent = false }: ScreenProps) {
  const theme = useAppTheme();
  const insets = useSafeAreaInsets();
  const responsive = useResponsiveLayout();
  const contentMaxWidth = responsive.getContentMaxWidth(maxWidth);

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
        <View pointerEvents="none" style={styles.backdrop}>
          <View
            style={[
              styles.orb,
              styles.orbPrimary,
              { backgroundColor: theme.colors.heroOrbA },
            ]}
          />
          <View
            style={[
              styles.orb,
              styles.orbSecondary,
              { backgroundColor: theme.colors.heroOrbB },
            ]}
          />
        </View>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.fill}
      >
          <View style={styles.fill}>
            <View
              style={[
                styles.staticContent,
                { maxWidth: contentMaxWidth },
                centerContent && {
                  justifyContent: 'center',
                  paddingHorizontal: theme.spacing.lg,
                  paddingVertical: theme.spacing.xl,
                },
              ]}
            >
              {children}
            </View>
          </View>
      </KeyboardAvoidingView>
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
      <View pointerEvents="none" style={styles.backdrop}>
        <View
          style={[
            styles.orb,
            styles.orbPrimary,
            { backgroundColor: theme.colors.heroOrbA },
          ]}
        />
        <View
          style={[
            styles.orb,
            styles.orbSecondary,
            { backgroundColor: theme.colors.heroOrbB },
          ]}
        />
      </View>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.fill}
      >
        <View style={styles.fill}>
          <ScrollView
            contentContainerStyle={[
              styles.content,
              {
                alignItems: 'center',
                paddingHorizontal: theme.spacing.lg,
                paddingTop: theme.spacing.xl,
                paddingBottom: Math.max(theme.spacing.xl * 2, insets.bottom + 88),
              },
            ]}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            <View style={[styles.innerContent, { maxWidth: contentMaxWidth }]}>
              {children}
            </View>
          </ScrollView>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    overflow: 'hidden',
  },
  backdrop: {
    ...StyleSheet.absoluteFillObject,
  },
  fill: {
    flex: 1,
  },
  staticContent: {
    alignSelf: 'center',
    flex: 1,
    width: '100%',
  },
  content: {
    gap: 18,
  },
  innerContent: {
    gap: 18,
    width: '100%',
  },
  orb: {
    borderRadius: 999,
    position: 'absolute',
  },
  orbPrimary: {
    height: 260,
    left: -72,
    top: -28,
    width: 260,
  },
  orbSecondary: {
    height: 220,
    right: -88,
    top: 110,
    width: 220,
  },
});
