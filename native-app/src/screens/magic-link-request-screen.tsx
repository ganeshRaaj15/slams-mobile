import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useState } from 'react';
import { ActivityIndicator, Image, Pressable, StyleSheet, Text, View } from 'react-native';

import { Screen } from '../components/screen';
import { TextField } from '../components/text-field';
import type { RootStackParamList } from '../navigation/types';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';

const appLogo = require('../../assets/icon.png');

export function MagicLinkRequestScreen() {
  const theme = useAppTheme();
  const responsive = useResponsiveLayout();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const requestMagicLink = useAuthStore((state) => state.requestMagicLink);

  const [account, setAccount] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const onSubmit = async () => {
    if (!account.trim()) {
      setError('Enter the email address or username on your account.');
      setSuccess(null);
      return;
    }

    try {
      setSubmitting(true);
      setError(null);
      const message = await requestMagicLink(account.trim());
      setSuccess(message);
    } catch (_error) {
      setSuccess(null);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Screen scroll={false} centerContent>
      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surfaceOverlay,
            borderColor: theme.colors.border,
            shadowColor: theme.colors.shadow,
          },
        ]}
      >
        <View
          style={[
            styles.logoBadge,
            {
              backgroundColor: theme.tone === 'dark' ? 'rgba(255,255,255,0.10)' : 'rgba(245,255,251,0.92)',
              borderColor: theme.tone === 'dark' ? 'rgba(255,255,255,0.14)' : 'rgba(13, 96, 77, 0.10)',
            },
          ]}
        >
          <Image source={appLogo} style={styles.logoImage} resizeMode="contain" />
        </View>

        <Text style={[styles.eyebrow, { color: theme.colors.primary }]}>Secure Recovery</Text>
        <Text style={[styles.title, { color: theme.colors.heading }]}>Find your account</Text>
        <Text style={[styles.subtitle, { color: theme.colors.textMuted }]}>
          Enter your email or username and we will send a one-time sign-in link that expires in 15 minutes.
        </Text>

        <TextField
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          label="Email or Username"
          onChangeText={setAccount}
          placeholder="you@example.com or username"
          value={account}
        />

        {error ? <Text style={[styles.message, { color: theme.colors.danger }]}>{error}</Text> : null}
        {success ? <Text style={[styles.message, { color: theme.colors.success }]}>{success}</Text> : null}

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
          {submitting ? <ActivityIndicator color="#ffffff" /> : <Text style={styles.buttonText}>Send Secure Link</Text>}
        </Pressable>

        <Pressable
          onPress={() => {
            navigation.goBack();
          }}
          style={[
            styles.secondaryButton,
            {
              backgroundColor: theme.colors.primarySoft,
              borderColor: theme.colors.borderStrong,
            },
          ]}
        >
          <Text style={[styles.secondaryButtonText, { color: theme.colors.primary }]}>Back to Sign In</Text>
        </Pressable>

        {responsive.isTablet ? (
          <Text style={[styles.footnote, { color: theme.colors.textMuted }]}>
            Open the email on this device to finish signing in directly inside SLAMS Mobile.
          </Text>
        ) : null}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 22,
    borderWidth: 1,
    gap: 14,
    maxWidth: 560,
    padding: 24,
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.1,
    shadowRadius: 22,
    width: '100%',
  },
  logoBadge: {
    alignItems: 'center',
    alignSelf: 'center',
    borderRadius: 22,
    borderWidth: 1,
    height: 96,
    justifyContent: 'center',
    marginBottom: 4,
    width: 96,
  },
  logoImage: {
    height: 72,
    width: 72,
  },
  eyebrow: {
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1,
    textAlign: 'center',
    textTransform: 'uppercase',
  },
  title: {
    fontSize: 26,
    fontWeight: '800',
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 14,
    lineHeight: 21,
    textAlign: 'center',
  },
  message: {
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
    borderRadius: 14,
    borderWidth: 1,
    justifyContent: 'center',
    minHeight: 48,
  },
  secondaryButtonText: {
    fontSize: 15,
    fontWeight: '800',
  },
  footnote: {
    fontSize: 12,
    lineHeight: 18,
    marginTop: 4,
    textAlign: 'center',
  },
});
