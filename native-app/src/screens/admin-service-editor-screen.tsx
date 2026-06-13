import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import {
  createAdminServiceRequest,
  deleteAdminServiceRequest,
  getAdminServiceRequest,
  listAdminAssetsRequest,
  listAdminLabsRequest,
  updateAdminServiceRequest,
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

export function AdminServiceEditorScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'AdminServiceEditor'>>();
  const queryClient = useQueryClient();
  const serviceId = route.params?.serviceId ?? null;
  const isEditMode = serviceId !== null;

  const [laboratoryId, setLaboratoryId] = useState<number | null>(null);
  const [fieldName, setFieldName] = useState('');
  const [serviceName, setServiceName] = useState('');
  const [acceptanceCriteria, setAcceptanceCriteria] = useState('');
  const [calibrationStatus, setCalibrationStatus] = useState('unknown');
  const [serviceNotes, setServiceNotes] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [requirements, setRequirements] = useState<Record<number, string>>({});
  const [labModalOpen, setLabModalOpen] = useState(false);
  const [statusModalOpen, setStatusModalOpen] = useState(false);
  const [initialized, setInitialized] = useState(false);
  const [localMessage, setLocalMessage] = useState<string | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  const detailQuery = useQuery({
    queryKey: ['admin-service', serviceId],
    queryFn: () => getAdminServiceRequest(serviceId as number),
    enabled: isEditMode,
  });

  const labsQuery = useQuery({
    queryKey: ['admin-labs', 'service-editor'],
    queryFn: () => listAdminLabsRequest(),
  });

  const assetsQuery = useQuery({
    queryKey: ['admin-assets', 'service-editor'],
    queryFn: () => listAdminAssetsRequest(),
  });

  useEffect(() => {
    if (initialized) {
      return;
    }

    if (isEditMode && detailQuery.data?.service) {
      const service = detailQuery.data.service;
      setLaboratoryId(service.laboratory_id);
      setFieldName(service.field_name ?? '');
      setServiceName(service.service_name ?? '');
      setAcceptanceCriteria(service.acceptance_criteria ?? '');
      setCalibrationStatus(service.calibration_status ?? 'unknown');
      setServiceNotes(service.service_notes ?? '');
      setIsActive(service.is_active);
      const nextRequirements: Record<number, string> = {};
      service.required_assets.forEach((asset) => {
        nextRequirements[asset.asset_id] = String(asset.quantity_required);
      });
      setRequirements(nextRequirements);
      setInitialized(true);
      return;
    }

    if (!isEditMode && labsQuery.data?.labs?.length) {
      setLaboratoryId(labsQuery.data.labs[0].id);
      setInitialized(true);
    }
  }, [detailQuery.data?.service, initialized, isEditMode, labsQuery.data?.labs]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        laboratory_id: laboratoryId ?? 0,
        field_name: fieldName.trim(),
        service_name: serviceName.trim(),
        acceptance_criteria: acceptanceCriteria.trim(),
        calibration_status: calibrationStatus,
        service_notes: serviceNotes.trim(),
        is_active: isActive,
        requirements: Object.entries(requirements)
          .map(([assetId, quantityRequired]) => ({
            asset_id: Number(assetId),
            quantity_required: Number(quantityRequired || 0),
          }))
          .filter((row) => row.asset_id > 0 && row.quantity_required > 0),
      };

      if (isEditMode) {
        return updateAdminServiceRequest(serviceId as number, payload);
      }

      return createAdminServiceRequest(payload);
    },
    onSuccess: async () => {
      setLocalError(null);
      setLocalMessage(isEditMode ? 'Service bundle updated successfully.' : 'Service bundle created successfully.');
      await queryClient.invalidateQueries({ queryKey: ['admin-services'] });
      if (isEditMode) {
        await queryClient.invalidateQueries({ queryKey: ['admin-service', serviceId] });
        return;
      }
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, isEditMode ? 'Service bundle update failed.' : 'Service bundle save failed.'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteAdminServiceRequest(serviceId as number),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-services'] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, 'Service bundle deletion failed.'));
    },
  });

  const labs = detailQuery.data?.labs ?? labsQuery.data?.labs ?? [];
  const assets = useMemo(() => {
    const source = detailQuery.data?.assets ?? assetsQuery.data?.assets ?? [];
    return source.filter((asset) => (laboratoryId ? asset.lab_id === laboratoryId : true));
  }, [assetsQuery.data?.assets, detailQuery.data?.assets, laboratoryId]);

  const selectedLab = useMemo(() => labs.find((item) => item.id === laboratoryId) ?? null, [laboratoryId, labs]);
  const selectedLabLabel = selectedLab
    ? ('label' in selectedLab ? selectedLab.label : `${selectedLab.name} - ${selectedLab.room}`)
    : 'Select laboratory';

  if ((isEditMode && detailQuery.isLoading) || labsQuery.isLoading || assetsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label={isEditMode ? 'Loading service bundle...' : 'Loading service editor...'} />
      </Screen>
    );
  }

  if ((isEditMode && (detailQuery.isError || !detailQuery.data)) || labsQuery.isError || assetsQuery.isError || !labsQuery.data || !assetsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The service editor could not be loaded."
          onRetry={() => {
            if (isEditMode) {
              void detailQuery.refetch();
            }
            void labsQuery.refetch();
            void assetsQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  function confirmDelete() {
    Alert.alert('Delete service bundle', 'This removes the bundled service definition from the selected laboratory.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () => {
          void deleteMutation.mutateAsync();
        },
      },
    ]);
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
          {isEditMode ? 'Edit Service Bundle' : 'Create Service Bundle'}
        </Text>

        <View style={styles.fieldWrap}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Laboratory</Text>
          <Pressable
            onPress={() => setLabModalOpen(true)}
            style={[
              styles.selector,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.selectorText, { color: theme.colors.text }]}>
              {selectedLabLabel}
            </Text>
          </Pressable>
        </View>

        <TextField label="Service Name" onChangeText={setServiceName} value={serviceName} />
        <TextField label="Field of Work" onChangeText={setFieldName} value={fieldName} />

        <View style={styles.fieldWrap}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Calibration Status</Text>
          <Pressable
            onPress={() => setStatusModalOpen(true)}
            style={[
              styles.selector,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.selectorText, { color: theme.colors.text }]}>
              {calibrationStatus.replace(/\b\w/g, (value) => value.toUpperCase())}
            </Text>
          </Pressable>
        </View>

        <View style={styles.toggleRow}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Service Status</Text>
          <Pressable
            onPress={() => setIsActive((current) => !current)}
            style={[
              styles.statusToggle,
              {
                backgroundColor: isActive ? theme.colors.successSoft : theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.statusToggleText, { color: isActive ? theme.colors.success : theme.colors.textMuted }]}>
              {isActive ? 'Active' : 'Inactive'}
            </Text>
          </Pressable>
        </View>

        <TextField
          label="Acceptance Criteria"
          multiline
          onChangeText={setAcceptanceCriteria}
          style={styles.multiline}
          value={acceptanceCriteria}
        />
        <TextField
          label="Service Notes"
          multiline
          onChangeText={setServiceNotes}
          style={styles.multiline}
          value={serviceNotes}
        />
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Bundle Requirements</Text>
        <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
          Enter the required quantity for each asset. Assets with `0` are excluded from the service.
        </Text>

        {assets.length === 0 ? (
          <EmptyState title="No assets in this laboratory" message="Add assets first, then link them into the service bundle." />
        ) : (
          assets.map((asset) => {
            const value = requirements[asset.id] ?? '0';
            return (
              <View
                key={asset.id}
                style={[
                  styles.assetRow,
                  {
                    backgroundColor: theme.colors.surfaceMuted,
                  },
                ]}
              >
                <View style={styles.assetMeta}>
                  <Text style={[styles.assetTitle, { color: theme.colors.text }]}>{asset.name}</Text>
                  <Text style={[styles.assetCaption, { color: theme.colors.textMuted }]}>
                    {asset.asset_code} {asset.model ? `| ${asset.model}` : ''}
                  </Text>
                  <Text style={[styles.assetCaption, { color: theme.colors.textMuted }]}>
                    Status {asset.status} | Available {asset.quantity}/{asset.total_quantity}
                  </Text>
                </View>
                <TextInput
                  keyboardType="number-pad"
                  onChangeText={(nextValue) => {
                    setRequirements((current) => ({
                      ...current,
                      [asset.id]: nextValue.replace(/[^0-9]/g, ''),
                    }));
                  }}
                  style={[
                    styles.quantityInput,
                    {
                      backgroundColor: theme.colors.surface,
                      borderColor: theme.colors.border,
                      color: theme.colors.text,
                    },
                  ]}
                  value={value}
                />
              </View>
            );
          })
        )}
      </View>

      {localMessage ? (
        <View style={[styles.feedbackCard, { backgroundColor: theme.colors.successSoft }]}>
          <Text style={[styles.feedbackText, { color: theme.colors.success }]}>{localMessage}</Text>
        </View>
      ) : null}
      {localError ? (
        <View style={[styles.feedbackCard, { backgroundColor: theme.colors.dangerSoft }]}>
          <Text style={[styles.feedbackText, { color: theme.colors.danger }]}>{localError}</Text>
        </View>
      ) : null}

      <Pressable
        disabled={saveMutation.isPending}
        onPress={() => {
          void saveMutation.mutateAsync();
        }}
        style={[
          styles.primaryButton,
          {
            backgroundColor: theme.colors.primary,
            opacity: saveMutation.isPending ? 0.7 : 1,
          },
        ]}
      >
        <Text style={styles.primaryButtonText}>
          {saveMutation.isPending ? 'Saving...' : isEditMode ? 'Save Service Bundle' : 'Create Service Bundle'}
        </Text>
      </Pressable>

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
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Delete Service Bundle</Text>
          <Pressable
            disabled={deleteMutation.isPending}
            onPress={confirmDelete}
            style={[
              styles.dangerButton,
              {
                backgroundColor: theme.colors.dangerSoft,
                opacity: deleteMutation.isPending ? 0.7 : 1,
              },
            ]}
          >
            <Text style={[styles.dangerButtonText, { color: theme.colors.danger }]}>
              {deleteMutation.isPending ? 'Deleting...' : 'Delete Service Bundle'}
            </Text>
          </Pressable>
        </View>
      ) : null}

      <SelectionModal
        onClose={() => setLabModalOpen(false)}
        onSelect={(value) => {
          setLaboratoryId(value ? Number(value) : null);
          setRequirements({});
        }}
        options={labs.map((lab) => ({
          id: String(lab.id),
          label: 'label' in lab ? lab.label : `${lab.name} - ${lab.room}`,
        }))}
        selectedId={laboratoryId ? String(laboratoryId) : null}
        title="Select Laboratory"
        visible={labModalOpen}
      />

      <SelectionModal
        onClose={() => setStatusModalOpen(false)}
        onSelect={(value) => setCalibrationStatus(value || 'unknown')}
        options={[
          { id: 'valid', label: 'Valid' },
          { id: 'expired', label: 'Expired' },
          { id: 'unknown', label: 'Unknown' },
        ]}
        selectedId={calibrationStatus}
        title="Calibration Status"
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
  toggleRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  statusToggle: {
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  statusToggleText: {
    fontSize: 12,
    fontWeight: '800',
  },
  multiline: {
    minHeight: 104,
    textAlignVertical: 'top',
  },
  helperText: {
    fontSize: 12,
    lineHeight: 18,
  },
  assetRow: {
    borderRadius: 14,
    flexDirection: 'row',
    gap: 12,
    padding: 14,
  },
  assetMeta: {
    flex: 1,
    gap: 3,
  },
  assetTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  assetCaption: {
    fontSize: 12,
    lineHeight: 17,
  },
  quantityInput: {
    borderRadius: 12,
    borderWidth: 1,
    minWidth: 68,
    paddingHorizontal: 12,
    paddingVertical: 10,
    textAlign: 'center',
  },
  feedbackCard: {
    borderRadius: 16,
    padding: 14,
  },
  feedbackText: {
    fontSize: 13,
    fontWeight: '700',
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
  dangerButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  dangerButtonText: {
    fontSize: 14,
    fontWeight: '800',
  },
});
