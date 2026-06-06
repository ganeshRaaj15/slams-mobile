import { useCallback } from 'react';
import type { PropsWithChildren } from 'react';
import type { StyleProp, ViewStyle } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import Animated, {
  Easing,
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withDelay,
  withTiming,
} from 'react-native-reanimated';

type AnimatedPageSectionProps = PropsWithChildren<{
  index?: number;
  axis?: 'x' | 'y';
  direction?: 'forward' | 'backward';
  variant?: 'hero' | 'section' | 'card';
  style?: StyleProp<ViewStyle>;
}>;

export function AnimatedPageSection({
  children,
  index = 0,
  axis = 'y',
  direction = 'forward',
  variant = 'card',
  style,
}: AnimatedPageSectionProps) {
  const reduceMotion = useReducedMotion();
  const distance = variant === 'hero' ? 20 : variant === 'section' ? 16 : 12;
  const initialScale = variant === 'hero' ? 0.985 : variant === 'section' ? 0.99 : 0.992;
  const enterDuration = variant === 'hero' ? 320 : variant === 'section' ? 280 : 240;
  const translateOrigin = direction === 'forward' ? distance : -distance;
  const opacity = useSharedValue(reduceMotion ? 1 : 0);
  const translate = useSharedValue(reduceMotion ? 0 : translateOrigin);
  const scale = useSharedValue(reduceMotion ? 1 : initialScale);

  useFocusEffect(
    useCallback(() => {
      if (reduceMotion) {
        opacity.value = 1;
        translate.value = 0;
        scale.value = 1;
        return undefined;
      }

      const delay = Math.min(index * 32, 180);

      opacity.value = 0;
      translate.value = translateOrigin;
      scale.value = initialScale;
      opacity.value = withDelay(
        delay,
        withTiming(1, {
          duration: enterDuration,
          easing: Easing.out(Easing.cubic),
        }),
      );
      translate.value = withDelay(
        delay,
        withTiming(0, {
          duration: enterDuration,
          easing: Easing.out(Easing.cubic),
        }),
      );
      scale.value = withDelay(
        delay,
        withTiming(1, {
          duration: enterDuration + 40,
          easing: Easing.out(Easing.cubic),
        }),
      );

      return undefined;
    }, [enterDuration, index, initialScale, opacity, reduceMotion, scale, translate, translateOrigin]),
  );

  const animatedStyle = useAnimatedStyle(() => ({
    opacity: opacity.value,
    transform: reduceMotion
      ? undefined
      : [
          axis === 'x' ? { translateX: translate.value } : { translateY: translate.value },
          { scale: scale.value },
        ],
  }));

  return <Animated.View style={[animatedStyle, style]}>{children}</Animated.View>;
}
