import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useState } from 'react';
import { ActivityIndicator, Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { BlurView } from 'expo-blur';
import { VideoView } from 'expo-video';

import { Screen } from '../components/screen';
import { useHeroVideo } from '../context/hero-video-context';
import type { RootStackParamList } from '../navigation/types';
import { TextField } from '../components/text-field';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';

const appLogo = require('../../assets/icon.png');

function LoginHeroCard() {
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
        intensity={theme.tone === 'dark' ? 28 : 34}
        tint={theme.tone === 'dark' ? 'dark' : 'light'}
        style={[
          styles.glassPanel,
          {
            borderColor: theme.tone === 'dark'
              ? 'rgba(255,255,255,0.10)'
              : 'rgba(255,255,255,0.65)',
            backgroundColor: theme.tone === 'dark'
              ? 'rgba(4,16,10,0.35)'
              : 'rgba(232,248,240,0.30)',
          },
        ]}
      >
        <Text
          style={[
            styles.eyebrow,
            {
              color: theme.tone === 'dark' ? '#2dd4bf' : theme.colors.primary,
            },
          ]}
        >
          SLAMS Mobile
        </Text>
        <Text
          style={[
            styles.title,
            {
              color: theme.tone === 'dark' ? '#f6faf7' : '#0d1b14',
            },
          ]}
        >
          Sign in to the mobile workspace
        </Text>
        <Text
          style={[
            styles.subtitle,
            {
              color: theme.tone === 'dark' ? 'rgba(220,236,228,0.90)' : 'rgba(18,44,32,0.85)',
            },
          ]}
        >
          Use your SLAMS account to access bookings, approvals, requests, notifications, and operational dashboards.
        </Text>
      </BlurView>
    </View>
  );
}

