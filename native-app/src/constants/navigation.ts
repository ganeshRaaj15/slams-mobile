import { Ionicons } from '@expo/vector-icons';
import type { ComponentProps } from 'react';

import type { MainTabParamList } from '../navigation/types';

type IoniconName = ComponentProps<typeof Ionicons>['name'];
type MainTabName = keyof MainTabParamList;

export const TAB_LABELS: Record<MainTabName, string> = {
  Home: 'Home',
  Labs: 'Labs',
  Bookings: 'Bookings',
  Approvals: 'Queue',
  Issues: 'Issues',
  Maintenance: 'Maintenance',
  Requests: 'Requests',
  Reports: 'Reports',
  AdminWorkspace: 'Admin',
  Notifications: 'Alerts',
  Profile: 'Profile',
};

export const TAB_ICONS: Record<MainTabName, { active: IoniconName; inactive: IoniconName }> = {
  Home: { active: 'home', inactive: 'home-outline' },
  Labs: { active: 'flask', inactive: 'flask-outline' },
  Bookings: { active: 'calendar', inactive: 'calendar-outline' },
  Approvals: { active: 'checkmark-done-circle', inactive: 'checkmark-done-circle-outline' },
  Issues: { active: 'alert-circle', inactive: 'alert-circle-outline' },
  Maintenance: { active: 'build', inactive: 'build-outline' },
  Requests: { active: 'document-text', inactive: 'document-text-outline' },
  Reports: { active: 'stats-chart', inactive: 'stats-chart-outline' },
  AdminWorkspace: { active: 'grid', inactive: 'grid-outline' },
  Notifications: { active: 'notifications', inactive: 'notifications-outline' },
  Profile: { active: 'person', inactive: 'person-outline' },
};

const shortcutIcons: Record<string, IoniconName> = {
  home: 'home-outline',
  labs: 'flask-outline',
  bookings: 'calendar-outline',
  approvals: 'checkmark-done-circle-outline',
  issues: 'alert-circle-outline',
  maintenance: 'build-outline',
  requests: 'document-text-outline',
  notifications: 'notifications-outline',
  profile: 'person-outline',
  reports: 'stats-chart-outline',
  admin: 'grid-outline',
};

export function getShortcutIcon(shortcutId: string): IoniconName {
  return shortcutIcons[shortcutId] ?? 'ellipse-outline';
}
