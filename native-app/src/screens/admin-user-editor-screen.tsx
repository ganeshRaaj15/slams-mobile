import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, Switch, Text, View } from 'react-native';

import {
  createAdminUserRequest,
  deleteAdminUserRequest,
  getAdminUserRequest,
  listAdminUsersRequest,
  sendAdminRecoveryRequest,
  updateAdminUserRequest,
} from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import type { RootStackParamList } from '../navigation/types';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';

export function AdminUserEditorScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'AdminUserEditor'>>();
  const queryClient = useQueryClient();
  const userId = route.params?.userId ?? null;
  const isEditMode = userId !== null;
  const [facultyModalOpen, setFacultyModalOpen] = useState(false);
  const [username, setUsername] = useState('');
  const [fullName, setFullName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [facultyId, setFacultyId] = useState<number | null>(null);
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [active, setActive] = useState(true);
  const [roles, setRoles] = useState<string[]>([]);
  const [localMessage, setLocalMessage] = useState<string | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  const detailQuery = useQuery({
    queryKey: ['admin-user', userId],
    queryFn: () => getAdminUserRequest(userId as number),
    enabled: isEditMode,
  });

  const catalogQuery = useQuery({
    queryKey: ['admin-users', 'catalog'],
    queryFn: () => listAdminUsersRequest({ page: 1, per_page: 10 }),
  });

  useEffect(() => {
    if (!detailQuery.data?.user) {
      return;
    }

    setUsername(detailQuery.data.user.username ?? '');
    setFullName(detailQuery.data.user.full_name ?? '');
    setPhone(detailQuery.data.user.phone ?? '');
    setEmail(detailQuery.data.user.email ?? '');
    setFacultyId(detailQuery.data.user.faculty_id ?? null);
    setActive(detailQuery.data.user.active);
    setRoles(detailQuery.data.user.roles ?? []);
  }, [detailQuery.data]);

  const createMutation = useMutation({
    mutationFn: createAdminUserRequest,
    onSuccess: async () => {
      setLocalError(null);
      setLocalMessage('User created successfully.');
      await queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, 'User creation failed.'));
    },
  });

  const updateMutation = useMutation({
    mutationFn: (payload: Parameters<typeof updateAdminUserRequest>[1]) =>
      updateAdminUserRequest(userId as number, payload),
    onSuccess: async () => {
      setLocalError(null);
      setLocalMessage('User updated successfully.');
      setPassword('');
      setPasswordConfirm('');
      await queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      await queryClient.invalidateQueries({ queryKey: ['admin-user', userId] });
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, 'User update failed.'));
    },
  });

  const recoveryMutation = useMutation({
    mutationFn: () => sendAdminRecoveryRequest(userId as number),
    onSuccess: () => {
      setLocalError(null);
      setLocalMessage('Recovery link sent.');
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, 'Recovery email could not be sent.'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteAdminUserRequest(userId as number),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, 'User deletion failed.'));
    },
  });

  const faculties = detailQuery.data?.faculties ?? catalogQuery.data?.faculties ?? [];
  const allRoles = detailQuery.data?.all_roles ?? catalogQuery.data?.all_roles ?? [];
  const selectedFaculty = useMemo(
    () => faculties.find((faculty) => faculty.id === facultyId)?.label || 'No faculty assigned',
    [faculties, facultyId],
  );

  if ((isEditMode && detailQuery.isLoading) || catalogQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label={isEditMode ? 'Loading user details...' : 'Loading user editor...'} />
      </Screen>
    );
  }

  if ((isEditMode && (detailQuery.isError || !detailQuery.data)) || catalogQuery.isError || !catalogQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The user editor could not be loaded."
          onRetry={() => {
            if (isEditMode) {
              void detailQuery.refetch();
            }
            void catalogQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  function selectRole(role: string) {
    setRoles([role]);
  }

  async function handleSave() {
    setLocalMessage(null);
    setLocalError(null);

    const payload = {
      username: username.trim(),
      full_name: fullName.trim(),
      phone: phone.trim(),
      faculty_id: facultyId,
      email: email.trim(),
      password,
      password_confirm: passwordConfirm,
      active,
      roles,
    };

    if (isEditMode) {
      await updateMutation.mutateAsync(payload);
      return;
    }

    await createMutation.mutateAsync({
      ...payload,
      password: password.trim(),
      password_confirm: passwordConfirm.trim(),
    });
  }

  return (
    <Screen>
      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.title, { color: theme.colors.text }]}>
          {isEditMode ? 'Edit User' : 'Create User'}
        </Text>
        <TextField autoCapitalize="none" label="Username" onChangeText={setUsername} value={username} />
        <TextField label="Full name" onChangeText={setFullName} value={fullName} />
        <TextField keyboardType="phone-pad" label="Phone" onChangeText={setPhone} value={phone} />
        <TextField
          autoCapitalize="none"
          keyboardType="email-address"
          label="Email"
          onChangeText={setEmail}
          value={email}
        />

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

        <View style={styles.fieldWrap}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Role</Text>
          <View style={styles.roleWrap}>
            {allRoles.map((role) => {
              const selected = roles.includes(role);
              return (
                <Pressable
                  key={role}
                  onPress={() => {
                    selectRole(role);
                  }}
                  style={[
                    styles.roleChip,
                    {
                      backgroundColor: selected ? theme.colors.primarySoft : theme.colors.surfaceMuted,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.roleChipText,
                      {
                        color: selected ? theme.colors.primary : theme.colors.text,
                      },
                    ]}
                  >
                    {role.toUpperCase()}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </View>

        <TextField
          autoCapitalize="none"
          label={isEditMode ? 'New password' : 'Password'}
          onChangeText={setPassword}
          placeholder={isEditMode ? 'Leave blank to keep the current password' : 'Required'}
          secureTextEntry
          value={password}
        />
        <TextField
          autoCapitalize="none"
          label="Confirm password"
          onChangeText={setPasswordConfirm}
          placeholder="Repeat the password"
          secureTextEntry
          value={passwordConfirm}
        />

        {isEditMode ? (
          <View style={styles.switchRow}>
            <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Active account</Text>
            <Switch onValueChange={setActive} value={active} />
          </View>
        ) : null}

        {localMessage ? <Text style={[styles.feedback, { color: theme.colors.success }]}>{localMessage}</Text> : null}
        {localError ? <Text style={[styles.feedback, { color: theme.colors.danger }]}>{localError}</Text> : null}

        <Pressable
          disabled={createMutation.isPending || updateMutation.isPending}
          onPress={() => {
            void handleSave();
          }}
          style={[
            styles.primaryButton,
            {
              backgroundColor: theme.colors.primary,
              opacity: createMutation.isPending || updateMutation.isPending ? 0.7 : 1,
            },
          ]}
        >
          <Text style={styles.primaryButtonText}>
            {createMutation.isPending || updateMutation.isPending ? 'Saving...' : isEditMode ? 'Save Changes' : 'Create User'}
          </Text>
        </Pressable>
      </View>

      {isEditMode ? (
        <View
          style={[
            styles.card,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Admin Actions</Text>
          <Pressable
            disabled={recoveryMutation.isPending}
            onPress={() => {
              void recoveryMutation.mutateAsync();
            }}
            style={[
              styles.secondaryButton,
              {
                backgroundColor: theme.colors.successSoft,
              },
            ]}
          >
            <Text style={[styles.secondaryButtonText, { color: theme.colors.success }]}>
              {recoveryMutation.isPending ? 'Sending...' : 'Send Recovery Link'}
            </Text>
          </Pressable>

          <Pressable
            disabled={deleteMutation.isPending}
            onPress={() => {
              void deleteMutation.mutateAsync();
            }}
            style={[
              styles.secondaryButton,
              {
                backgroundColor: theme.colors.dangerSoft,
              },
            ]}
          >
            <Text style={[styles.secondaryButtonText, { color: theme.colors.danger }]}>
              {deleteMutation.isPending ? 'Deleting...' : 'Delete User'}
            </Text>
          </Pressable>
        </View>
      ) : (
        <EmptyState title="New account" message="Select a role and set a password to create the user." />
      )}

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
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 12,
    padding: 16,
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
  },
  sectionTitle: {
    fontSize: 17,
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
  roleWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  roleChip: {
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  roleChipText: {
    fontSize: 12,
    fontWeight: '800',
  },
  switchRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  feedback: {
    fontSize: 13,
    lineHeight: 18,
  },
  primaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '800',
  },
  secondaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 13,
  },
  secondaryButtonText: {
    fontSize: 14,
    fontWeight: '800',
  },
});
