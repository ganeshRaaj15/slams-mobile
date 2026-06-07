import { Ionicons } from '@expo/vector-icons';
import type { ComponentProps } from 'react';

import type { MainTabParamList } from '../navigation/types';

type IoniconName = ComponentProps<typeof Ionicons>['name'];
export type MainTabName = keyof MainTabParamList;

export const TAB_LABELS: Record<MainTabName, string> = {
  Home: 'Home',
  Labs: 'Labs',
  Bookings: 'Bookings',
  Approvals: 'Queue',
  Issues: 'Issues',
  Maintenance: 'Maintenance',
  Reservations: 'Reservations',
  Requests: 'Requests',
  Reports: 'Reports',
  AdminWorkspace: 'Admin',
  Notifications: 'Alerts',
  Profile: 'Profile',
};

export const TAB_ICONS: Record<MainTabName, { active: IoniconName; inactive: IoniconName }> = {
  Home: { active: 'compass', inactive: 'compass-outline' },
  Labs: { active: 'business', inactive: 'business-outline' },
  Bookings: { active: 'bookmark', inactive: 'bookmark-outline' },
  Approvals: { active: 'shield-checkmark', inactive: 'shield-checkmark-outline' },
  Issues: { active: 'warning', inactive: 'warning-outline' },
  Maintenance: { active: 'construct', inactive: 'construct-outline' },
  Reservations: { active: 'calendar', inactive: 'calendar-outline' },
  Requests: { active: 'mail-unread', inactive: 'mail-unread-outline' },
  Reports: { active: 'bar-chart', inactive: 'bar-chart-outline' },
  AdminWorkspace: { active: 'layers', inactive: 'layers-outline' },
  Notifications: { active: 'megaphone', inactive: 'megaphone-outline' },
  Profile: { active: 'person-circle', inactive: 'person-circle-outline' },
};

const shortcutIcons: Record<string, IoniconName> = {
  home: 'compass-outline',
  labs: 'business-outline',
  bookings: 'bookmark-outline',
  approvals: 'shield-checkmark-outline',
  issues: 'warning-outline',
  maintenance: 'construct-outline',
  reservations: 'calendar-outline',
  requests: 'mail-unread-outline',
  notifications: 'megaphone-outline',
  profile: 'person-circle-outline',
  reports: 'bar-chart-outline',
  admin: 'layers-outline',
};

export function getShortcutIcon(shortcutId: string): IoniconName {
  return shortcutIcons[shortcutId] ?? 'ellipse-outline';
}

export function getAvailableTabsForRole(role: string): MainTabName[] {
  switch (role) {
    case 'student':
      return ['Home', 'Labs', 'Bookings', 'Notifications', 'Profile'];
    case 'staff':
      return ['Home', 'Labs', 'Issues', 'Notifications', 'Profile'];
    case 'pic':
      return ['Home', 'Approvals', 'Maintenance', 'Reservations', 'Requests', 'Labs', 'Notifications', 'Profile', 'Issues'];
    case 'manager':
      return ['Home', 'Approvals', 'Requests', 'Notifications', 'Profile'];
    case 'admin':
      return ['Home', 'AdminWorkspace', 'Reports', 'Notifications', 'Profile'];
    case 'external':
      return ['Home', 'Requests', 'Notifications', 'Profile'];
    default:
      return ['Home', 'Notifications', 'Profile'];
  }
}

type TabLayoutContext = {
  isTablet: boolean;
  isTabletLandscape: boolean;
  width: number;
};

export function getPrimaryTabsForRole(role: string, layout: TabLayoutContext): MainTabName[] {
  if (role === 'pic') {
    if (layout.isTabletLandscape || layout.width >= 1180) {
      return ['Home', 'Approvals', 'Maintenance', 'Reservations', 'Requests', 'Labs', 'Notifications', 'Profile'];
    }

    if (layout.isTablet || layout.width >= 820) {
      return ['Home', 'Approvals', 'Maintenance', 'Reservations', 'Requests', 'Notifications', 'Profile'];
    }

    return ['Home', 'Approvals', 'Maintenance', 'Reservations', 'Notifications', 'Profile'];
  }

  return getAvailableTabsForRole(role);
}
