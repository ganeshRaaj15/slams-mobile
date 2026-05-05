export type ThemeTone = 'light' | 'dark';

export type AppTheme = ReturnType<typeof buildTheme>;

export function buildTheme(tone: ThemeTone) {
  const dark = tone === 'dark';

  return {
    tone,
    colors: {
      background: dark ? '#101411' : '#f5f8f6',
      backgroundMuted: dark ? '#1d241f' : '#edf3ef',
      surface: dark ? '#171d19' : '#ffffff',
      surfaceMuted: dark ? 'rgba(255, 255, 255, 0.08)' : '#edf3ef',
      surfaceElevated: dark ? '#1d241f' : '#ffffff',
      surfaceAccent: dark ? 'rgba(45, 212, 191, 0.1)' : '#e7f5ef',
      surfaceOverlay: dark ? 'rgba(23, 29, 25, 0.92)' : 'rgba(255, 255, 255, 0.94)',
      border: dark ? 'rgba(222, 232, 226, 0.14)' : 'rgba(29, 41, 37, 0.12)',
      borderStrong: dark ? 'rgba(222, 232, 226, 0.24)' : 'rgba(29, 41, 37, 0.2)',
      text: dark ? '#e7eee9' : '#1b2622',
      heading: dark ? '#f6faf7' : '#111b17',
      textMuted: dark ? '#aab8b1' : '#66756f',
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
      glowPrimary: dark ? 'rgba(45, 212, 191, 0.2)' : 'rgba(15, 118, 110, 0.12)',
      glowAccent: dark ? 'rgba(20, 184, 166, 0.13)' : 'rgba(20, 184, 166, 0.1)',
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
