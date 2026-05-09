import type { ComponentProps, ReactNode } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';

import { useAppTheme } from '../theme/use-app-theme';

type TextFieldProps = ComponentProps<typeof TextInput> & {
  label: string;
  hint?: string;
  rightAccessory?: ReactNode;
};

export function TextField({ label, hint, rightAccessory, style, ...props }: TextFieldProps) {
  const theme = useAppTheme();

  return (
    <View style={styles.wrap}>
      <Text
        style={[
          styles.label,
          {
            color: theme.colors.text,
          },
        ]}
      >
        {label}
      </Text>
      {hint ? (
        <Text
          style={[
            styles.hint,
            {
              color: theme.colors.textMuted,
            },
          ]}
        >
          {hint}
        </Text>
      ) : null}
      <View style={styles.inputWrap}>
        <TextInput
          placeholderTextColor={theme.colors.textMuted}
          style={[
            styles.input,
            {
              backgroundColor: theme.colors.surfaceMuted,
              borderColor: theme.colors.borderStrong,
              color: theme.colors.text,
            },
            rightAccessory ? styles.inputWithAccessory : null,
            style,
          ]}
          {...props}
        />
        {rightAccessory ? <View style={styles.accessory}>{rightAccessory}</View> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    gap: 6,
  },
  label: {
    fontSize: 14,
    fontWeight: '700',
  },
  hint: {
    fontSize: 12,
    lineHeight: 18,
  },
  inputWrap: {
    justifyContent: 'center',
    position: 'relative',
  },
  input: {
    borderRadius: 14,
    borderWidth: 1,
    fontSize: 15,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  inputWithAccessory: {
    paddingRight: 48,
  },
  accessory: {
    bottom: 0,
    justifyContent: 'center',
    position: 'absolute',
    right: 12,
    top: 0,
  },
});
