import { useQuery } from '@tanstack/react-query';
import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';

import { bootstrapRequest } from '../api/endpoints';
import { AnimatedPageSection } from '../components/animated-page-section';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatCard } from '../components/stat-card';
import { getShortcutIcon } from '../constants/navigation';
import { isOperationalRole, isStudentRole } from '../constants/roles';
import { useAuthStore } from '../state/auth-store';
import { textStyle } from '../theme/palette';
import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';

const routeMap: Record<string, string> = {
  labs: 'Labs',
  bookings: 'Bookings',
  approvals: 'Approvals',
  issues: 'Issues',
  maintenance: 'Maintenance',
  requests: 'Requests',
  notifications: 'Notifications',
  profile: 'Profile',
  reports: 'Reports',
  admin: 'AdminWorkspace',
};

export function HomeScreen() {
  const theme = useAppTheme();
  const responsive = useResponsiveLayout();
  const navigation = useNavigation<any>();
  const user = useAuthStore((state) => state.user);
  const isWideDashboard = responsive.isTabletLandscape || responsive.isExtraWide;
  const isWideStudentDashboard = isWideDashboard && !!user?.primary_role && isStudentRole(user.primary_role);

  const hour = new Date().getHours();
  const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
  const firstName = user?.full_name?.trim().split(' ')[0] || user?.username || 'there';

  const cardShadow = {
    elevation: 4,
    shadowColor: theme.colors.shadow,
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: theme.tone === 'dark' ? 0.28 : 0.08,
    shadowRadius: 22,
  };

  const bootstrapQuery = useQuery({
    queryKey: ['bootstrap'],
    queryFn: bootstrapRequest,
  });

  if (bootstrapQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading dashboard..." />
      </Screen>
    );
  }

  if (bootstrapQuery.isError || !bootstrapQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The dashboard summary could not be loaded from the backend."
          onRetry={() => {
            void bootstrapQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const { summary, navigation: navItems } = bootstrapQuery.data;
  const shortcutItems = navItems.filter((item) => item.id !== 'home');
  const displayShortcutItems = shortcutItems;

  const nextItemCard = summary.next_item ? (
    <View
      style={[
        styles.card,
        cardShadow,
        {
          backgroundColor: theme.colors.glassStrong,
          borderColor: theme.colors.glassBorder,
        },
      ]}
    >
      <Text
        style={[
          styles.sectionLabel,
          {
            color: theme.colors.primary,
          },
        ]}
      >
        Next item
      </Text>
      <Text
        style={[
          styles.cardTitle,
          {
            color: theme.colors.heading,
          },
        ]}
      >
        {summary.next_item.title}
      </Text>
      <Text
        style={[
          styles.cardSubtitle,
          {
            color: theme.colors.textMuted,
          },
        ]}
      >
        {summary.next_item.subtitle}
      </Text>
      {summary.next_item.meta ? (
        <Text
          style={[
            styles.cardMeta,
            {
              color: theme.colors.primary,
            },
          ]}
        >
          {summary.next_item.meta}
        </Text>
      ) : null}
    </View>
  ) : null;

  const shortcutsCard = displayShortcutItems.length > 0 ? (
    <View
      style={[
        styles.card,
        cardShadow,
        {
          backgroundColor: theme.colors.glassStrong,
          borderColor: theme.colors.glassBorder,
        },
      ]}
    >
      <Text
        style={[
          styles.sectionLabel,
          {
            color: theme.colors.primary,
          },
        ]}
      >
        Shortcuts
      </Text>
      <View style={[styles.shortcutsWrap, isWideDashboard ? styles.shortcutsWrapWide : null]}>
        {displayShortcutItems.map((item) => (
          <Pressable
            key={item.id}
            onPress={() => {
              const routeName = routeMap[item.id];
              if (routeName) {
                navigation.navigate(routeName);
              }
            }}
            style={[
              styles.shortcut,
              isWideDashboard ? styles.shortcutWide : null,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View
              style={[
                styles.shortcutIconWrap,
                {
                  backgroundColor: theme.colors.primarySoft,
                },
              ]}
            >
              <Ionicons color={theme.colors.primary} name={getShortcutIcon(item.id)} size={18} />
            </View>
            <Text
              style={[
                styles.shortcutText,
                {
                  color: theme.colors.heading,
                },
              ]}
            >
              {item.label}
            </Text>
          </Pressable>
        ))}
      </View>
      <Text style={[styles.shortcutsHint, { color: theme.colors.textMuted }]}>
        This grid mirrors your full mobile workspace, including sections that are also pinned in the bottom bar.
      </Text>
    </View>
  ) : (
    <EmptyState
      title="No shortcuts yet"
      message="This role does not have any extra mobile sections configured yet."
    />
  );

  const noteCard = (
    <View
      style={[
        styles.noteCard,
        cardShadow,
        {
          backgroundColor: theme.colors.glassStrong,
          borderColor: theme.colors.glassBorder,
        },
      ]}
    >
      <Text
        style={[
          styles.noteTitle,
          {
            color: theme.colors.primaryStrong,
          },
        ]}
      >
        Role summary
      </Text>
      <Text
        style={[
          styles.noteText,
          {
            color: theme.colors.heading,
          },
        ]}
      >
        {summary.message}
      </Text>
      {user?.primary_role && isStudentRole(user.primary_role) ? (
        <Text
          style={[
            styles.noteHint,
            {
              color: theme.colors.textMuted,
            },
          ]}
        >
          To book a lab: tap the "Book a Laboratory" button above, choose a lab, then tap "Book this Laboratory".
        </Text>
      ) : null}
      {user?.primary_role && isOperationalRole(user.primary_role) ? (
        <Text
          style={[
            styles.noteHint,
            {
              color: theme.colors.textMuted,
            },
          ]}
        >
          The bottom bar now adapts to screen width. Use the shortcuts above for the full workspace at any size.
        </Text>
      ) : null}
      {!user?.primary_role || !isOperationalRole(user.primary_role) ? (
        <Text
          style={[
            styles.noteHint,
            {
              color: theme.colors.textMuted,
            },
          ]}
        >
          Use the shortcut cards above whenever you need a section that is not pinned to the bottom bar.
        </Text>
      ) : null}
    </View>
  );

  const bookingPrompt = user?.primary_role && isStudentRole(user.primary_role) ? (
    <Pressable
      onPress={() => navigation.navigate('Labs')}
      style={[
        styles.bookingPrompt,
        isWideDashboard ? styles.bookingPromptWide : null,
        {
          backgroundColor: theme.colors.primary,
        },
      ]}
    >
      <View style={styles.bookingPromptContent}>
        <Ionicons color="#ffffff" name="flask-outline" size={28} />
        <View style={styles.bookingPromptText}>
          <Text style={styles.bookingPromptTitle}>Book a Laboratory</Text>
          <Text style={styles.bookingPromptSub}>Browse labs and submit a booking request</Text>
        </View>
      </View>
      <Ionicons color="rgba(255,255,255,0.7)" name="chevron-forward" size={20} />
    </Pressable>
  ) : null;

  return (
    <Screen maxWidth="wide">
      <AnimatedPageSection index={0} variant="hero">
        <View
          style={[
            styles.hero,
            cardShadow,
            {
              backgroundColor: theme.colors.glassStrong,
              borderColor: theme.colors.glassBorder,
            },
          ]}
        >
          <Text style={[textStyle.overline, { color: theme.colors.primary }]}>SLAMS Workspace</Text>
          <Text
            style={[
              textStyle.displayLg,
              styles.title,
              {
                color: theme.colors.heading,
              },
            ]}
          >
            {user?.full_name?.trim() || user?.username || 'SLAMS User'}
          </Text>
          <Text
            style={[
              textStyle.subheading,
              styles.subtitle,
              {
                color: theme.colors.primary,
              },
            ]}
          >
            {greeting}, {firstName}
          </Text>
          <Text
            style={[
              textStyle.caption,
              styles.meta,
              {
                color: theme.colors.textMuted,
              },
            ]}
          >
            {summary.attention_meta}
          </Text>
        </View>
      </AnimatedPageSection>

      {isWideStudentDashboard ? (
        <View style={styles.dashboardLeadRow}>
          <AnimatedPageSection axis="x" direction="forward" index={1} variant="section" style={styles.dashboardLeadPrimary}>
            {bookingPrompt}
          </AnimatedPageSection>
          <AnimatedPageSection axis="x" direction="backward" index={2} variant="section" style={styles.dashboardLeadSecondary}>
            {nextItemCard ?? noteCard}
          </AnimatedPageSection>
        </View>
      ) : bookingPrompt ? (
        <AnimatedPageSection index={1} variant="section">
          {bookingPrompt}
        </AnimatedPageSection>
      ) : null}

      <View style={[styles.statsRow, isWideDashboard && styles.statsRowWide]}>
        {summary.stats.map((stat, index) => (
          <AnimatedPageSection
            key={stat.id}
            index={index + 2}
            variant="card"
            style={isWideDashboard ? styles.statCardWrapWide : styles.statCardWrap}
          >
            <StatCard label={stat.label} tone={stat.tone} value={stat.value} flex={isWideDashboard} />
          </AnimatedPageSection>
        ))}
      </View>
      {isWideStudentDashboard ? (
        <View style={styles.contentGrid}>
          <AnimatedPageSection index={6} variant="section" style={styles.gridColWide}>
            {shortcutsCard}
          </AnimatedPageSection>
          {nextItemCard ? (
            <AnimatedPageSection index={7} variant="section" style={styles.gridCol}>
              {noteCard}
            </AnimatedPageSection>
          ) : null}
        </View>
      ) : isWideDashboard ? (
        <View style={styles.contentGrid}>
          <AnimatedPageSection axis="x" direction="forward" index={6} variant="section" style={styles.gridCol}>
            {shortcutsCard}
          </AnimatedPageSection>
          <AnimatedPageSection axis="x" direction="backward" index={7} variant="section" style={styles.gridCol}>
            {noteCard}
          </AnimatedPageSection>
          {nextItemCard ? (
            <AnimatedPageSection index={8} variant="section" style={styles.gridColNarrow}>
              {nextItemCard}
            </AnimatedPageSection>
          ) : null}
        </View>
      ) : (
        <>
          {nextItemCard ? (
            <AnimatedPageSection index={6} variant="section">
              {nextItemCard}
            </AnimatedPageSection>
          ) : null}
          <AnimatedPageSection index={7} variant="section">
            {shortcutsCard}
          </AnimatedPageSection>
          <AnimatedPageSection index={8} variant="section">
            {noteCard}
          </AnimatedPageSection>
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: {
    borderRadius: 22,
    borderWidth: 1,
    gap: 6,
    overflow: 'hidden',
    padding: 20,
  },
  title: {
    maxWidth: '90%',
  },
  subtitle: {
  },
  meta: {
    maxWidth: '92%',
  },
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    justifyContent: 'space-between',
  },
  statsRowWide: {
    flexWrap: 'nowrap',
  },
  statCardWrap: {
    width: '47%',
  },
  statCardWrapWide: {
    flex: 1,
    minWidth: 0,
  },
  contentGrid: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: 18,
  },
  dashboardLeadRow: {
    alignItems: 'stretch',
    flexDirection: 'row',
    gap: 18,
  },
  dashboardLeadPrimary: {
    flex: 1.6,
  },
  dashboardLeadSecondary: {
    flex: 1,
  },
  gridCol: {
    flex: 1,
  },
  gridColWide: {
    flex: 1.35,
  },
  gridColNarrow: {
    flex: 0.75,
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 8,
    overflow: 'hidden',
    padding: 16,
  },
  sectionLabel: {
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.6,
    textTransform: 'uppercase',
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  cardSubtitle: {
    fontSize: 14,
  },
  cardMeta: {
    fontSize: 13,
    fontWeight: '700',
  },
  shortcutsWrap: {
    gap: 10,
  },
  shortcutsWrapWide: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  shortcut: {
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    justifyContent: 'flex-start',
    gap: 10,
    paddingHorizontal: 12,
    paddingVertical: 14,
    width: '100%',
  },
  shortcutWide: {
    width: '48.8%',
  },
  shortcutIconWrap: {
    alignItems: 'center',
    borderRadius: 12,
    height: 34,
    justifyContent: 'center',
    width: 34,
  },
  shortcutText: {
    fontSize: 14,
    fontWeight: '700',
  },
  shortcutsHint: {
    fontSize: 12,
    lineHeight: 18,
  },
  bookingPrompt: {
    borderRadius: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    paddingVertical: 16,
  },
  bookingPromptWide: {
    width: '100%',
  },
  bookingPromptContent: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
    flex: 1,
  },
  bookingPromptText: {
    flex: 1,
    gap: 3,
  },
  bookingPromptTitle: {
    color: '#ffffff',
    fontSize: 17,
    fontWeight: '800',
  },
  bookingPromptSub: {
    color: 'rgba(255,255,255,0.8)',
    fontSize: 13,
  },
  noteCard: {
    borderRadius: 20,
    borderWidth: 1,
    gap: 8,
    overflow: 'hidden',
    padding: 18,
  },
  noteTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  noteText: {
    fontSize: 14,
    lineHeight: 20,
  },
  noteHint: {
    fontSize: 13,
    lineHeight: 19,
  },
});
