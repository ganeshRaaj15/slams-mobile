import { NavigationContainer, DarkTheme as NavigationDarkTheme, DefaultTheme as NavigationDefaultTheme } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import { useEffect } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Animated, {
  interpolateColor,
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withSpring,
} from 'react-native-reanimated';

import { getAvailableTabsForRole, getPrimaryTabsForRole, TAB_ICONS, TAB_LABELS } from '../constants/navigation';
import { isExternalRole, isStudentRole } from '../constants/roles';
import { isOperationalRole } from '../constants/roles';
import { ApprovalDetailScreen } from '../screens/approval-detail-screen';
import { ApprovalsScreen } from '../screens/approvals-screen';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { BookingDetailScreen } from '../screens/booking-detail-screen';
import { BookingEditScreen } from '../screens/booking-edit-screen';
import { BookingComposerScreen } from '../screens/booking-composer-screen';
import { BookingsScreen } from '../screens/bookings-screen';
import { HomeScreen } from '../screens/home-screen';
import { ReportsScreen } from '../screens/reports-screen';
import { AdminWorkspaceScreen } from '../screens/admin-workspace-screen';
import { AdminLabsScreen } from '../screens/admin-labs-screen';
import { AdminLabEditorScreen } from '../screens/admin-lab-editor-screen';
import { AdminAssetsScreen } from '../screens/admin-assets-screen';
import { AdminAssetEditorScreen } from '../screens/admin-asset-editor-screen';
import { AdminUsersScreen } from '../screens/admin-users-screen';
import { AdminUserEditorScreen } from '../screens/admin-user-editor-screen';
import { AdminSettingsScreen } from '../screens/admin-settings-screen';
import { ExternalRequestReviewDetailScreen } from '../screens/external-request-review-detail-screen';
import { IssuesScreen } from '../screens/issues-screen';
import { LabDetailScreen } from '../screens/lab-detail-screen';
import { LabsScreen } from '../screens/labs-screen';
import { LoginScreen } from '../screens/login-screen';
import { MaintenanceFormScreen } from '../screens/maintenance-form-screen';
import { MaintenanceScreen } from '../screens/maintenance-screen';
import { navigationRef } from './navigation-service';
import { NotificationsScreen } from '../screens/notifications-screen';
import { ProfileScreen } from '../screens/profile-screen';
import { RegisterScreen } from '../screens/register-screen';
import { RequestFormScreen } from '../screens/request-form-screen';
import { RequestsScreen } from '../screens/requests-screen';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';
import { HeroVideoProvider } from '../context/hero-video-context';
import type { MainTabParamList, RootStackParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();
const Tabs = createBottomTabNavigator<MainTabParamList>();
type MainTabName = keyof MainTabParamList;

function tabSceneInterpolator({ current }: { current: { progress: { interpolate: (config: {
  inputRange: number[];
  outputRange: number[];
}) => any } } }) {
  return {
    sceneStyle: {
      opacity: current.progress.interpolate({
        inputRange: [-1, 0, 1],
        outputRange: [0.82, 1, 0.82],
      }),
      transform: [
        {
          translateY: current.progress.interpolate({
            inputRange: [-1, 0, 1],
            outputRange: [10, 0, 10],
          }),
        },
        {
          scale: current.progress.interpolate({
            inputRange: [-1, 0, 1],
            outputRange: [0.992, 1, 0.992],
          }),
        },
      ],
    },
  };
}

function SignOutHeaderAction() {
  const signOut = useAuthStore((state) => state.signOut);
  const theme = useAppTheme();

  return (
    <Pressable
      accessibilityHint="Signs out of SLAMS Mobile"
      accessibilityLabel="Sign out"
      hitSlop={10}
      onPress={() => {
        void signOut();
      }}
      style={[
        styles.headerActionButton,
        {
          backgroundColor: theme.colors.dangerSoft,
          borderColor: theme.colors.border,
        },
      ]}
    >
      <Ionicons color={theme.colors.danger} name="log-out-outline" size={18} />
    </Pressable>
  );
}

type TabIconBubbleProps = {
  color: string;
  focused: boolean;
  iconSize?: number;
  tabConfig: { active: string; inactive: string };
  theme: ReturnType<typeof useAppTheme>;
};

function TabIconBubble({ color, focused, iconSize = 20, tabConfig, theme }: TabIconBubbleProps) {
  const reduceMotion = useReducedMotion();
  const progress = useSharedValue(focused ? 1 : 0);

  useEffect(() => {
    progress.value = withSpring(focused ? 1 : 0, {
      damping: 18,
      stiffness: 280,
      mass: 0.7,
    });
  }, [focused, progress]);

  const animStyle = useAnimatedStyle(() => ({
    backgroundColor: interpolateColor(
      progress.value,
      [0, 1],
      ['transparent', theme.colors.tabBarActiveFill]
    ),
    borderColor: interpolateColor(
      progress.value,
      [0, 1],
      ['transparent', theme.colors.tabBarActiveBorder]
    ),
    transform: reduceMotion
      ? undefined
      : [{ scale: 0.88 + progress.value * 0.12 }],
  }));

  return (
    <Animated.View style={[styles.tabIconWrap, animStyle]}>
      <Ionicons
        color={color}
        name={focused ? tabConfig.active as never : tabConfig.inactive as never}
        size={iconSize}
      />
    </Animated.View>
  );
}

function MainTabs() {
  const user = useAuthStore((state) => state.user);
  const theme = useAppTheme();
  const responsive = useResponsiveLayout();
  const role = user?.primary_role ?? 'student';
  const primaryTabNames = getPrimaryTabsForRole(role, responsive);
  const primaryTabs = new Set<MainTabName>(primaryTabNames);
  const availableTabs = new Set<MainTabName>(getAvailableTabsForRole(role));
  const visibleTabCount = primaryTabNames.length;
  const tabOptions = (name: MainTabName) => (
    availableTabs.has(name) && primaryTabs.has(name)
      ? undefined
      : {
          tabBarItemStyle: {
            display: 'none' as const,
          },
        }
  );

  return (
    <Tabs.Navigator
      detachInactiveScreens={false}
      screenOptions={({ route }) => {
        const tabConfig = TAB_ICONS[route.name as keyof MainTabParamList];

        return {
          animation: 'fade',
          animationEnabled: true,
          lazy: true,
          sceneStyleInterpolator: tabSceneInterpolator,
          transitionSpec: {
            animation: 'timing',
            config: {
              duration: 280,
            },
          },
          sceneStyle: {
            backgroundColor: theme.colors.background,
          },
          headerStyle: {
            backgroundColor: theme.colors.surfaceOverlay,
          },
          headerShadowVisible: false,
          headerTintColor: theme.colors.heading,
          headerTitleStyle: {
            color: theme.colors.heading,
            fontSize: responsive.isTablet ? 20 : 18,
            fontFamily: theme.fonts.displayBold,
          },
          headerLeftContainerStyle: {
            paddingLeft: responsive.isTablet ? 20 : 4,
          },
          headerRightContainerStyle: {
            paddingRight: responsive.isTablet ? 20 : 4,
          },
          headerRight: () => <SignOutHeaderAction />,
          tabBarHideOnKeyboard: true,
          tabBarLabel: TAB_LABELS[route.name as keyof MainTabParamList],
          tabBarLabelPosition: 'below-icon',
          tabBarActiveTintColor: theme.colors.primary,
          tabBarInactiveTintColor: theme.colors.textMuted,
          tabBarLabelStyle: {
            fontSize: responsive.isTablet ? (visibleTabCount >= 7 ? 12 : 13) : 11,
            fontFamily: theme.fonts.bodyBold,
            letterSpacing: 0.2,
            lineHeight: responsive.isTablet ? 16 : 14,
            marginBottom: responsive.isTablet ? 2 : 2,
            marginLeft: 0,
          },
          tabBarStyle: {
            backgroundColor: theme.colors.tabBar,
            borderTopColor: theme.colors.tabBarBorder,
            borderTopLeftRadius: responsive.isTablet ? 0 : 24,
            borderTopRightRadius: responsive.isTablet ? 0 : 24,
            borderTopWidth: 1,
            elevation: 18,
            height: responsive.isTablet ? (visibleTabCount >= 7 ? 80 : 84) : 80,
            overflow: 'hidden',
            paddingBottom: responsive.isTablet ? 8 : 12,
            paddingHorizontal: responsive.isTablet ? (visibleTabCount >= 7 ? 8 : 12) : 0,
            paddingTop: responsive.isTablet ? 8 : 10,
            shadowColor: theme.colors.shadow,
            shadowOffset: { width: 0, height: -6 },
            shadowOpacity: theme.tone === 'dark' ? 0.34 : 0.12,
            shadowRadius: 18,
          },
          tabBarIconStyle: {
            marginBottom: 0,
            marginTop: 0,
          },
          tabBarItemStyle: {
            alignItems: 'center',
            flexDirection: 'column',
            gap: 0,
            justifyContent: 'center',
            minHeight: responsive.isTablet ? 54 : undefined,
            paddingHorizontal: responsive.isTablet ? (visibleTabCount >= 7 ? 2 : 4) : 4,
            paddingVertical: responsive.isTablet ? 4 : 2,
          },
          tabBarIcon: ({ color, focused }) => (
            <TabIconBubble
              color={color}
              focused={focused}
              iconSize={responsive.isTablet ? (visibleTabCount >= 7 ? 22 : 24) : 20}
              tabConfig={tabConfig}
              theme={theme}
            />
          ),
        };
      }}
    >
      <Tabs.Screen name="Home" component={HomeScreen} options={tabOptions('Home')} />
      {role !== 'admin' ? <Tabs.Screen name="Labs" component={LabsScreen} options={tabOptions('Labs')} /> : null}
      {isStudentRole(role) ? <Tabs.Screen name="Bookings" component={BookingsScreen} options={tabOptions('Bookings')} /> : null}
      {isOperationalRole(role) && role !== 'admin' ? (
        <Tabs.Screen
          name="Approvals"
          component={ApprovalsScreen}
          options={{
            title: 'Approvals',
            ...tabOptions('Approvals'),
          }}
        />
      ) : null}
      {role === 'student' || role === 'staff' || role === 'pic' ? (
        <Tabs.Screen name="Issues" component={IssuesScreen} options={tabOptions('Issues')} />
      ) : null}
      {role === 'pic' ? <Tabs.Screen name="Maintenance" component={MaintenanceScreen} options={tabOptions('Maintenance')} /> : null}
      {(isExternalRole(role) || role === 'pic' || role === 'manager') ? (
        <Tabs.Screen
          name="Requests"
          component={RequestsScreen}
          options={{
            title: isExternalRole(role) ? 'My Requests' : 'External Requests',
            ...tabOptions('Requests'),
          }}
        />
      ) : null}
      {role === 'admin' ? (
        <Tabs.Screen
          name="Reports"
          component={ReportsScreen}
          options={{
            title: 'Reports',
            ...tabOptions('Reports'),
          }}
        />
      ) : null}
      {role === 'admin' ? (
        <Tabs.Screen
          name="AdminWorkspace"
          component={AdminWorkspaceScreen}
          options={{
            title: 'Admin Workspace',
            ...tabOptions('AdminWorkspace'),
          }}
        />
      ) : null}
      <Tabs.Screen name="Notifications" component={NotificationsScreen} options={tabOptions('Notifications')} />
      <Tabs.Screen name="Profile" component={ProfileScreen} options={tabOptions('Profile')} />
    </Tabs.Navigator>
  );
}

type RootNavigatorProps = {
  onReady?: () => void;
};

export function RootNavigator({ onReady }: RootNavigatorProps) {
  const status = useAuthStore((state) => state.status);
  const theme = useAppTheme();
  const responsive = useResponsiveLayout();

  const navigationTheme = {
    ...(theme.tone === 'dark' ? NavigationDarkTheme : NavigationDefaultTheme),
    colors: {
      ...(theme.tone === 'dark' ? NavigationDarkTheme.colors : NavigationDefaultTheme.colors),
      background: theme.colors.background,
      card: theme.colors.surface,
      text: theme.colors.text,
      border: theme.colors.border,
      primary: theme.colors.primary,
      notification: theme.colors.danger,
    },
  };

  if (status === 'booting') {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading workspace..." />
      </Screen>
    );
  }

  const authStack = (
    <Stack.Navigator screenOptions={{ headerShown: false, animation: 'fade', animationDuration: 220 }}>
      <Stack.Screen name="Auth" component={LoginScreen} />
      <Stack.Screen name="Register" component={RegisterScreen} />
    </Stack.Navigator>
  );

  return (
    <NavigationContainer ref={navigationRef} onReady={onReady} theme={navigationTheme}>
      {status === 'authenticated' ? (
        <Stack.Navigator
          screenOptions={{
            headerRight: () => <SignOutHeaderAction />,
            headerTitleStyle: {
              fontSize: responsive.isTablet ? 20 : 18,
            },
            animation: 'slide_from_right',
            animationDuration: 280,
            freezeOnBlur: true,
            gestureEnabled: true,
            fullScreenGestureEnabled: true,
          }}
        >
          <Stack.Screen name="Main" component={MainTabs} options={{ headerShown: false }} />
          <Stack.Screen name="LabDetail" component={LabDetailScreen} options={{ title: 'Laboratory' }} />
          <Stack.Screen name="BookingDetail" component={BookingDetailScreen} options={{ title: 'Booking Details' }} />
          <Stack.Screen name="BookingEdit" component={BookingEditScreen} options={{ title: 'Edit Booking' }} />
          <Stack.Screen name="BookingComposer" component={BookingComposerScreen} options={{ title: 'New Booking' }} />
          <Stack.Screen name="ApprovalDetail" component={ApprovalDetailScreen} options={{ title: 'Approval Review' }} />
          <Stack.Screen name="Reports" component={ReportsScreen} options={{ title: 'Reports' }} />
          <Stack.Screen name="AdminWorkspace" component={AdminWorkspaceScreen} options={{ title: 'Admin Workspace' }} />
          <Stack.Screen name="AdminLabs" component={AdminLabsScreen} options={{ title: 'Laboratories' }} />
          <Stack.Screen
            name="AdminLabEditor"
            component={AdminLabEditorScreen}
            options={({ route }) => ({
              title: route.params?.labId ? 'Laboratory Details' : 'New Laboratory',
            })}
          />
          <Stack.Screen name="AdminAssets" component={AdminAssetsScreen} options={{ title: 'Assets' }} />
          <Stack.Screen
            name="AdminAssetEditor"
            component={AdminAssetEditorScreen}
            options={({ route }) => ({
              title: route.params?.assetId ? 'Asset Details' : 'New Asset',
            })}
          />
          <Stack.Screen name="AdminUsers" component={AdminUsersScreen} options={{ title: 'User Management' }} />
          <Stack.Screen name="AdminUserEditor" component={AdminUserEditorScreen} options={{ title: 'User Details' }} />
          <Stack.Screen name="AdminSettings" component={AdminSettingsScreen} options={{ title: 'System Settings' }} />
          <Stack.Screen
            name="ExternalRequestReviewDetail"
            component={ExternalRequestReviewDetailScreen}
            options={{ title: 'Review External Request' }}
          />
          <Stack.Screen
            name="MaintenanceForm"
            component={MaintenanceFormScreen}
            options={({ route }) => ({
              title: route.params?.maintenanceId ? 'Maintenance Case' : 'Plan Maintenance',
            })}
          />
          <Stack.Screen
            name="RequestForm"
            component={RequestFormScreen}
            options={({ route }) => ({
              title: route.params?.requestId ? 'Update Request' : 'New Request',
            })}
          />
        </Stack.Navigator>
      ) : (
        responsive.isTablet ? <HeroVideoProvider>{authStack}</HeroVideoProvider> : authStack
      )}
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  headerActionButton: {
    alignItems: 'center',
    borderRadius: 12,
    borderWidth: 1,
    height: 36,
    justifyContent: 'center',
    marginRight: 2,
    width: 36,
  },
  tabIconWrap: {
    alignItems: 'center',
    borderRadius: 16,
    borderWidth: 1,
    height: 34,
    justifyContent: 'center',
    minWidth: 42,
    paddingHorizontal: 10,
  },
});
