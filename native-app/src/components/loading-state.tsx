import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { useAppTheme } from '../theme/use-app-theme';

export function LoadingState({ label = 'Loading...' }: { label?: string }) {
  const theme = useAppTheme();

  return (
    <View style={styles.container}>
      <ActivityIndicator color={theme.colors.primary} size="large" />
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
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    gap: 10,
    justifyContent: 'center',
    minHeight: 220,
  },
  label: {
    fontSize: 14,
    fontWeight: '500',
  },
});
