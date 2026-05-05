import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { Screen } from '../components/screen';
import type { RootStackParamList } from '../navigation/types';
import { TextField } from '../components/text-field';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';

export function LoginScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const signIn = useAuthStore((state) => state.signIn);
  const authError = useAuthStore((state) => state.error);
  const cardShadow = {
    elevation: 5,
    shadowColor: theme.colors.shadow,
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: theme.tone === 'dark' ? 0.28 : 0.08,
    shadowRadius: 22,
  };

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

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
            styles.eyebrow,
            {
              color: theme.colors.primary,
            },
            ]}
          >
            SLAMS Mobile
          </Text>
        <Text
          style={[
            styles.title,
            {
              color: theme.colors.heading,
            },
          ]}
        >
          Sign in to the mobile workspace
        </Text>
        <Text
          style={[
            styles.subtitle,
            {
              color: theme.colors.textMuted,
            },
          ]}
        >
          Use your SLAMS account to access bookings, approvals, requests, notifications, and operational dashboards.
        </Text>
      </View>

      <View
        style={[
          styles.formCard,
          cardShadow,
          {
            backgroundColor: theme.colors.surfaceOverlay,
            borderColor: theme.colors.border,
          },
        ]}
      >
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
          secureTextEntry
          value={password}
        />

        {localError ? (
          <Text
            style={[
              styles.error,
              {
                color: theme.colors.danger,
              },
            ]}
          >
            {localError}
          </Text>
        ) : null}

        {!localError && authError ? (
          <Text
            style={[
              styles.error,
              {
                color: theme.colors.danger,
              },
            ]}
          >
            {authError}
          </Text>
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
          <Text
            style={[
              styles.secondaryButtonText,
              {
                color: theme.colors.primary,
              },
            ]}
          >
            Create Account
          </Text>
        </Pressable>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: {
    borderRadius: 22,
    borderWidth: 1,
    gap: 8,
    padding: 20,
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
});
