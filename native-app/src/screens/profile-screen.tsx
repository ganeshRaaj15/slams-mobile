import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import { useEffect, useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, Switch, Text, View } from 'react-native';

import { getNativePushStatusRequest, getProfileWorkspaceRequest, updateProfileRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import { syncNativePushRegistration, unregisterNativePushRegistration } from '../notifications/native-push';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';

export function ProfileScreen() {
  const theme = useAppTheme();
  const bootstrap = useAuthStore((state) => state.bootstrap);
  const biometric = useAuthStore((state) => state.biometric);
  const refreshBiometricState = useAuthStore((state) => state.refreshBiometricState);
  const setBiometricPreference = useAuthStore((state) => state.setBiometricPreference);
  const queryClient = useQueryClient();
  const [facultyModalOpen, setFacultyModalOpen] = useState(false);
  const [username, setUsername] = useState('');
  const [fullName, setFullName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [facultyId, setFacultyId] = useState<number | null>(null);
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [photoAsset, setPhotoAsset] = useState<{
    uri: string;
    name: string;
    mimeType: string;
  } | null>(null);
  const [biometricPending, setBiometricPending] = useState(false);
  const [pushPending, setPushPending] = useState(false);
  const [localMessage, setLocalMessage] = useState<string | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  const profileQuery = useQuery({
    queryKey: ['profile-workspace'],
    queryFn: getProfileWorkspaceRequest,
  });

  const nativePushQuery = useQuery({
    queryKey: ['native-push'],
    queryFn: getNativePushStatusRequest,
  });

  const saveMutation = useMutation({
    mutationFn: updateProfileRequest,
    onSuccess: async () => {
      setLocalError(null);
      setLocalMessage('Profile updated successfully.');
      setPassword('');
      setPasswordConfirm('');
      setPhotoAsset(null);
      try {
        await bootstrap();
      } catch (_error) {
        // Keep the successful save state visible even if the follow-up refresh fails.
      }
      await queryClient.invalidateQueries({ queryKey: ['profile-workspace'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
    },
    onError: (error: unknown) => {
      setLocalMessage(null);
      setLocalError(readErrorMessage(error, 'Profile update failed.'));
    },
  });

  useEffect(() => {
    if (!profileQuery.data) {
      return;
    }

    const { user } = profileQuery.data;
    setUsername(user.username ?? '');
    setFullName(user.full_name ?? '');
    setPhone(user.phone ?? '');
    setEmail(user.email ?? '');
    setFacultyId(user.faculty_id ?? null);
  }, [profileQuery.data]);

  useEffect(() => {
    void refreshBiometricState().catch(() => undefined);
  }, [refreshBiometricState]);

  if (profileQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading profile..." />
      </Screen>
    );
  }

  if (profileQuery.isError || !profileQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The profile workspace could not be loaded."
          onRetry={() => {
            void profileQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const { user, editable, editable_reason, faculties } = profileQuery.data;
  const selectedFaculty =
    faculties.find((faculty) => faculty.id === facultyId)?.label || 'No faculty assigned';
  const avatarUrl = user.profile_photo_url?.trim() || '';

  async function pickProfilePhoto() {
    setLocalError(null);
    const result = await DocumentPicker.getDocumentAsync({
      type: ['image/jpeg', 'image/png', 'image/webp'],
      copyToCacheDirectory: true,
      multiple: false,
    });

    if (result.canceled || result.assets.length === 0) {
      return;
    }

    const asset = result.assets[0];
    if (!asset.uri || !asset.name || !asset.mimeType) {
      setLocalError('The selected image could not be read.');
      return;
    }

    setPhotoAsset({
      uri: asset.uri,
      name: asset.name,
      mimeType: asset.mimeType,
    });
  }

  async function handleSave() {
    setLocalMessage(null);
    setLocalError(null);

    await saveMutation.mutateAsync({
      username: username.trim(),
      full_name: fullName.trim(),
      phone: phone.trim(),
      faculty_id: facultyId,
      email: email.trim(),
      password,
      password_confirm: passwordConfirm,
      profile_photo: photoAsset,
    });
  }

  async function handleBiometricToggle(enabled: boolean) {
    setLocalMessage(null);
    setLocalError(null);
    setBiometricPending(true);

    try {
      await setBiometricPreference(enabled);
      setLocalMessage(enabled ? 'Biometric login enabled for this device.' : 'Biometric login disabled for this device.');
    } catch (error: unknown) {
      setLocalError(
        readErrorMessage(
          error,
          enabled ? 'Biometric login could not be enabled.' : 'Biometric login could not be updated.',
        ),
      );
    } finally {
      setBiometricPending(false);
    }
  }

  async function handlePushToggle(enabled: boolean) {
    setPushPending(true);

    try {
      if (enabled) {
        const result = await syncNativePushRegistration({ prompt: true });
        await nativePushQuery.refetch();

        if (result.enabled) {
          Alert.alert(
            'Push notifications enabled',
            'This device will receive SLAMS alerts while you remain signed in.',
          );
        } else {
          Alert.alert('Push notifications unavailable', result.message);
        }

        return;
      }

      await unregisterNativePushRegistration();
      await nativePushQuery.refetch();
      Alert.alert(
        'Push notifications disabled',
        'This device will stop receiving SLAMS alerts until you enable push again.',
      );
    } catch (error: unknown) {
      Alert.alert(
        'Push notifications unavailable',
        readErrorMessage(
          error,
          enabled
            ? 'Native push could not be enabled on this device.'
            : 'Native push could not be disabled on this device.',
        ),
      );
    } finally {
      setPushPending(false);
    }
  }

  const hasNativePush = (nativePushQuery.data?.active_tokens ?? 0) > 0;

  return (
    <Screen>
      <View
        style={[
          styles.heroCard,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <View style={styles.heroHeader}>
          {avatarUrl ? (
            <Image source={{ uri: avatarUrl }} style={styles.avatar} />
          ) : (
            <View
              style={[
                styles.avatarFallback,
                {
                  backgroundColor: theme.colors.primarySoft,
                },
              ]}
            >
              <Text style={[styles.avatarFallbackText, { color: theme.colors.primary }]}>
                {(user.full_name || user.username || 'S').trim().charAt(0).toUpperCase()}
              </Text>
            </View>
          )}
          <View style={styles.heroMeta}>
            <Text style={[styles.name, { color: theme.colors.text }]}>{user.full_name || user.username}</Text>
            <Text style={[styles.role, { color: theme.colors.primary }]}>{user.primary_role.toUpperCase()}</Text>
            <Text style={[styles.meta, { color: theme.colors.textMuted }]}>{user.email}</Text>
          </View>
        </View>
      </View>

      {editable ? (
        <View
          style={[
            styles.card,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Edit Profile</Text>
          <TextField autoCapitalize="none" label="Username" onChangeText={setUsername} value={username} />
          <TextField label="Full name" onChangeText={setFullName} value={fullName} />
          <TextField
            autoCapitalize="none"
            keyboardType="email-address"
            label="Email"
            onChangeText={setEmail}
            value={email}
          />
          <TextField keyboardType="phone-pad" label="Phone" onChangeText={setPhone} value={phone} />

          <View style={styles.fieldWrap}>
            <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Faculty</Text>
            <Pressable
              onPress={() => {
                setFacultyModalOpen(true);
              }}
              style={[
                styles.selector,
                {
                  backgroundColor: theme.colors.surface,
                  borderColor: theme.colors.border,
                },
              ]}
            >
              <Text style={[styles.selectorText, { color: theme.colors.text }]}>{selectedFaculty}</Text>
            </Pressable>
          </View>

          <TextField
            autoCapitalize="none"
            label="New password"
            onChangeText={setPassword}
            placeholder="Leave blank to keep the current password"
            secureTextEntry
            value={password}
          />
          <TextField
            autoCapitalize="none"
            label="Confirm password"
            onChangeText={setPasswordConfirm}
            placeholder="Repeat the new password"
            secureTextEntry
            value={passwordConfirm}
          />

          <Pressable
            onPress={() => {
              void pickProfilePhoto();
            }}
            style={[
              styles.secondaryButton,
              {
                backgroundColor: theme.colors.primarySoft,
              },
            ]}
          >
            <Text style={[styles.secondaryButtonText, { color: theme.colors.primary }]}>
              {photoAsset ? `Change photo: ${photoAsset.name}` : 'Choose Profile Photo'}
            </Text>
          </Pressable>

          {localMessage ? <Text style={[styles.feedback, { color: theme.colors.success }]}>{localMessage}</Text> : null}
          {localError ? <Text style={[styles.feedback, { color: theme.colors.danger }]}>{localError}</Text> : null}

          <Pressable
            disabled={saveMutation.isPending}
            onPress={() => {
              void handleSave();
            }}
            style={[
              styles.primaryButton,
              {
                backgroundColor: theme.colors.primary,
                opacity: saveMutation.isPending ? 0.7 : 1,
              },
            ]}
          >
            <Text style={styles.primaryButtonText}>{saveMutation.isPending ? 'Saving...' : 'Save Profile'}</Text>
          </Pressable>
        </View>
      ) : (
        <EmptyState
          title="Profile editing handled by admin"
          message={editable_reason || 'This role cannot update profile details from the mobile app.'}
        />
      )}

      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Biometric Login</Text>
        <Text style={[styles.feedback, { color: theme.colors.textMuted }]}>
          Use Face ID, fingerprint, or the device biometric prompt to unlock the saved SLAMS session on this device.
        </Text>
        {biometric.isSupported ? (
          <>
            <View style={styles.switchRow}>
              <View style={styles.switchCopy}>
                <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Enable on this device</Text>
                <Text style={[styles.switchHint, { color: theme.colors.textMuted }]}>
                  Requires one successful sign-in and stores the session in protected device storage.
                </Text>
              </View>
              <Switch
                disabled={biometricPending}
                onValueChange={(value) => {
                  void handleBiometricToggle(value);
                }}
                value={biometric.isEnabled}
              />
            </View>
            <Text style={[styles.feedback, { color: theme.colors.textMuted }]}>
              {biometric.isReady
                ? 'Biometric sign-in is ready on this device.'
                : biometric.isEnabled
                  ? 'Biometric login is enabled and will be ready after the current session is saved again.'
                  : 'Biometric login is currently disabled.'}
            </Text>
          </>
        ) : (
          <Text style={[styles.feedback, { color: theme.colors.textMuted }]}>
            This device does not currently expose a supported biometric method to the app.
          </Text>
        )}
      </View>

      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Push Notifications</Text>
        <Text style={[styles.feedback, { color: theme.colors.textMuted }]}>
          Receive booking and approval alerts on this device while you remain signed in.
        </Text>
        <View style={styles.switchRow}>
          <View style={styles.switchCopy}>
            <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Enable on this device</Text>
            <Text style={[styles.switchHint, { color: theme.colors.textMuted }]}>
              Turn this off to stop device notifications from SLAMS on this phone.
            </Text>
          </View>
          <Switch
            disabled={pushPending || nativePushQuery.isLoading}
            onValueChange={(value) => {
              void handlePushToggle(value);
            }}
            value={hasNativePush}
          />
        </View>

        {nativePushQuery.data?.devices?.length ? (
          <View style={styles.deviceList}>
            <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Registered devices</Text>
            {nativePushQuery.data.devices.map((device) => (
              <View
                key={device.id}
                style={[
                  styles.deviceRow,
                  {
                    borderColor: theme.colors.border,
                  },
                ]}
              >
                <View style={styles.deviceCopy}>
                  <Text style={[styles.deviceTitle, { color: theme.colors.text }]}>
                    {device.device_name || 'SLAMS Mobile Device'} ({device.platform || 'unknown'})
                  </Text>
                  <Text style={[styles.deviceMeta, { color: theme.colors.textMuted }]}>
                    {device.is_active ? 'Active' : 'Inactive'}
                    {device.last_used_at ? ` - Last confirmed ${device.last_used_at}` : ''}
                  </Text>
                  {device.last_error_message ? (
                    <Text style={[styles.deviceError, { color: theme.colors.warning }]}>
                      {device.is_active ? 'Last delivery issue' : 'Inactive reason'}: {device.last_error_message}
                    </Text>
                  ) : null}
                </View>
              </View>
            ))}
          </View>
        ) : null}
      </View>

      <SelectionModal
        onClose={() => {
          setFacultyModalOpen(false);
        }}
        onSelect={(selectedId) => {
          setFacultyId(selectedId ? Number(selectedId) : null);
        }}
        options={[
          { id: '', label: 'No faculty assigned' },
          ...faculties.map((faculty) => ({
            id: String(faculty.id),
            label: faculty.label,
            subtitle: faculty.is_fkmp ? 'FKMP faculty' : 'Non-FKMP faculty',
          })),
        ]}
        selectedId={facultyId ? String(facultyId) : ''}
        title="Select Faculty"
        visible={facultyModalOpen}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  heroCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 14,
    padding: 16,
  },
  heroHeader: {
    flexDirection: 'row',
    gap: 14,
  },
  heroMeta: {
    flex: 1,
    gap: 4,
  },
  avatar: {
    borderRadius: 28,
    height: 56,
    width: 56,
  },
  avatarFallback: {
    alignItems: 'center',
    borderRadius: 28,
    height: 56,
    justifyContent: 'center',
    width: 56,
  },
  avatarFallbackText: {
    fontSize: 22,
    fontWeight: '800',
  },
  name: {
    fontSize: 22,
    fontWeight: '800',
  },
  role: {
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0.6,
  },
  meta: {
    fontSize: 13,
    lineHeight: 18,
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 12,
    padding: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  fieldWrap: {
    gap: 6,
  },
  fieldLabel: {
    fontSize: 14,
    fontWeight: '700',
  },
  selector: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  selectorText: {
    fontSize: 15,
  },
  switchRow: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
  },
  switchCopy: {
    flex: 1,
    gap: 4,
  },
  switchHint: {
    fontSize: 12,
    lineHeight: 18,
  },
  deviceList: {
    gap: 10,
    paddingTop: 4,
  },
  deviceRow: {
    borderTopWidth: StyleSheet.hairlineWidth,
    paddingTop: 10,
  },
  deviceCopy: {
    gap: 4,
  },
  deviceTitle: {
    fontSize: 13,
    fontWeight: '700',
  },
  deviceMeta: {
    fontSize: 12,
  },
  deviceError: {
    fontSize: 12,
    lineHeight: 18,
  },
  primaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
  secondaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 12,
  },
  secondaryButtonText: {
    fontSize: 14,
    fontWeight: '800',
  },
  feedback: {
    fontSize: 13,
    lineHeight: 18,
  },
});
