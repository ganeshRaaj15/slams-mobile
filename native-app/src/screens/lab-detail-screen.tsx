import { useQuery } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { getLabRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { isExternalRole, isOperationalRole, isStudentRole } from '../constants/roles';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import type { RootStackParamList } from '../navigation/types';

export function LabDetailScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'LabDetail'>>();
  const user = useAuthStore((state) => state.user);

  const labQuery = useQuery({
    queryKey: ['lab', route.params.labId],
    queryFn: () => getLabRequest(route.params.labId),
  });

  if (labQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading laboratory..." />
      </Screen>
    );
  }

  if (labQuery.isError || !labQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="Laboratory details could not be loaded."
          onRetry={() => {
            void labQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const { lab } = labQuery.data;
  const role = user?.primary_role ?? 'student';

  return (
    <Screen>
      <View
        style={[
          styles.hero,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text
          style={[
            styles.title,
            {
              color: theme.colors.text,
            },
          ]}
        >
          {lab.name}
        </Text>
        <Text
          style={[
            styles.room,
            {
              color: theme.colors.primary,
            },
          ]}
        >
          {lab.room}
        </Text>
        {lab.description ? (
          <Text
            style={[
              styles.description,
              {
                color: theme.colors.textMuted,
              },
            ]}
          >
            {lab.description}
          </Text>
        ) : null}
        <Text
          style={[
            styles.meta,
            {
              color: theme.colors.text,
            },
          ]}
        >
          Capacity: {lab.capacity || '-'}
        </Text>
        <Text
          style={[
            styles.meta,
            {
              color: theme.colors.text,
            },
          ]}
        >
          PIC: {lab.pic_name || 'Not assigned'}
        </Text>
      </View>

      {isExternalRole(role) ? (
        <Pressable
          onPress={() => navigation.navigate('RequestForm', { labId: lab.id })}
          style={[
            styles.actionButton,
            {
              backgroundColor: theme.colors.primary,
            },
          ]}
        >
          <Text style={styles.actionButtonText}>Request Access</Text>
        </Pressable>
      ) : null}

      {isStudentRole(role) ? (
        <Pressable
          onPress={() => navigation.navigate('BookingComposer', { labId: lab.id })}
          style={[
            styles.actionButton,
            {
              backgroundColor: theme.colors.primary,
            },
          ]}
        >
          <Text style={styles.actionButtonText}>Launch Booking Composer</Text>
        </Pressable>
      ) : null}

      {isOperationalRole(role) ? (
        <View
          style={[
            styles.noticeCard,
            {
              backgroundColor: theme.colors.warningSoft,
            },
          ]}
        >
          <Text
            style={[
              styles.noticeTitle,
              {
                color: theme.colors.warning,
              },
            ]}
          >
            Operational note
          </Text>
          <Text
            style={[
              styles.noticeText,
              {
              color: theme.colors.text,
            },
          ]}
          >
            Use the role-specific sections for approvals, issue reporting, or maintenance work linked to this laboratory.
          </Text>
        </View>
      ) : null}

      <View
        style={[
          styles.sectionCard,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text
          style={[
            styles.sectionTitle,
            {
              color: theme.colors.text,
            },
          ]}
        >
          Available Services
        </Text>
        {lab.services.length === 0 ? (
          <EmptyState
            title="No services listed"
            message="This laboratory does not have active catalog services configured yet."
          />
        ) : (
          lab.services.map((service) => (
            <View
              key={service.id}
              style={[
                styles.innerCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text
                style={[
                  styles.innerTitle,
                  {
                    color: theme.colors.text,
                  },
                ]}
              >
                {service.service_name}
              </Text>
              {service.field_name ? (
                <Text
                  style={[
                    styles.innerMeta,
                    {
                      color: theme.colors.primary,
                    },
                  ]}
                >
                  {service.field_name}
                </Text>
              ) : null}
              {service.equipment_models ? (
                <Text
                  style={[
                    styles.innerText,
                    {
                      color: theme.colors.textMuted,
                    },
                  ]}
                >
                  Equipment: {service.equipment_models}
                </Text>
              ) : null}
            </View>
          ))
        )}
      </View>

      <View
        style={[
          styles.sectionCard,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text
          style={[
            styles.sectionTitle,
            {
              color: theme.colors.text,
            },
          ]}
        >
          Assets
        </Text>
        {lab.assets.length === 0 ? (
          <EmptyState
            title="No assets found"
            message="Assets for this laboratory have not been added yet."
          />
        ) : (
          lab.assets.map((asset) => (
            <View
              key={asset.id}
              style={[
                styles.innerCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text
                style={[
                  styles.innerTitle,
                  {
                    color: theme.colors.text,
                  },
                ]}
              >
                {asset.name}
              </Text>
              <Text
                style={[
                  styles.innerText,
                  {
                    color: theme.colors.textMuted,
                  },
                ]}
              >
                {asset.category || 'Uncategorized'}  |  {asset.status || 'Unknown status'}
              </Text>
              <Text
                style={[
                  styles.innerText,
                  {
                    color: theme.colors.textMuted,
                  },
                ]}
              >
                Available: {asset.quantity} / {asset.total_quantity}
              </Text>
            </View>
          ))
        )}
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
  title: {
    fontSize: 26,
    fontWeight: '800',
  },
  room: {
    fontSize: 14,
    fontWeight: '700',
  },
  description: {
    fontSize: 14,
    lineHeight: 20,
  },
  meta: {
    fontSize: 13,
    fontWeight: '600',
  },
  actionButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  actionButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '800',
  },
  noticeCard: {
    borderRadius: 18,
    gap: 8,
    padding: 16,
  },
  noticeTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  noticeText: {
    fontSize: 14,
    lineHeight: 20,
  },
  sectionCard: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 12,
    padding: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  innerCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  innerTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  innerMeta: {
    fontSize: 12,
    fontWeight: '700',
  },
  innerText: {
    fontSize: 13,
    lineHeight: 18,
  },
});
