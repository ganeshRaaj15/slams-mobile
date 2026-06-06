import type { ComponentProps, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { Animated, StyleSheet, Text, TextInput, View } from 'react-native';

import { textStyle } from '../theme/palette';
import { useAppTheme } from '../theme/use-app-theme';

type TextFieldProps = ComponentProps<typeof TextInput> & {
  label: string;
  hint?: string;
  rightAccessory?: ReactNode;
};

export function TextField({ label, hint, rightAccessory, style, onFocus, onBlur, ...props }: TextFieldProps) {
  const theme = useAppTheme();
  const [focused, setFocused] = useState(false);
  const focusAnim = useRef(new Animated.Value(0)).current;

  function handleFocus(e: Parameters<NonNullable<ComponentProps<typeof TextInput>['onFocus']>>[0]) {
    setFocused(true);
    Animated.timing(focusAnim, {
      toValue: 1,
      duration: 150,
      useNativeDriver: false,
    }).start();
    onFocus?.(e);
  }

  function handleBlur(e: Parameters<NonNullable<ComponentProps<typeof TextInput>['onBlur']>>[0]) {
    setFocused(false);
    Animated.timing(focusAnim, {
      toValue: 0,
      duration: 150,
      useNativeDriver: false,
    }).start();
    onBlur?.(e);
  }

  const borderColor = focusAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [theme.colors.borderStrong, theme.colors.primary],
  });

  const backgroundColor = focusAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [theme.colors.glassStrong, theme.colors.surface],
  });

  return (
    <View style={styles.wrap}>
      <Text
        style={[
          textStyle.label,
          styles.label,
          {
            color: focused ? theme.colors.primary : theme.colors.text,
          },
        ]}
      >
        {label}
      </Text>
      {hint ? (
        <Text
          style={[
            textStyle.caption,
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
        <Animated.View
          style={[
            styles.inputContainer,
            {
              borderColor,
              backgroundColor,
            },
          ]}
        >
          <TextInput
            placeholderTextColor={theme.colors.textMuted}
            style={[
              styles.input,
              {
                color: theme.colors.text,
                fontFamily: theme.fonts.bodyRegular,
              },
              rightAccessory ? styles.inputWithAccessory : null,
              style,
            ]}
            onBlur={handleBlur}
            onFocus={handleFocus}
            {...props}
          />
        </Animated.View>
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
  },
  hint: {
  },
  inputWrap: {
    justifyContent: 'center',
    position: 'relative',
  },
  inputContainer: {
    borderRadius: 14,
    borderWidth: 1,
  },
  input: {
    fontSize: 15,
    paddingHorizontal: 16,
    paddingVertical: 14,
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
