import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Ionicons } from '@expo/vector-icons';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { WebView } from 'react-native-webview';

import { bootstrapRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { StatCard } from '../components/stat-card';
import { getShortcutIcon } from '../constants/navigation';
import { isOperationalRole } from '../constants/roles';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';

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

const CAMPUS_VIDEO_EMBED_URL = 'https://www.youtube-nocookie.com/embed/Car8y6iPSRg?autoplay=1&rel=0';

export function HomeScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const user = useAuthStore((state) => state.user);
  const [isCampusVideoOpen, setCampusVideoOpen] = useState(false);
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

  return (
    <Screen>
      <View
        style={[
          styles.hero,
          cardShadow,
          {
            backgroundColor: theme.colors.surfaceAccent,
            borderColor: theme.colors.borderStrong,
          },
        ]}
      >
        <Text
          style={[
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
            styles.subtitle,
            {
              color: theme.colors.primary,
            },
          ]}
        >
          {summary.attention_label}
        </Text>
        <Text
          style={[
            styles.meta,
            {
              color: theme.colors.textMuted,
            },
          ]}
        >
          {summary.attention_meta}
        </Text>
        <Pressable
          onPress={() => {
            setCampusVideoOpen(true);
          }}
          style={[
            styles.videoButton,
            {
              backgroundColor: theme.colors.primarySoft,
              borderColor: theme.colors.borderStrong,
            },
          ]}
        >
          <View
            style={[
              styles.videoButtonIcon,
              {
                backgroundColor: theme.colors.surface,
              },
            ]}
          >
            <Ionicons color={theme.colors.primary} name="play-circle" size={18} />
          </View>
          <View style={styles.videoButtonCopy}>
            <Text
              style={[
                styles.videoButtonTitle,
                {
                  color: theme.colors.heading,
                },
              ]}
            >
              Watch Campus Video
            </Text>
            <Text
              style={[
                styles.videoButtonMeta,
                {
                  color: theme.colors.textMuted,
                },
              ]}
            >
              Open the UTHM aerial footage inside SLAMS.
            </Text>
          </View>
        </Pressable>
      </View>

      <View style={styles.statsRow}>
        {summary.stats.map((stat) => (
          <StatCard key={stat.id} label={stat.label} tone={stat.tone} value={stat.value} />
        ))}
      </View>

      {summary.next_item ? (
        <View
          style={[
            styles.card,
            cardShadow,
            {
              backgroundColor: theme.colors.surfaceOverlay,
              borderColor: theme.colors.border,
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
      ) : null}

      {navItems.length > 1 ? (
        <View
          style={[
            styles.card,
            cardShadow,
            {
              backgroundColor: theme.colors.surfaceOverlay,
              borderColor: theme.colors.border,
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
          <View style={styles.shortcutsWrap}>
            {navItems
              .filter((item) => item.id !== 'home')
              .map((item) => (
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
        </View>
      ) : (
        <EmptyState
          title="No shortcuts yet"
          message="This role does not have any extra mobile sections configured yet."
        />
      )}

      <View
        style={[
          styles.noteCard,
          cardShadow,
          {
            backgroundColor: theme.colors.surfaceAccent,
            borderColor: theme.colors.borderStrong,
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
        {user?.primary_role && isOperationalRole(user.primary_role) ? (
          <Text
            style={[
              styles.noteHint,
              {
                color: theme.colors.textMuted,
              },
            ]}
          >
            Use the shortcuts above to move quickly between the sections available to your role.
          </Text>
        ) : null}
      </View>

      <Modal
        animationType="slide"
        onRequestClose={() => {
          setCampusVideoOpen(false);
        }}
        visible={isCampusVideoOpen}
      >
        <View
          style={[
            styles.videoModalRoot,
            {
              backgroundColor: theme.colors.background,
            },
          ]}
        >
          <View
            style={[
              styles.videoModalHeader,
              {
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.videoModalCopy}>
              <Text
                style={[
                  styles.videoModalTitle,
                  {
                    color: theme.colors.heading,
                  },
                ]}
              >
                UTHM aerial footage
              </Text>
              <Text
                style={[
                  styles.videoModalMeta,
                  {
                    color: theme.colors.textMuted,
                  },
                ]}
              >
                Watch the campus video without leaving the app.
              </Text>
            </View>
            <Pressable
              accessibilityHint="Closes the campus video"
              accessibilityLabel="Close campus video"
              hitSlop={10}
              onPress={() => {
                setCampusVideoOpen(false);
              }}
              style={[
                styles.videoModalClose,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                  borderColor: theme.colors.border,
                },
              ]}
            >
              <Ionicons color={theme.colors.heading} name="close" size={20} />
            </Pressable>
          </View>

          <View
            style={[
              styles.videoFrameShell,
              {
                borderColor: theme.colors.borderStrong,
                backgroundColor: theme.colors.surface,
              },
            ]}
          >
            {isCampusVideoOpen ? (
              <WebView
                allowsFullscreenVideo
                mediaPlaybackRequiresUserAction={false}
                source={{ uri: CAMPUS_VIDEO_EMBED_URL }}
                style={styles.videoFrame}
              />
            ) : null}
          </View>
        </View>
      </Modal>
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: {
    borderRadius: 22,
    borderWidth: 1,
    gap: 6,
    padding: 20,
  },
  title: {
    fontSize: 28,
    fontWeight: '800',
  },
  subtitle: {
    fontSize: 18,
    fontWeight: '700',
  },
  meta: {
    fontSize: 14,
    lineHeight: 20,
  },
  videoButton: {
    alignItems: 'center',
    borderRadius: 16,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 12,
    marginTop: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  videoButtonIcon: {
    alignItems: 'center',
    borderRadius: 14,
    height: 38,
    justifyContent: 'center',
    width: 38,
  },
  videoButtonCopy: {
    flex: 1,
    gap: 2,
  },
  videoButtonTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  videoButtonMeta: {
    fontSize: 13,
    lineHeight: 18,
  },
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    justifyContent: 'space-between',
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 8,
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
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  shortcut: {
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    flexGrow: 1,
    gap: 10,
    minWidth: '46%',
    paddingHorizontal: 12,
    paddingVertical: 12,
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
  noteCard: {
    borderRadius: 20,
    borderWidth: 1,
    gap: 8,
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
  videoModalRoot: {
    flex: 1,
    gap: 16,
    padding: 18,
    paddingTop: 22,
  },
  videoModalHeader: {
    alignItems: 'flex-start',
    borderBottomWidth: 1,
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    paddingBottom: 14,
  },
  videoModalCopy: {
    flex: 1,
    gap: 4,
  },
  videoModalTitle: {
    fontSize: 20,
    fontWeight: '800',
  },
  videoModalMeta: {
    fontSize: 14,
    lineHeight: 20,
  },
  videoModalClose: {
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1,
    height: 40,
    justifyContent: 'center',
    width: 40,
  },
  videoFrameShell: {
    borderRadius: 22,
    borderWidth: 1,
    flex: 1,
    overflow: 'hidden',
  },
  videoFrame: {
    flex: 1,
  },
});
