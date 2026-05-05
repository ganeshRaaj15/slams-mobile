import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { listAdminUsersRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';

const STATUS_OPTIONS = [
  { id: '', label: 'All statuses' },
  { id: 'active', label: 'Active only' },
  { id: 'inactive', label: 'Inactive only' },
];

export function AdminUsersScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const [query, setQuery] = useState('');
  const [role, setRole] = useState('');
  const [status, setStatus] = useState('');
  const [roleModalOpen, setRoleModalOpen] = useState(false);
  const [statusModalOpen, setStatusModalOpen] = useState(false);

  const usersQuery = useQuery({
    queryKey: ['admin-users', query, role, status],
    queryFn: () =>
      listAdminUsersRequest({
        q: query.trim() || undefined,
        role: role || undefined,
        status: status || undefined,
        page: 1,
        per_page: 50,
      }),
  });

  const roleLabel = useMemo(() => {
    const option = usersQuery.data?.all_roles.find((value) => value === role);
    return option ? option.toUpperCase() : 'All roles';
  }, [role, usersQuery.data?.all_roles]);

  const statusLabel = STATUS_OPTIONS.find((option) => option.id === status)?.label ?? 'All statuses';

  if (usersQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading users..." />
      </Screen>
    );
  }

  if (usersQuery.isError || !usersQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The user management workspace could not be loaded."
          onRetry={() => {
            void usersQuery.refetch();
          }}
        />
      </Screen>
    );
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
        <Text style={[styles.title, { color: theme.colors.text }]}>User Management</Text>
        <TextField
          autoCapitalize="none"
          label="Search"
          onChangeText={setQuery}
          placeholder="Search by username, full name, email, or phone"
          value={query}
        />

        <View style={styles.filterRow}>
          <Pressable
            onPress={() => {
              setRoleModalOpen(true);
            }}
            style={[
              styles.filterButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]}>{roleLabel}</Text>
          </Pressable>
          <Pressable
            onPress={() => {
              setStatusModalOpen(true);
            }}
            style={[
              styles.filterButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.filterText, { color: theme.colors.text }]}>{statusLabel}</Text>
          </Pressable>
        </View>

        <Pressable
          onPress={() => {
            navigation.navigate('AdminUserEditor', {});
          }}
          style={[
            styles.primaryButton,
            {
              backgroundColor: theme.colors.primary,
            },
          ]}
        >
          <Text style={styles.primaryButtonText}>Create User</Text>
        </Pressable>
      </View>

      {usersQuery.data.users.length === 0 ? (
        <EmptyState title="No users found" message="Adjust the filters or create a new user." />
      ) : (
        usersQuery.data.users.map((user) => (
          <Pressable
            key={user.id}
            onPress={() => {
              navigation.navigate('AdminUserEditor', { userId: user.id });
            }}
            style={[
              styles.userCard,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.userHeader}>
              <View style={styles.userHeaderMeta}>
                <Text style={[styles.userName, { color: theme.colors.text }]}>
                  {user.full_name || user.username}
                </Text>
                <Text style={[styles.userMeta, { color: theme.colors.textMuted }]}>{user.email}</Text>
                <Text style={[styles.userMeta, { color: theme.colors.textMuted }]}>
                  Roles: {user.roles.join(', ') || 'None'}
                </Text>
              </View>
              <View
                style={[
                  styles.statusPill,
                  {
                    backgroundColor: user.active ? theme.colors.successSoft : theme.colors.warningSoft,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.statusPillText,
                    {
                      color: user.active ? theme.colors.success : theme.colors.warning,
                    },
                  ]}
                >
                  {user.active ? 'Active' : 'Inactive'}
                </Text>
              </View>
            </View>
            <Text style={[styles.userMeta, { color: theme.colors.primary }]}>
              {user.phone ? `Phone: ${user.phone}` : 'No phone provided'}
            </Text>
          </Pressable>
        ))
      )}

      <SelectionModal
        onClose={() => {
          setRoleModalOpen(false);
        }}
        onSelect={setRole}
        options={[
          { id: '', label: 'All roles' },
          ...usersQuery.data.all_roles.map((item) => ({
            id: item,
            label: item.toUpperCase(),
          })),
        ]}
        selectedId={role}
        title="Filter by Role"
        visible={roleModalOpen}
      />

      <SelectionModal
        onClose={() => {
          setStatusModalOpen(false);
        }}
        onSelect={setStatus}
        options={STATUS_OPTIONS}
        selectedId={status}
        title="Filter by Status"
        visible={statusModalOpen}
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
  filterRow: {
    flexDirection: 'row',
    gap: 10,
  },
  filterButton: {
    borderRadius: 12,
    flex: 1,
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  filterText: {
    fontSize: 13,
    fontWeight: '700',
  },
  primaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 13,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '800',
  },
  userCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 8,
    padding: 16,
  },
  userHeader: {
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
  },
  userHeaderMeta: {
    flex: 1,
    gap: 4,
  },
  userName: {
    fontSize: 16,
    fontWeight: '800',
  },
  userMeta: {
    fontSize: 13,
    lineHeight: 18,
  },
  statusPill: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  statusPillText: {
    fontSize: 12,
    fontWeight: '800',
  },
});
