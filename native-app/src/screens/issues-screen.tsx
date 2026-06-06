import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { createIssueReportRequest, getIssueWorkspaceRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { SelectionModal } from '../components/selection-modal';
import { StatusPill } from '../components/status-pill';
import { TextField } from '../components/text-field';
import { useAuthStore } from '../state/auth-store';
import { useAppTheme } from '../theme/use-app-theme';
import { useResponsiveLayout } from '../theme/use-responsive-layout';
import { readErrorMessage } from '../utils/error-message';
import { formatDateLabel } from '../utils/format';

const REPORTER_ROLES = ['student', 'staff', 'pic'];

export function IssuesScreen() {
  const theme = useAppTheme();
  const responsive = useResponsiveLayout();
  const queryClient = useQueryClient();
  const role = useAuthStore((state) => state.user?.primary_role ?? 'student');
  const canReport = REPORTER_ROLES.includes(role);

  const [selectedAssetId, setSelectedAssetId] = useState<number | null>(null);
  const [quantityAffected, setQuantityAffected] = useState('1');
  const [title, setTitle] = useState('');
  const [priority, setPriority] = useState('medium');
  const [description, setDescription] = useState('');
  const [unitReference, setUnitReference] = useState('');
  const [pickedPhoto, setPickedPhoto] = useState<{
    uri: string;
    name: string;
    mimeType: string;
  } | null>(null);
  const [showAssetPicker, setShowAssetPicker] = useState(false);
  const [showPriorityPicker, setShowPriorityPicker] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  const workspaceQuery = useQuery({
    queryKey: ['issues-workspace'],
    queryFn: getIssueWorkspaceRequest,
    enabled: canReport,
  });

  const submitMutation = useMutation({
    mutationFn: createIssueReportRequest,
    onSuccess: async () => {
      setQuantityAffected('1');
      setTitle('');
      setPriority('medium');
      setDescription('');
      setUnitReference('');
      setPickedPhoto(null);
      setLocalError(null);
      await queryClient.invalidateQueries({ queryKey: ['issues-workspace'] });
      await queryClient.invalidateQueries({ queryKey: ['notifications'] });
      await queryClient.invalidateQueries({ queryKey: ['bootstrap'] });
    },
  });

  const assets = workspaceQuery.data?.assets ?? [];
  const selectedAsset = assets.find((asset) => asset.id === selectedAssetId) ?? null;

  const assetOptions = useMemo(
    () =>
      assets.map((asset) => ({
        id: String(asset.id),
        label: asset.name,
        subtitle: `${asset.lab_name || 'No lab'}  |  Available ${asset.quantity}/${asset.total_quantity}`,
      })),
    [assets],
  );

  if (!canReport) {
    return (
      <Screen maxWidth="wide">
        <EmptyState
          title="No issue reporting"
          message="This mobile workspace is available to student, staff, and PIC roles that report equipment problems."
        />
      </Screen>
    );
  }

  if (workspaceQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading issue reporting workspace..." />
      </Screen>
    );
  }

  if (workspaceQuery.isError || !workspaceQuery.data) {
    return (
      <Screen maxWidth="wide">
        <ErrorState
          message="Issue reporting data could not be loaded."
          onRetry={() => {
            void workspaceQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  async function pickPhoto() {
    const result = await DocumentPicker.getDocumentAsync({
      copyToCacheDirectory: true,
      multiple: false,
      type: ['image/*'],
    });

    if (result.canceled || !result.assets?.[0]) {
      return;
    }

    const asset = result.assets[0];
    setPickedPhoto({
      uri: asset.uri,
      name: asset.name || 'issue-photo.jpg',
      mimeType: asset.mimeType || 'image/jpeg',
    });
  }

  async function handleSubmit() {
    if (!selectedAsset) {
      setLocalError('Select the affected equipment before submitting the issue report.');
      return;
    }

    setLocalError(null);

    try {
      await submitMutation.mutateAsync({
        asset_id: selectedAsset.id,
        quantity_affected: Math.max(Number(quantityAffected) || 0, 0),
        title: title.trim(),
        priority,
        description: description.trim(),
        unit_reference: unitReference.trim(),
        report_photo: pickedPhoto,
      });
    } catch (error) {
      setLocalError(readErrorMessage(error, 'Issue report submission failed.'));
    }
  }

  return (
    <Screen maxWidth="wide">
      <View style={responsive.isTabletLandscape ? styles.contentGrid : undefined}>
        <View
          style={[
            styles.card,
            responsive.isTabletLandscape && styles.primaryColumn,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Report Asset Issue</Text>
          <Text style={[styles.helperText, { color: theme.colors.textMuted }]}>
            Corrective maintenance starts here. The assigned lab PIC will see the case in the maintenance workflow.
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
            <Text style={[styles.selectorLabel, { color: theme.colors.text }]}>Affected equipment</Text>
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
            hint="Required for multi-unit equipment. Use workstation number, seat number, or physical label."
            onChangeText={setUnitReference}
            placeholder="Workstation A-03"
            value={unitReference}
          />

          <TextField
            label="Issue title"
            onChangeText={setTitle}
            placeholder="Microscope objective not focusing"
            value={title}
          />

          <Pressable
            onPress={() => setShowPriorityPicker(true)}
            style={[
              styles.selector,
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

          <TextField
            label="Description"
            multiline
            numberOfLines={5}
            onChangeText={setDescription}
            placeholder="Describe the fault, the symptoms, and what happened before the problem occurred."
            style={styles.multilineInput}
            textAlignVertical="top"
            value={description}
          />

          <View style={[styles.actionRow, responsive.isTabletLandscape && styles.actionRowWide]}>
            <Pressable
              onPress={() => {
                void pickPhoto();
              }}
              style={[
                styles.secondaryButton,
                {
                  backgroundColor: theme.colors.surfaceMuted,
                },
              ]}
            >
              <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>
                {pickedPhoto ? 'Replace Photo' : 'Attach Photo'}
              </Text>
            </Pressable>

            <Pressable
              disabled={submitMutation.isPending}
              onPress={() => {
                void handleSubmit();
              }}
              style={[
                styles.primaryButton,
                {
                  backgroundColor: theme.colors.primary,
                  opacity: submitMutation.isPending ? 0.7 : 1,
                },
              ]}
            >
              <Text style={styles.primaryButtonText}>Submit Issue</Text>
            </Pressable>
          </View>

          {pickedPhoto ? (
            <Text style={[styles.fileName, { color: theme.colors.textMuted }]}>
              Attached photo: {pickedPhoto.name}
            </Text>
          ) : null}

          {localError ? (
            <Text style={[styles.errorText, { color: theme.colors.danger }]}>{localError}</Text>
          ) : null}
        </View>

        <View
          style={[
            styles.card,
            responsive.isTabletLandscape && styles.secondaryColumn,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Recent Reports</Text>
          {workspaceQuery.data.recent_reports.length === 0 ? (
            <EmptyState
              title="No reports yet"
              message="Issue reports you submit from SLAMS will appear here for quick status tracking."
            />
          ) : (
            workspaceQuery.data.recent_reports.map((report) => (
              <View
                key={report.id}
                style={[
                  styles.reportCard,
                  {
                    backgroundColor: theme.colors.surfaceMuted,
                  },
                ]}
              >
                <View style={styles.reportHeader}>
                  <View style={styles.reportTitleWrap}>
                    <Text style={[styles.reportTitle, { color: theme.colors.text }]}>{report.title}</Text>
                    <Text style={[styles.reportMeta, { color: theme.colors.textMuted }]}>
                      {report.asset_name}  |  {report.lab_name}
                    </Text>
                  </View>
                  <StatusPill kind="maintenance" status={report.status} />
                </View>
                <Text style={[styles.reportMeta, { color: theme.colors.primary }]}>
                  Submitted {formatDateLabel(report.created_at)}
                </Text>
                <Text style={[styles.reportMeta, { color: theme.colors.textMuted }]}>
                  Quantity: {report.quantity_affected}
                  {report.unit_reference ? `  |  Unit ${report.unit_reference}` : ''}
                </Text>
              </View>
            ))
          )}
        </View>
      </View>

      <SelectionModal
        onClose={() => setShowAssetPicker(false)}
        onSelect={(value) => setSelectedAssetId(Number(value))}
        options={assetOptions}
        selectedId={selectedAssetId ? String(selectedAssetId) : null}
        title="Select Asset"
        visible={showAssetPicker}
      />

      <SelectionModal
        onClose={() => setShowPriorityPicker(false)}
        onSelect={setPriority}
        options={workspaceQuery.data.priorities.map((item) => ({
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
  contentGrid: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: 18,
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 12,
    padding: 16,
  },
  primaryColumn: {
    flex: 1.2,
  },
  secondaryColumn: {
    flex: 0.9,
  },
  sectionTitle: {
    fontSize: 19,
    fontWeight: '800',
  },
  helperText: {
    fontSize: 13,
    lineHeight: 19,
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
  multilineInput: {
    minHeight: 120,
    paddingTop: 12,
  },
  actionRow: {
    flexDirection: 'row',
    gap: 12,
  },
  actionRowWide: {
    paddingTop: 4,
  },
  secondaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    flex: 1,
    paddingVertical: 14,
  },
  secondaryButtonText: {
    fontSize: 14,
    fontWeight: '800',
  },
  primaryButton: {
    alignItems: 'center',
    borderRadius: 14,
    flex: 1,
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '800',
  },
  fileName: {
    fontSize: 12,
  },
  errorText: {
    fontSize: 13,
    fontWeight: '700',
  },
  reportCard: {
    borderRadius: 14,
    gap: 8,
    padding: 14,
  },
  reportHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  reportTitleWrap: {
    flex: 1,
    gap: 4,
    paddingRight: 12,
  },
  reportTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  reportMeta: {
    fontSize: 12,
    lineHeight: 18,
  },
});