export function LoginScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const signIn = useAuthStore((state) => state.signIn);
  const submitOtp = useAuthStore((state) => state.submitOtp);
  const signInWithBiometrics = useAuthStore((state) => state.signInWithBiometrics);
  const authStatus = useAuthStore((state) => state.status);
  const authError = useAuthStore((state) => state.error);
  const biometric = useAuthStore((state) => state.biometric);
  const isOtpPending = authStatus === 'otp_pending';
  const responsive = useResponsiveLayout();
  const cardShadow = {
    elevation: 5,
    shadowColor: theme.colors.shadow,
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: theme.tone === 'dark' ? 0.28 : 0.08,
    shadowRadius: 22,
  };

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordVisible, setPasswordVisible] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [biometricSubmitting, setBiometricSubmitting] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);
  const [otpCode, setOtpCode] = useState('');

  const onSubmit = async () => {
    if (!email.trim() || !password) {
      setLocalError('Email and password are required.');
      return;
    }

    try {
      setLocalError(null);
      setSubmitting(true);
      await signIn(email, password);
    } catch (_error) {
      // Error state is stored centrally.
    } finally {
      setSubmitting(false);
    }
  };

  const onOtpSubmit = async () => {
    if (otpCode.trim().length !== 6) {
      setLocalError('Enter the 6-digit code sent to your email.');
      return;
    }

    try {
      setLocalError(null);
      setSubmitting(true);
      await submitOtp(otpCode.trim());
    } catch (_error) {
      // Error state is stored centrally.
    } finally {
      setSubmitting(false);
    }
  };

  const onBiometricSubmit = async () => {
    try {
      setLocalError(null);
      setBiometricSubmitting(true);
      await signInWithBiometrics();
    } catch (_error) {
      // Error state is stored centrally.
    } finally {
      setBiometricSubmitting(false);
    }
  };

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
      {responsive.isTablet ? <LoginHeroCard /> : null}
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
        {isOtpPending ? (
          <>
            <Text style={[styles.otpHeading, { color: theme.colors.heading }]}>
              Verification Required
            </Text>
            <Text style={[styles.otpHint, { color: theme.colors.textMuted }]}>
              A 6-digit code has been sent to your email. Enter it below to continue.
            </Text>
            <TextField
              autoCapitalize="none"
              keyboardType="number-pad"
              label="Verification Code"
              maxLength={6}
              onChangeText={setOtpCode}
              placeholder="000000"
              value={otpCode}
            />
            {localError ? (
              <Text style={[styles.error, { color: theme.colors.danger }]}>{localError}</Text>
            ) : null}
            {!localError && authError ? (
              <Text style={[styles.error, { color: theme.colors.danger }]}>{authError}</Text>
            ) : null}
            <Pressable
              disabled={submitting}
              onPress={onOtpSubmit}
              style={[styles.button, { backgroundColor: theme.colors.primary, opacity: submitting ? 0.7 : 1 }]}
            >
              {submitting ? (
                <ActivityIndicator color="#ffffff" />
              ) : (
                <Text style={styles.buttonText}>Verify Code</Text>
              )}
            </Pressable>
          </>
        ) : null}

        {!isOtpPending && biometric.isReady ? (
          <Pressable
            disabled={submitting || biometricSubmitting}
            onPress={onBiometricSubmit}
            style={[
              styles.biometricButton,
              {
                backgroundColor: theme.colors.primarySoft,
                borderColor: theme.colors.borderStrong,
                opacity: submitting || biometricSubmitting ? 0.7 : 1,
              },
            ]}
          >
            {biometricSubmitting ? (
              <ActivityIndicator color={theme.colors.primary} />
            ) : (
              <>
                <Ionicons color={theme.colors.primary} name="scan-outline" size={18} />
                <Text
                  style={[
                    styles.biometricButtonText,
                    {
                      color: theme.colors.primary,
                    },
                  ]}
                >
                  Unlock with Biometrics
                </Text>
              </>
            )}
          </Pressable>
        ) : null}

        {!isOtpPending && biometric.isEnabled && !biometric.isReady ? (
          <Text
            style={[
              styles.hint,
              {
                color: theme.colors.textMuted,
              },
            ]}
          >
            Biometric login is enabled for this device, but the saved session needs one successful password sign-in before it can be used again.
          </Text>
        ) : null}

        {!isOtpPending ? (
          <>
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
              placeholder="Enter your password"
              rightAccessory={
                <Pressable
                  accessibilityLabel={passwordVisible ? 'Hide password' : 'Show password'}
                  accessibilityRole="button"
                  hitSlop={8}
                  onPress={() => {
                    setPasswordVisible((current) => !current);
                  }}
                  style={styles.passwordToggle}
                >
                  <Ionicons
                    color={theme.colors.textMuted}
                    name={passwordVisible ? 'eye-off-outline' : 'eye-outline'}
                    size={20}
                  />
                </Pressable>
              }
              secureTextEntry={!passwordVisible}
              value={password}
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
              style={[
                styles.button,
                {
                  backgroundColor: theme.colors.primary,
                  opacity: submitting ? 0.7 : 1,
                },
              ]}
            >
              {submitting ? <ActivityIndicator color="#ffffff" /> : <Text style={styles.buttonText}>Sign In</Text>}
            </Pressable>

            <Pressable
              onPress={() => {
                navigation.navigate('Register');
              }}
              style={[
                styles.secondaryButton,
                {
                  backgroundColor: theme.colors.primarySoft,
                  borderColor: theme.colors.borderStrong,
                },
              ]}
            >
              <Text style={[styles.secondaryButtonText, { color: theme.colors.primary }]}>
                Create Account
              </Text>
            </Pressable>
          </>
        ) : null}
      </View>
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
    <Screen maxWidth="wide">
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
  biometricButton: {
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    minHeight: 48,
  },
  biometricButtonText: {
    fontSize: 15,
    fontWeight: '800',
  },
  error: {
    fontSize: 13,
    fontWeight: '600',
  },
  otpHeading: {
    fontSize: 18,
    fontWeight: '800',
  },
  otpHint: {
    fontSize: 13,
    lineHeight: 20,
  },
  hint: {
    fontSize: 12,
    lineHeight: 18,
  },
  passwordToggle: {
    alignItems: 'center',
    justifyContent: 'center',
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
});
