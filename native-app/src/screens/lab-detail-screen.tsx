import { useQuery } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { StyleSheet, Text, View } from 'react-native';

import { getLabRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LabCalendar } from '../components/lab-calendar';
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Availability Calendar</Text>
        <LabCalendar
          labId={lab.id}
          onSlotSelect={
            isStudentRole(role)
              ? (date, start, end) =>
                  navigation.navigate('BookingComposer', {
                    labId: lab.id,
                    preselectedDate: date,
                    preselectedStartTime: start,
                    preselectedEndTime: end,
                  })
              : isExternalRole(role)
                ? (date, start, end) =>
                    navigation.navigate('RequestForm', {
                      labId: lab.id,
                      preselectedDate: date,
                      preselectedStartTime: start,
                      preselectedEndTime: end,
                    })
                : undefined
          }
        />
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

      {(() => {
        const assetsUnderMaintenance = lab.assets.filter(
          (a) => (a.status ?? '').toLowerCase() === 'maintenance',
        );
        return assetsUnderMaintenance.length > 0 ? (
          <View style={[styles.maintenanceBanner, { backgroundColor: theme.colors.warningSoft }]}>
            <Text style={[styles.maintenanceBannerTitle, { color: theme.colors.warning }]}>
              Equipment Notice
            </Text>
            <Text style={[styles.maintenanceBannerText, { color: theme.colors.text }]}>
              {assetsUnderMaintenance.length} item{assetsUnderMaintenance.length > 1 ? 's' : ''} in this lab{' '}
              {assetsUnderMaintenance.length > 1 ? 'are' : 'is'} currently under maintenance and may not be
              available for use.
            </Text>
          </View>
        ) : null;
      })()}

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
          lab.assets.map((asset) => {
            const isUnderMaintenance = (asset.status ?? '').toLowerCase() === 'maintenance';
            return (
              <View
                key={asset.id}
                style={[
                  styles.innerCard,
                  {
                    backgroundColor: isUnderMaintenance
                      ? theme.colors.warningSoft
                      : theme.colors.surfaceMuted,
                  },
                ]}
              >
                <View style={styles.assetTitleRow}>
                  <Text
                    style={[
                      styles.innerTitle,
                      {
                        color: theme.colors.text,
                        flex: 1,
                      },
                    ]}
                  >
                    {asset.name}
                  </Text>
                  {isUnderMaintenance ? (
                    <View style={[styles.maintenancePill, { backgroundColor: theme.colors.warning }]}>
                      <Text style={styles.maintenancePillText}>Under Maintenance</Text>
                    </View>
                  ) : null}
                </View>
                <Text
                  style={[
                    styles.innerText,
                    {
                      color: theme.colors.textMuted,
                    },
                  ]}
                >
                  {asset.category || 'Uncategorized'}
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
            );
          })
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
  maintenanceBanner: {
    borderRadius: 16,
    gap: 6,
    padding: 16,
  },
  maintenanceBannerTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  maintenanceBannerText: {
    fontSize: 13,
    lineHeight: 19,
  },
  assetTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  maintenancePill: {
    borderRadius: 20,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  maintenancePillText: {
    color: '#ffffff',
    fontSize: 11,
    fontWeight: '800',
  },
});
