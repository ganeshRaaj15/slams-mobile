import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useState } from 'react';
import { ActivityIndicator, Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { BlurView } from 'expo-blur';
import { VideoView } from 'expo-video';

import { Screen } from '../components/screen';
import { TextField } from '../components/text-field';
import type { RootStackParamList } from '../navigation/types';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';
import { useHeroVideo } from '../context/hero-video-context';

const appLogo = require('../../assets/icon.png');

function RegisterPhoneHeader() {
  const theme = useAppTheme();

  return (
    <View style={styles.phoneHeader}>
      <View
        style={[
          styles.phoneLogoBadge,
          {
            backgroundColor: theme.tone === 'dark'
              ? 'rgba(255,255,255,0.08)'
              : 'rgba(245,255,251,0.92)',
            borderColor: theme.tone === 'dark'
              ? 'rgba(255,255,255,0.12)'
              : 'rgba(13, 96, 77, 0.10)',
          },
        ]}
      >
        <Image source={appLogo} style={styles.phoneLogoImage} resizeMode="contain" />
      </View>
      <Text
        style={[
          styles.eyebrow,
          { color: theme.tone === 'dark' ? '#2dd4bf' : theme.colors.primary },
        ]}
      >
        SLAMS Mobile
      </Text>
      <Text
        style={[
          styles.phoneTitle,
          { color: theme.tone === 'dark' ? '#f6faf7' : '#0d1b14' },
        ]}
      >
        Create your account
      </Text>
    </View>
  );
}

function RegisterHeroCard() {
  const theme = useAppTheme();
  const responsive = useResponsiveLayout();
  const { dayPlayer, nightPlayer } = useHeroVideo();
  const heroPlayer = theme.tone === 'dark' ? nightPlayer : dayPlayer;
  const cardShadow = {
    elevation: 5,
    shadowColor: theme.colors.shadow,
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: theme.tone === 'dark' ? 0.28 : 0.08,
    shadowRadius: 22,
  };

  return (
    <View
      style={[
        styles.hero,
        responsive.isTabletLandscape ? styles.heroWide : null,
        cardShadow,
        {
          backgroundColor: theme.colors.surfaceAccent,
          borderColor: theme.colors.borderStrong,
          overflow: 'hidden',
        },
      ]}
    >
      <VideoView
        style={StyleSheet.absoluteFillObject}
        player={heroPlayer}
        nativeControls={false}
        contentFit="cover"
        allowsFullscreen={false}
        allowsPictureInPicture={false}
      />
      <BlurView
        intensity={theme.tone === 'dark' ? 32 : 42}
        tint="dark"
        style={[
          styles.glassPanel,
          {
            borderColor: theme.tone === 'dark'
              ? 'rgba(255,255,255,0.10)'
              : 'rgba(255,255,255,0.22)',
            backgroundColor: theme.tone === 'dark'
              ? 'rgba(4,16,10,0.42)'
              : 'rgba(6,24,18,0.50)',
          },
        ]}
      >
        <Text style={[styles.eyebrow, styles.heroEyebrow]}>
          SLAMS Mobile
        </Text>
        <Text style={[styles.title, { color: '#f6faf7' }]}>
          Create your account
        </Text>
        <Text style={[styles.subtitle, { color: 'rgba(232,243,237,0.92)' }]}>
          Use your institutional student email for student access. Other sign-ups default to external access.
        </Text>
      </BlurView>
    </View>
  );
}

export function RegisterScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const signUp = useAuthStore((state) => state.signUp);
  const authError = useAuthStore((state) => state.error);
  const responsive = useResponsiveLayout();
  const cardShadow = {
    elevation: 5,
    shadowColor: theme.colors.shadow,
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: theme.tone === 'dark' ? 0.28 : 0.08,
    shadowRadius: 22,
  };

  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  const onSubmit = async () => {
    const normalizedUsername = username.trim();
    const normalizedEmail = email.trim();

    if (!normalizedUsername || !normalizedEmail || !password || !passwordConfirm) {
      setLocalError('Username, email, password, and password confirmation are required.');
      return;
    }

    if (password !== passwordConfirm) {
      setLocalError('Password confirmation does not match.');
      return;
    }

    try {
      setLocalError(null);
      setSubmitting(true);
      await signUp({
        username: normalizedUsername,
        email: normalizedEmail,
        password,
        password_confirm: passwordConfirm,
      });
    } catch (_error) {
      // Error state is stored centrally.
    } finally {
      setSubmitting(false);
    }
  };

  const formCard = (
    <View
      style={[
        styles.formCard,
        responsive.isTabletLandscape ? styles.formCardWide : null,
        cardShadow,
        {
          backgroundColor: theme.colors.surfaceOverlay,
          borderColor: theme.colors.border,
        },
      ]}
    >
      {responsive.isTablet ? (
        <View
          style={[
            styles.logoBadge,
            styles.formLogoBadge,
            {
              backgroundColor: theme.tone === 'dark'
                ? 'rgba(255,255,255,0.10)'
                : 'rgba(245,255,251,0.92)',
              borderColor: theme.tone === 'dark'
                ? 'rgba(255,255,255,0.14)'
                : 'rgba(13, 96, 77, 0.10)',
            },
          ]}
        >
          <Image source={appLogo} style={styles.logoImage} resizeMode="contain" />
        </View>
      ) : null}
      <TextField
        autoCapitalize="none"
        autoCorrect={false}
        label="Username"
        onChangeText={setUsername}
        placeholder="Choose a username"
        value={username}
      />
      <TextField
        autoCapitalize="none"
        autoCorrect={false}
        keyboardType="email-address"
        label="Email"
        onChangeText={setEmail}
        placeholder="name@example.com"
        value={email}
      />
      <TextField
        label="Password"
        onChangeText={setPassword}
        placeholder="Create a password"
        secureTextEntry
        value={password}
      />
      <TextField
        label="Confirm Password"
        onChangeText={setPasswordConfirm}
        placeholder="Repeat your password"
        secureTextEntry
        value={passwordConfirm}
      />

      {localError ? (
        <Text style={[styles.error, { color: theme.colors.danger }]}>{localError}</Text>
      ) : null}

      {!localError && authError ? (
        <Text style={[styles.error, { color: theme.colors.danger }]}>{authError}</Text>
      ) : null}

      <Pressable
        disabled={submitting}
        onPress={onSubmit}
        style={[styles.button, { backgroundColor: theme.colors.primary, opacity: submitting ? 0.7 : 1 }]}
      >
        {submitting ? <ActivityIndicator color="#ffffff" /> : <Text style={styles.buttonText}>Create Account</Text>}
      </Pressable>

      <Pressable
        onPress={() => {
          navigation.navigate('Auth');
        }}
        style={[styles.secondaryButton, { backgroundColor: theme.colors.primarySoft, borderColor: theme.colors.borderStrong }]}
      >
        <Text style={[styles.secondaryButtonText, { color: theme.colors.primary }]}>Back to Sign In</Text>
      </Pressable>
    </View>
  );

  const landscapeCardHeight = responsive.isTabletLandscape ? responsive.height - 80 : undefined;

  const layout = (
    <View
      style={[
        styles.layout,
        responsive.isTabletLandscape
          ? [styles.layoutWide, landscapeCardHeight != null && { minHeight: landscapeCardHeight }]
          : null,
      ]}
    >
      {responsive.isTablet ? <RegisterHeroCard /> : <RegisterPhoneHeader />}
      {formCard}
    </View>
  );

  if (responsive.isTablet) {
    return (
      <Screen scroll={false} maxWidth="wide" centerContent>
        {layout}
      </Screen>
    );
  }

  return (
    <Screen scroll={false} maxWidth="wide" centerContent>
      {layout}
    </Screen>
  );
}

