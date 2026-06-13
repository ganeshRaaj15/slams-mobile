import { useMemo } from 'react';
import { useWindowDimensions } from 'react-native';

const TABLET_BREAKPOINT = 768;

export function useResponsiveLayout() {
  const { width, height } = useWindowDimensions();

  return useMemo(() => {
    const isTablet = width >= TABLET_BREAKPOINT;
    const isLandscape = width > height;

    return {
      width,
      height,
      isLandscape,
      isTablet,
      isTabletLandscape: isTablet && isLandscape,
    };
  }, [height, width]);
}
