import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { textStyle } from '../theme/palette';
import { useAppTheme } from '../theme/use-app-theme';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

type EmptyStateProps = {
  title: string;
  message: string;
  icon?: IoniconName;
  actionLabel?: string;
  onActionPress?: () => void;
};

export function EmptyState({
  title,
  message,
  icon = 'folder-open-outline',
  actionLabel,
  onActionPress,
}: EmptyStateProps) {
  const theme = useAppTheme();

  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: theme.colors.glassStrong,
          borderColor: theme.colors.glassBorder,
        },
      ]}
    >
      <View
        style={[
          styles.iconWrap,
          {
            backgroundColor: theme.colors.primarySoft,
          },
        ]}
      >
        <Ionicons color={theme.colors.primary} name={icon} size={theme.iconSize.lg} />
      </View>
      <Text
        style={[
          textStyle.overline,
          {
            color: theme.colors.primary,
          },
        ]}
      >
        Workspace
      </Text>
      <Text
        style={[
          textStyle.heading,
          styles.title,
          {
            color: theme.colors.heading,
          },
        ]}
      >
        {title}
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
      {actionLabel && onActionPress ? (
        <Pressable
          accessibilityRole="button"
          onPress={onActionPress}
          style={({ pressed }) => [
            styles.button,
            {
              backgroundColor: theme.colors.primary,
              opacity: pressed ? 0.82 : 1,
            },
          ]}
        >
          <Text style={[textStyle.label, styles.buttonText]}>{actionLabel}</Text>
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
    overflow: 'hidden',
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
    alignSelf: 'center',
    borderRadius: 12,
    marginTop: 6,
    paddingHorizontal: 20,
    paddingVertical: 11,
  },
  buttonText: {
    color: '#ffffff',
  },
});
