export type ThemeTone = 'light' | 'dark';

export type AppTheme = ReturnType<typeof buildTheme>;

export const typography = {
  size: {
    xs: 11,
    sm: 13,
    md: 15,
    lg: 17,
    xl: 20,
    '2xl': 24,
    '3xl': 30,
  },
  lineHeight: {
    tight: 1.2,
    normal: 1.5,
    relaxed: 1.7,
  },
  weight: {
    regular: '400' as const,
    medium: '500' as const,
    semibold: '600' as const,
    bold: '700' as const,
    extrabold: '800' as const,
  },
  letterSpacing: {
    tight: -0.3,
    normal: 0,
    wide: 0.3,
    wider: 0.6,
  },
};

/**
 * Named text style presets — combine size, weight, lineHeight, and letterSpacing
 * from the scale above so screens don't inline raw pixel values.
 *
 * Usage:  style={[textStyle.heading, { color: theme.colors.heading }]}
 */
export const textStyle = {
  /** Large page/screen titles — 30px, extrabold, tight leading */
  displayLg: {
    fontSize: typography.size['3xl'],
    fontWeight: typography.weight.extrabold,
    lineHeight: typography.size['3xl'] * typography.lineHeight.tight,
    letterSpacing: typography.letterSpacing.tight,
  },
  /** Section/card titles — 24px, extrabold */
  display: {
    fontSize: typography.size['2xl'],
    fontWeight: typography.weight.extrabold,
    lineHeight: typography.size['2xl'] * typography.lineHeight.tight,
    letterSpacing: typography.letterSpacing.tight,
  },
  /** Primary headings — 20px, bold, tight leading */
  heading: {
    fontSize: typography.size.xl,
    fontWeight: typography.weight.bold,
    lineHeight: typography.size.xl * typography.lineHeight.tight,
    letterSpacing: typography.letterSpacing.normal,
  },
  /** Sub-headings — 17px, semibold */
  subheading: {
    fontSize: typography.size.lg,
    fontWeight: typography.weight.semibold,
    lineHeight: typography.size.lg * typography.lineHeight.normal,
    letterSpacing: typography.letterSpacing.normal,
  },
  /** Body copy — 15px, regular, normal leading */
  body: {
    fontSize: typography.size.md,
    fontWeight: typography.weight.regular,
    lineHeight: typography.size.md * typography.lineHeight.normal,
    letterSpacing: typography.letterSpacing.normal,
  },
  /** Emphasized body copy — same size, semibold */
  bodyStrong: {
    fontSize: typography.size.md,
    fontWeight: typography.weight.semibold,
    lineHeight: typography.size.md * typography.lineHeight.normal,
    letterSpacing: typography.letterSpacing.normal,
  },
  /** Small auxiliary text — 13px, regular */
  caption: {
    fontSize: typography.size.sm,
    fontWeight: typography.weight.regular,
    lineHeight: typography.size.sm * typography.lineHeight.relaxed,
    letterSpacing: typography.letterSpacing.normal,
  },
  /** Form/card labels — 13px, bold */
  label: {
    fontSize: typography.size.sm,
    fontWeight: typography.weight.bold,
    lineHeight: typography.size.sm * typography.lineHeight.normal,
    letterSpacing: typography.letterSpacing.normal,
  },
  /** Eyebrow / section category labels — 11px, extrabold, wide tracking */
  overline: {
    fontSize: typography.size.xs,
    fontWeight: typography.weight.extrabold,
    lineHeight: typography.size.xs * typography.lineHeight.normal,
    letterSpacing: typography.letterSpacing.wider,
    textTransform: 'uppercase' as const,
  },
} as const;

export const iconSize = {
  xs: 14,
  sm: 18,
  md: 22,
  lg: 26,
  xl: 32,
};

export function buildTheme(tone: ThemeTone) {
  const dark = tone === 'dark';

  return {
    tone,
    typography,
    textStyle,
    iconSize,
    colors: {
      background: dark ? '#101411' : '#f5f8f6',
      backgroundMuted: dark ? '#1d241f' : '#edf3ef',
      surface: dark ? '#171d19' : '#ffffff',
      surfaceMuted: dark ? 'rgba(255, 255, 255, 0.08)' : '#edf3ef',
      surfaceElevated: dark ? '#1d241f' : '#ffffff',
      surfaceAccent: dark ? 'rgba(45, 212, 191, 0.1)' : '#e7f5ef',
      surfaceOverlay: dark ? 'rgba(27, 35, 30, 0.97)' : 'rgba(255, 255, 255, 0.94)',
      surfaceModal: dark ? 'rgba(35, 44, 38, 0.99)' : 'rgba(255, 255, 255, 0.98)',
      border: dark ? 'rgba(222, 232, 226, 0.14)' : 'rgba(29, 41, 37, 0.12)',
      borderStrong: dark ? 'rgba(222, 232, 226, 0.24)' : 'rgba(29, 41, 37, 0.2)',
      text: dark ? '#e7eee9' : '#1b2622',
      heading: dark ? '#f6faf7' : '#111b17',
      textMuted: dark ? '#aab8b1' : '#4f6459',
      primary: dark ? '#2dd4bf' : '#0f766e',
      primaryStrong: dark ? '#5eead4' : '#115e59',
      primarySoft: dark ? 'rgba(45, 212, 191, 0.14)' : 'rgba(15, 118, 110, 0.12)',
      success: dark ? '#86efac' : '#15803d',
      successSoft: dark ? 'rgba(134, 239, 172, 0.14)' : 'rgba(21, 128, 61, 0.12)',
      warning: dark ? '#fcd34d' : '#b45309',
      warningSoft: dark ? 'rgba(252, 211, 77, 0.14)' : 'rgba(180, 83, 9, 0.14)',
      danger: dark ? '#fca5a5' : '#b91c1c',
      dangerSoft: dark ? 'rgba(252, 165, 165, 0.14)' : 'rgba(185, 28, 28, 0.12)',
      accent: dark ? '#7dd3fc' : '#2563eb',
      accentSoft: dark ? 'rgba(125, 211, 252, 0.14)' : 'rgba(37, 99, 235, 0.12)',
      neutral: dark ? '#cbd5e1' : '#475569',
      neutralSoft: dark ? 'rgba(203, 213, 225, 0.14)' : 'rgba(71, 85, 105, 0.12)',
      shadow: dark ? '#000000' : '#1c2723',
      tabBar: dark ? 'rgba(18, 28, 25, 0.96)' : 'rgba(255, 255, 255, 0.94)',
      tabBarBorder: dark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(29, 41, 37, 0.12)',
      tabBarActiveFill: dark ? 'rgba(45, 212, 191, 0.16)' : 'rgba(15, 118, 110, 0.1)',
      tabBarActiveBorder: dark ? 'rgba(45, 212, 191, 0.28)' : 'rgba(15, 118, 110, 0.16)',
    },
    spacing: {
      xs: 6,
      sm: 10,
      md: 14,
      lg: 18,
      xl: 24,
    },
    radius: {
      sm: 12,
      md: 18,
      lg: 24,
    },
  };
}
