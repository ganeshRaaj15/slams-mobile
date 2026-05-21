import { useEffect } from 'react';
import type { ViewStyle } from 'react-native';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withDelay,
  withSpring,
  useReducedMotion,
} from 'react-native-reanimated';

type Props = {
  children: React.ReactNode;
  index: number;
  style?: ViewStyle;
};

const TRANSLATE_SPRING = { damping: 18, stiffness: 200, mass: 0.9 };
const OPACITY_SPRING  = { damping: 22, stiffness: 260, mass: 0.8 };

export function AnimatedListItem({ children, index, style }: Props) {
  const reduceMotion = useReducedMotion();
  const opacity    = useSharedValue(reduceMotion ? 1 : 0);
  const translateY = useSharedValue(reduceMotion ? 0 : 14);

  useEffect(() => {
    if (reduceMotion) {
      opacity.value    = 1;
      translateY.value = 0;
      return;
    }
    const delay = Math.min(index * 40, 200);
    opacity.value    = withDelay(delay, withSpring(1, OPACITY_SPRING));
    translateY.value = withDelay(delay, withSpring(0, TRANSLATE_SPRING));
  }, [reduceMotion, opacity, translateY, index]);

  const animStyle = useAnimatedStyle(() => ({
    opacity: opacity.value,
    transform: [{ translateY: translateY.value }],
  }));

  return (
    <Animated.View style={[animStyle, style]}>
      {children}
    </Animated.View>
  );
}
