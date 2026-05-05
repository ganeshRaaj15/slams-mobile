import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import {
  createMaintenanceRequest,
  getMaintenanceRequest,
  listMaintenanceRequest,
  updateMaintenanceRequest,
} from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { PickerField } from '../components/picker-field';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { StatusPill } from '../components/status-pill';
import { TextField } from '../components/text-field';
import type { RootStackParamList } from '../navigation/types';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';
import { formatDateLabel } from '../utils/format';

const TRANSITION_LABELS: Record<string, string> = {
  scheduled: 'Move To Scheduled',
  in_progress: 'Start Repair',
  testing: 'Move To Testing',
  completed: 'Complete Case',
  cancelled: 'Cancel Case',
};

function splitScheduledFor(value: string) {
  const normalized = value.trim().replace(' ', 'T');

  if (!normalized) {
    return { date: '', time: '' };
  }

  return {
    date: normalized.slice(0, 10),
    time: normalized.slice(11, 16),
  };
}

export function MaintenanceFormScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'MaintenanceForm'>>();
  const queryClient = useQueryClient();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const maintenanceId = route.params?.maintenanceId;
  const isEdit = typeof maintenanceId === 'number' && maintenanceId > 0;

  const [selectedAssetId, setSelectedAssetId] = useState<number | null>(null);
  const [quantityAffected, setQuantityAffected] = useState('1');
  const [unitReference, setUnitReference] = useState('');
  const [title, setTitle] = useState('');
  const [issueType, setIssueType] = useState('preventive');
  const [priority, setPriority] = useState('medium');
  const [description, setDescription] = useState('');
  const [scheduledDate, setScheduledDate] = useState('');
  const [scheduledTime, setScheduledTime] = useState('');
  const [diagnosisNotes, setDiagnosisNotes] = useState('');
  const [workNotes, setWorkNotes] = useState('');
  const [testNotes, setTestNotes] = useState('');
  const [resolutionNotes, setResolutionNotes] = useState('');
  const [transition, setTransition] = useState('');
  const [pickedCompletionPhoto, setPickedCompletionPhoto] = useState<{
    uri: string;
    name: string;
    mimeType: string;
  } | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);
  const [showAssetPicker, setShowAssetPicker] = useState(false);
  const [showIssueTypePicker, setShowIssueTypePicker] = useState(false);
  const [showPriorityPicker, setShowPriorityPicker] = useState(false);
  const [initialized, setInitialized] = useState(false);

  const canUseMaintenance = role === 'technician';

  const detailQuery = useQuery({
    queryKey: ['maintenance-record', maintenanceId],
    queryFn: () => getMaintenanceRequest(maintenanceId!),
    enabled: canUseMaintenance && isEdit,
  });

  const createMetaQuery = useQuery({
    queryKey: ['maintenance-create-meta'],
    queryFn: () => listMaintenanceRequest({ scope: 'mine' }),
    enabled: canUseMaintenance && !isEdit,
  });

  const createMutation = useMutation({
    mutationFn: createMaintenanceRequest,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['maintenance-workspace'] });
      await queryClient.invalidateQueries({ queryKey: ['notifications'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
      navigation.goBack();
    },
  });

  const updateMutation = useMutation({
    mutationFn: (payload: Parameters<typeof updateMaintenanceRequest>[1]) =>
      updateMaintenanceRequest(maintenanceId!, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['maintenance-workspace'] });
      await queryClient.invalidateQueries({ queryKey: ['maintenance-record', maintenanceId] });
      await queryClient.invalidateQueries({ queryKey: ['notifications'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
      navigation.goBack();
    },
  });

  const activeData = isEdit ? detailQuery.data : createMetaQuery.data;
  const assetOptions = activeData?.assets ?? [];
  const issueTypeOptions = isEdit
    ? detailQuery.data?.issue_types ?? []
    : createMetaQuery.data?.issue_types ?? [];
  const priorityOptions = isEdit
    ? detailQuery.data?.priorities ?? []
    : createMetaQuery.data?.priorities ?? [];
  const selectedAsset = assetOptions.find((asset) => asset.id === selectedAssetId) ?? null;

  const pickerAssets = useMemo(
    () =>
      assetOptions.map((asset) => ({
        id: String(asset.id),
        label: asset.name,
        subtitle: `${asset.lab_name || 'No lab'}  |  Available ${asset.quantity}/${asset.total_quantity}`,
      })),
    [assetOptions],
  );

  useEffect(() => {
    if (!isEdit || !detailQuery.data || initialized) {
      return;
    }

    const { record, issue_types: recordIssueTypes } = detailQuery.data;
    setSelectedAssetId(record.asset_id);
    setQuantityAffected(String(record.quantity_affected || 1));
    setUnitReference(record.unit_reference || '');
    setTitle(record.title || '');
    setIssueType(record.issue_type || recordIssueTypes[0] || 'preventive');
    setPriority(record.priority || 'medium');
    setDescription(record.description || '');
    const scheduled = splitScheduledFor(record.scheduled_for || '');
    setScheduledDate(scheduled.date);
    setScheduledTime(scheduled.time);
    setDiagnosisNotes(record.diagnosis_notes || '');
    setWorkNotes(record.work_notes || '');
    setTestNotes(record.test_notes || '');
    setResolutionNotes(record.resolution_notes || '');
    setTransition(record.next_statuses[0] ?? '');
    setInitialized(true);
  }, [detailQuery.data, initialized, isEdit]);

  useEffect(() => {
    if (isEdit || !createMetaQuery.data || initialized) {
      return;
    }

    setSelectedAssetId(createMetaQuery.data.assets[0]?.id ?? null);
    setIssueType(createMetaQuery.data.issue_types[0] ?? 'preventive');
    setPriority(createMetaQuery.data.priorities[1] ?? createMetaQuery.data.priorities[0] ?? 'medium');
    setInitialized(true);
  }, [createMetaQuery.data, initialized, isEdit]);

  if (!canUseMaintenance) {
    return (
      <Screen>
        <EmptyState
          title="No maintenance access"
          message="Only technician users can create or update maintenance cases."
        />
      </Screen>
    );
  }

  if ((isEdit && detailQuery.isLoading) || (!isEdit && createMetaQuery.isLoading)) {
    return (
      <Screen scroll={false}>
        <LoadingState label={isEdit ? 'Loading maintenance case...' : 'Loading maintenance form...'} />
      </Screen>
    );
  }

  if ((isEdit && (detailQuery.isError || !detailQuery.data)) || (!isEdit && (createMetaQuery.isError || !createMetaQuery.data))) {
    return (
      <Screen>
        <ErrorState
          message="The maintenance workflow data could not be loaded."
          onRetry={() => {
            if (isEdit) {
              void detailQuery.refetch();
              return;
            }

            void createMetaQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const record = detailQuery.data?.record;

  async function pickCompletionPhoto() {
    const result = await DocumentPicker.getDocumentAsync({
      copyToCacheDirectory: true,
      multiple: false,
      type: ['image/*'],
    });

    if (result.canceled || !result.assets?.[0]) {
      return;
    }

    const asset = result.assets[0];
    setPickedCompletionPhoto({
      uri: asset.uri,
      name: asset.name || 'completion-photo.jpg',
      mimeType: asset.mimeType || 'image/jpeg',
    });
  }

  async function handleSubmit() {
    if (!selectedAsset) {
      setLocalError('Select the equipment for this maintenance case.');
      return;
    }

    if ((scheduledDate && !scheduledTime) || (!scheduledDate && scheduledTime)) {
      setLocalError('Scheduled date and time must both be set, or both left blank.');
      return;
    }

    setLocalError(null);

    try {
      if (!isEdit) {
        await createMutation.mutateAsync({
          asset_id: selectedAsset.id,
          quantity_affected: Math.max(Number(quantityAffected) || 0, 0),
          unit_reference: unitReference.trim(),
          title: title.trim(),
          issue_type: issueType,
          priority,
          description: description.trim(),
          scheduled_for: scheduledDate && scheduledTime ? `${scheduledDate}T${scheduledTime}` : '',
          diagnosis_notes: diagnosisNotes.trim(),
        });
        return;
      }

      await updateMutation.mutateAsync({
        asset_id: selectedAsset.id,
        quantity_affected: Math.max(Number(quantityAffected) || 0, 0),
        unit_reference: unitReference.trim(),
        title: title.trim(),
        issue_type: issueType,
        priority,
        description: description.trim(),
        scheduled_for: scheduledDate && scheduledTime ? `${scheduledDate}T${scheduledTime}` : '',
        diagnosis_notes: diagnosisNotes.trim(),
        work_notes: workNotes.trim(),
        test_notes: testNotes.trim(),
        resolution_notes: resolutionNotes.trim(),
        transition,
        completion_photo: pickedCompletionPhoto,
      });
    } catch (error) {
      setLocalError(readErrorMessage(error, 'Maintenance update failed.'));
    }
  }

  return (
    <Screen>
      {record ? (
        <View
          style={[
            styles.card,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <View style={styles.heroHeader}>
            <View style={styles.heroTitleWrap}>
              <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>{record.title}</Text>
              <Text style={[styles.heroMeta, { color: theme.colors.textMuted }]}>
                {record.asset_name}  |  {record.lab_name}
              </Text>
            </View>
            <StatusPill kind="maintenance" status={record.status} />
          </View>
          {record.asset_prediction ? (
            <View
              style={[
                styles.predictionCard,
                {
                  backgroundColor: theme.colors.primarySoft,
                },
              ]}
            >
              <Text style={[styles.predictionTitle, { color: theme.colors.primary }]}>
                Predictive maintenance signal
              </Text>
              <Text style={[styles.predictionText, { color: theme.colors.text }]}>
                Risk {record.asset_prediction.risk_percent}%  |  {record.asset_prediction.decision?.label || 'Normal monitoring'}
              </Text>
              {record.asset_prediction.reasons?.[0] ? (
                <Text style={[styles.predictionText, { color: theme.colors.textMuted }]}>
                  {record.asset_prediction.reasons[0]}
                </Text>
              ) : null}
            </View>
          ) : null}
        </View>
      ) : null}

      <View
        style={[
          styles.card,
          {
            backgroundColor: theme.colors.surface,
            borderColor: theme.colors.border,
          },
        ]}
      >
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>
          {isEdit ? 'Update Maintenance Case' : 'Plan Maintenance Case'}
        </Text>

        <Pressable
          onPress={() => setShowAssetPicker(true)}
          style={[
            styles.selector,
            {
              backgroundColor: theme.colors.surfaceMuted,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.selectorLabel, { color: theme.colors.text }]}>Equipment</Text>
          <Text style={[styles.selectorValue, { color: theme.colors.primary }]}>
            {selectedAsset ? `${selectedAsset.name} (${selectedAsset.asset_code || 'No code'})` : 'Select asset'}
          </Text>
          {selectedAsset ? (
            <Text style={[styles.selectorMeta, { color: theme.colors.textMuted }]}>
              {selectedAsset.lab_name}  |  Available {selectedAsset.quantity}/{selectedAsset.total_quantity}
            </Text>
          ) : null}
        </Pressable>

        <TextField
          keyboardType="number-pad"
          label="Affected quantity"
          onChangeText={setQuantityAffected}
          placeholder="1"
          value={quantityAffected}
        />

        <TextField
          label="Unit reference"
          hint="Required for shared or multi-unit equipment."
          onChangeText={setUnitReference}
          placeholder="Station B-02"
          value={unitReference}
        />

        <TextField label="Case title" onChangeText={setTitle} placeholder="Quarterly calibration" value={title} />

        <View style={styles.pickerRow}>
          <Pressable
            onPress={() => {
              if (issueTypeOptions.length > 1) {
                setShowIssueTypePicker(true);
              }
            }}
            style={[
              styles.pickerHalf,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.selectorLabel, { color: theme.colors.text }]}>Issue type</Text>
            <Text style={[styles.selectorValue, { color: theme.colors.primary }]}>
              {issueType.replace('_', ' ').toUpperCase()}
            </Text>
          </Pressable>

          <Pressable
            onPress={() => setShowPriorityPicker(true)}
            style={[
              styles.pickerHalf,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.selectorLabel, { color: theme.colors.text }]}>Priority</Text>
            <Text style={[styles.selectorValue, { color: theme.colors.primary }]}>
              {priority.replace('_', ' ').toUpperCase()}
            </Text>
          </Pressable>
        </View>

        <TextField
          label="Description"
          multiline
          numberOfLines={4}
          onChangeText={setDescription}
          placeholder="Describe the maintenance scope or the reported fault."
          style={styles.multilineInput}
          textAlignVertical="top"
          value={description}
        />

        <PickerField
          allowClear
          label="Scheduled date"
          mode="date"
          onChangeValue={setScheduledDate}
          placeholder="Select scheduled date"
          value={scheduledDate}
        />
        <PickerField
          allowClear
          label="Scheduled time"
          mode="time"
          onChangeValue={setScheduledTime}
          placeholder="Select scheduled time"
          value={scheduledTime}
        />

        <TextField
          label="Diagnosis notes"
          multiline
          numberOfLines={4}
          onChangeText={setDiagnosisNotes}
          placeholder="What did you find during the initial inspection?"
          style={styles.multilineInput}
          textAlignVertical="top"
          value={diagnosisNotes}
        />

        {isEdit ? (
          <>
            <TextField
              label="Work notes"
              multiline
              numberOfLines={4}
              onChangeText={setWorkNotes}
              placeholder="Describe the repair or servicing work carried out."
              style={styles.multilineInput}
              textAlignVertical="top"
              value={workNotes}
            />
            <TextField
              label="Test notes"
              multiline
              numberOfLines={4}
              onChangeText={setTestNotes}
              placeholder="Record testing or verification results."
              style={styles.multilineInput}
              textAlignVertical="top"
              value={testNotes}
            />
            <TextField
              label="Resolution notes"
              multiline
              numberOfLines={4}
              onChangeText={setResolutionNotes}
              placeholder="Summarize the case closure outcome."
              style={styles.multilineInput}
              textAlignVertical="top"
              value={resolutionNotes}
            />

            {transition === 'completed' || record?.completion_photo_url ? (
              <View style={styles.photoWrap}>
                <Pressable
                  onPress={() => {
                    void pickCompletionPhoto();
                  }}
                  style={[
                    styles.secondaryButton,
                    {
                      backgroundColor: theme.colors.surfaceMuted,
                    },
                  ]}
                >
                  <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>
                    {pickedCompletionPhoto || record?.completion_photo_url ? 'Replace Completion Photo' : 'Attach Completion Photo'}
                  </Text>
                </Pressable>
                {pickedCompletionPhoto ? (
                  <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
                    Attached: {pickedCompletionPhoto.name}
                  </Text>
                ) : record?.completion_photo_url ? (
                  <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
                    A completion photo is already stored for this case.
                  </Text>
                ) : null}
              </View>
            ) : null}
          </>
        ) : null}

        {localError ? (
          <Text style={[styles.errorText, { color: theme.colors.danger }]}>{localError}</Text>
        ) : null}

        {isEdit ? (
          <View style={styles.transitionWrap}>
            <Text style={[styles.sectionSubtitle, { color: theme.colors.text }]}>Next action</Text>
            <View style={styles.transitionButtons}>
              {(record?.next_statuses ?? []).map((status) => {
                const active = transition === status;
                return (
                  <Pressable
                    key={status}
                    onPress={() => setTransition(status)}
                    style={[
                      styles.transitionButton,
                      {
                        backgroundColor: active ? theme.colors.primarySoft : theme.colors.surfaceMuted,
                        borderColor: active ? theme.colors.primary : theme.colors.border,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.transitionButtonText,
                        {
                          color: active ? theme.colors.primary : theme.colors.text,
                        },
                      ]}
                    >
                      {TRANSITION_LABELS[status] ?? status}
                    </Text>
                  </Pressable>
                );
              })}
            </View>

            <Pressable
              disabled={updateMutation.isPending || !transition}
              onPress={() => {
                void handleSubmit();
              }}
              style={[
                styles.primaryButton,
                {
                  backgroundColor: theme.colors.primary,
                  opacity: updateMutation.isPending || !transition ? 0.7 : 1,
                },
              ]}
            >
              <Text style={styles.primaryButtonText}>
                {transition ? TRANSITION_LABELS[transition] || 'Save Transition' : 'Choose Transition'}
              </Text>
            </Pressable>
          </View>
        ) : (
          <Pressable
            disabled={createMutation.isPending}
            onPress={() => {
              void handleSubmit();
            }}
            style={[
              styles.primaryButton,
              {
                backgroundColor: theme.colors.primary,
                opacity: createMutation.isPending ? 0.7 : 1,
              },
            ]}
          >
            <Text style={styles.primaryButtonText}>Create Maintenance Case</Text>
          </Pressable>
        )}
      </View>

      {record?.logs?.length ? (
        <View
          style={[
            styles.card,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Workflow Log</Text>
          {record.logs.map((log) => (
            <View
              key={log.id}
              style={[
                styles.logCard,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text style={[styles.logTitle, { color: theme.colors.text }]}>
                {(log.from_status || 'New').replace('_', ' ')}
                {' -> '}
                {log.to_status.replace('_', ' ')}
              </Text>
              <Text style={[styles.logMeta, { color: theme.colors.primary }]}>
                {log.changed_by}  |  {formatDateLabel(log.created_at)}
              </Text>
              <Text style={[styles.logMeta, { color: theme.colors.textMuted }]}>{log.notes}</Text>
            </View>
          ))}
        </View>
      ) : null}

      <SelectionModal
        onClose={() => setShowAssetPicker(false)}
        onSelect={(value) => setSelectedAssetId(Number(value))}
        options={pickerAssets}
        selectedId={selectedAssetId ? String(selectedAssetId) : null}
        title="Select Asset"
        visible={showAssetPicker}
      />

      <SelectionModal
        onClose={() => setShowIssueTypePicker(false)}
        onSelect={setIssueType}
        options={issueTypeOptions.map((item) => ({
          id: item,
          label: item.replace('_', ' ').toUpperCase(),
        }))}
        selectedId={issueType}
        title="Select Issue Type"
        visible={showIssueTypePicker}
      />

      <SelectionModal
        onClose={() => setShowPriorityPicker(false)}
        onSelect={setPriority}
        options={priorityOptions.map((item) => ({
          id: item,
          label: item.replace('_', ' ').toUpperCase(),
        }))}
        selectedId={priority}
        title="Select Priority"
        visible={showPriorityPicker}
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
  heroHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  heroTitleWrap: {
    flex: 1,
    gap: 4,
    paddingRight: 12,
  },
  sectionTitle: {
    fontSize: 19,
    fontWeight: '800',
  },
  sectionSubtitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  heroMeta: {
    fontSize: 13,
  },
  predictionCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  predictionTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  predictionText: {
    fontSize: 13,
    lineHeight: 18,
  },
  selector: {
    borderRadius: 14,
    borderWidth: 1,
    gap: 4,
    padding: 14,
  },
  selectorLabel: {
    fontSize: 13,
    fontWeight: '700',
  },
  selectorValue: {
    fontSize: 15,
    fontWeight: '800',
  },
  selectorMeta: {
    fontSize: 12,
  },
  pickerRow: {
    flexDirection: 'row',
    gap: 12,
  },
  pickerHalf: {
    borderRadius: 14,
    borderWidth: 1,
    flex: 1,
    gap: 4,
    padding: 14,
  },
  multilineInput: {
    minHeight: 110,
    paddingTop: 12,
  },
  helperText: {
    fontSize: 12,
    lineHeight: 18,
  },
  photoWrap: {
    gap: 8,
  },
  secondaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  secondaryButtonText: {
    fontSize: 14,
    fontWeight: '800',
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
  transitionWrap: {
    gap: 12,
  },
  transitionButtons: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  transitionButton: {
    borderRadius: 12,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  transitionButtonText: {
    fontSize: 13,
    fontWeight: '800',
  },
  errorText: {
    fontSize: 13,
    fontWeight: '700',
  },
  logCard: {
    borderRadius: 14,
    gap: 6,
    padding: 14,
  },
  logTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  logMeta: {
    fontSize: 12,
    lineHeight: 18,
  },
});
