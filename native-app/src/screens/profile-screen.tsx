import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import { useEffect, useState } from 'react';
import { Image, Pressable, StyleSheet, Text, View } from 'react-native';

import { getProfileWorkspaceRequest, updateProfileRequest } from '../api/endpoints';
import { API_BASE_URL } from '../config/env';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';

export function ProfileScreen() {
  const theme = useAppTheme();
  const signOut = useAuthStore((state) => state.signOut);
  const bootstrap = useAuthStore((state) => state.bootstrap);
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
  const [localMessage, setLocalMessage] = useState<string | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  const profileQuery = useQuery({
    queryKey: ['profile-workspace'],
    queryFn: getProfileWorkspaceRequest,
  });

  const saveMutation = useMutation({
    mutationFn: updateProfileRequest,
    onSuccess: async () => {
      setLocalError(null);
      setLocalMessage('Profile updated successfully.');
      setPassword('');
      setPasswordConfirm('');
      setPhotoAsset(null);
      await bootstrap();
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
            <Text style={[styles.meta, { color: theme.colors.textMuted }]}>Roles: {user.roles.join(', ')}</Text>
          </View>
        </View>

        <View
          style={[
            styles.noteCard,
            {
              backgroundColor: theme.colors.surfaceMuted,
            },
          ]}
        >
          <Text style={[styles.noteTitle, { color: theme.colors.text }]}>Connection</Text>
          <Text style={[styles.noteText, { color: theme.colors.textMuted }]}>API Base URL: {API_BASE_URL}</Text>
          <Text style={[styles.noteText, { color: theme.colors.textMuted }]}>
            Dashboard path: {user.dashboard_path}
          </Text>
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

      <Pressable
        onPress={() => {
          void signOut();
        }}
        style={[
          styles.signOutButton,
          {
            backgroundColor: theme.colors.dangerSoft,
          },
        ]}
      >
        <Text style={[styles.signOutText, { color: theme.colors.danger }]}>Sign Out</Text>
      </Pressable>

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
  noteCard: {
    borderRadius: 14,
    gap: 4,
    padding: 12,
  },
  noteTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  noteText: {
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
  signOutButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  signOutText: {
    fontSize: 15,
    fontWeight: '800',
  },
});
