import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { textStyle } from '../theme/palette';
import { useAppTheme } from '../theme/use-app-theme';

export function ErrorState({
  message,
  onRetry,
}: {
  message: string;
  onRetry?: () => void;
}) {
  const theme = useAppTheme();

  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: theme.colors.surface,
          borderColor: theme.colors.danger,
        },
      ]}
    >
      <View
        style={[
          styles.iconWrap,
          {
            backgroundColor: theme.colors.dangerSoft,
          },
        ]}
      >
        <Ionicons color={theme.colors.danger} name="alert-circle" size={theme.iconSize.lg} />
      </View>
      <Text
        style={[
          textStyle.heading,
          styles.title,
          {
            color: theme.colors.heading,
          },
        ]}
      >
        Something went wrong
      </Text>
      <Text
        style={[
          textStyle.caption,
          styles.message,
          {
            color: theme.colors.textMuted,
          },
        ]}
      >
        {message}
      </Text>
      {onRetry ? (
        <Pressable
          accessibilityLabel="Retry"
          accessibilityRole="button"
          onPress={onRetry}
          style={({ pressed }) => [
            styles.button,
            {
              backgroundColor: theme.colors.danger,
              opacity: pressed ? 0.82 : 1,
            },
          ]}
        >
          <Ionicons color="#ffffff" name="refresh-outline" size={theme.iconSize.xs} />
          <Text style={[textStyle.label, styles.buttonText]}>Try again</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    alignItems: 'center',
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 28,
  },
  iconWrap: {
    alignItems: 'center',
    borderRadius: 20,
    height: 68,
    justifyContent: 'center',
    marginBottom: 2,
    width: 68,
  },
  title: {
    textAlign: 'center',
  },
  message: {
    textAlign: 'center',
  },
  button: {
    alignItems: 'center',
    alignSelf: 'center',
    borderRadius: 12,
    flexDirection: 'row',
    gap: 6,
    marginTop: 6,
    paddingHorizontal: 20,
    paddingVertical: 11,
  },
  buttonText: {
    color: '#ffffff',
  },
});
