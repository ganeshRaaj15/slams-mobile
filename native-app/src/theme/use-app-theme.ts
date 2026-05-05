import { useColorScheme } from 'react-native';

import { buildTheme } from './palette';

export function useAppTheme() {
  const scheme = useColorScheme();
  return buildTheme(scheme === 'dark' ? 'dark' : 'light');
}
