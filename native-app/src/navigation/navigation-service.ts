import { createNavigationContainerRef, StackActions } from '@react-navigation/native';

import type { MainTabParamList, RootStackParamList } from './types';

export const navigationRef = createNavigationContainerRef<RootStackParamList>();

export function navigateToTab(tab: keyof MainTabParamList) {
  if (!navigationRef.isReady()) {
    return;
  }

  (navigationRef as any).navigate('Main', { screen: tab });
}

export function navigateToStack<RouteName extends keyof RootStackParamList>(
  routeName: RouteName,
  params: RootStackParamList[RouteName],
) {
  if (!navigationRef.isReady()) {
    return;
  }

  (navigationRef as any).navigate(routeName, params);
}

export function pushToStack<RouteName extends keyof RootStackParamList>(
  routeName: RouteName,
  params: RootStackParamList[RouteName],
) {
  if (!navigationRef.isReady()) {
    return;
  }

  navigationRef.dispatch(StackActions.push(routeName as string, params));
}
