import { useWindowDimensions } from 'react-native';

type LayoutWidth = 'narrow' | 'default' | 'wide' | 'full';

const MAX_WIDTHS: Record<Exclude<LayoutWidth, 'full'>, number> = {
  narrow: 620,
  default: 1040,
  wide: 1380,
};

export function useResponsiveLayout() {
  const { width, height } = useWindowDimensions();
  const shortestSide = Math.min(width, height);
  const longestSide = Math.max(width, height);
  const isLandscape = width > height;
  const isTablet = shortestSide >= 768;
  const isTabletLandscape = isTablet && isLandscape;
  const isWide = width >= 1100;
  const isExtraWide = width >= 1400;

  function getContentMaxWidth(layoutWidth: LayoutWidth = 'default') {
    if (layoutWidth === 'full') {
      return width;
    }

    if (!isTablet) {
      return width;
    }

    return Math.min(MAX_WIDTHS[layoutWidth], width - 32);
  }

  return {
    width,
    height,
    shortestSide,
    longestSide,
    isLandscape,
    isTablet,
    isTabletLandscape,
    isWide,
    isExtraWide,
    getContentMaxWidth,
  };
}