const styles = StyleSheet.create({
  layout: {
    gap: 18,
  },
  layoutWide: {
    alignItems: 'stretch',
    flexDirection: 'row',
  },
  hero: {
    borderRadius: 22,
    borderWidth: 1,
    justifyContent: 'flex-end',
    padding: 16,
  },
  heroWide: {
    flex: 1,
    justifyContent: 'flex-end',
    minHeight: 400,
    padding: 20,
  },
  glassPanel: {
    borderRadius: 14,
    borderWidth: 1,
    gap: 6,
    overflow: 'hidden',
    padding: 16,
  },
  logoBadge: {
    alignItems: 'center',
    borderRadius: 22,
    borderWidth: 1,
    height: 96,
    justifyContent: 'center',
    marginBottom: 12,
    width: 96,
  },
  logoImage: {
    height: 72,
    width: 72,
  },
  formLogoBadge: {
    alignSelf: 'center',
    marginBottom: 4,
  },
  eyebrow: {
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  heroEyebrow: {
    color: '#c7fff0',
  },
  title: {
    fontSize: 28,
    fontWeight: '800',
    lineHeight: 34,
  },
  subtitle: {
    fontSize: 15,
    lineHeight: 22,
  },
  formCard: {
    borderRadius: 22,
    borderWidth: 1,
    gap: 14,
    padding: 20,
  },
  formCardWide: {
    flex: 1,
    justifyContent: 'center',
    maxWidth: 560,
    padding: 28,
  },
  error: {
    fontSize: 13,
    fontWeight: '600',
  },
  button: {
    alignItems: 'center',
    borderRadius: 14,
    justifyContent: 'center',
    minHeight: 48,
  },
  buttonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
  secondaryButton: {
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 14,
    justifyContent: 'center',
    minHeight: 48,
  },
  secondaryButtonText: {
    fontSize: 15,
    fontWeight: '800',
  },
  phoneHeader: {
    alignItems: 'center',
    gap: 8,
    paddingBottom: 8,
  },
  phoneLogoBadge: {
    alignItems: 'center',
    borderRadius: 28,
    borderWidth: 1,
    height: 120,
    justifyContent: 'center',
    marginBottom: 10,
    width: 120,
  },
  phoneLogoImage: {
    height: 90,
    width: 90,
  },
  phoneTitle: {
    fontSize: 26,
    fontWeight: '800',
    lineHeight: 32,
    textAlign: 'center',
  },
});
