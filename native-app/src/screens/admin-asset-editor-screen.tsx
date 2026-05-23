import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, Text, View } from 'react-native';

import {
  createAdminAssetRequest,
  deleteAdminAssetRequest,
  getAdminAssetRequest,
  listAdminAssetsRequest,
  updateAdminAssetRequest,
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
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';
import { formatDateLabel } from '../utils/format';

type PickedImage = {
  uri: string;
  name: string;
  mimeType: string;
};

function riskTone(band: string, theme: ReturnType<typeof useAppTheme>) {
  if (band === 'high') {
    return {
      accent: theme.colors.danger,
      background: theme.colors.dangerSoft,
      label: 'High risk',
    };
  }
  if (band === 'medium') {
    return {
      accent: theme.colors.warning,
      background: theme.colors.warningSoft,
      label: 'Review soon',
    };
  }

  return {
    accent: theme.colors.success,
    background: theme.colors.successSoft,
    label: 'Stable',
  };
}

export function AdminAssetEditorScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'AdminAssetEditor'>>();
  const queryClient = useQueryClient();
  const assetId = route.params?.assetId ?? null;
  const isEditMode = assetId !== null;

  const [assetCode, setAssetCode] = useState('');
  const [name, setName] = useState('');
  const [category, setCategory] = useState('');
  const [brand, setBrand] = useState('');
  const [model, setModel] = useState('');
  const [serialNumber, setSerialNumber] = useState('');
  const [labId, setLabId] = useState<number | null>(null);
  const [totalQuantity, setTotalQuantity] = useState('1');
  const [locationNote, setLocationNote] = useState('');
  const [purchaseDate, setPurchaseDate] = useState('');
  const [specifications, setSpecifications] = useState('');
  const [image, setImage] = useState<PickedImage | null>(null);
  const [localMessage, setLocalMessage] = useState<string | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);
  const [labModalOpen, setLabModalOpen] = useState(false);
  const [initialized, setInitialized] = useState(false);

  const detailQuery = useQuery({
    queryKey: ['admin-asset', assetId],
    queryFn: () => getAdminAssetRequest(assetId as number),
    enabled: isEditMode,
  });

  const catalogQuery = useQuery({
    queryKey: ['admin-assets', 'catalog'],
    queryFn: () => listAdminAssetsRequest(),
  });

  useEffect(() => {
    if (!isEditMode || !detailQuery.data?.asset || initialized) {
      return;
    }

    const { asset } = detailQuery.data;
    setAssetCode(asset.asset_code ?? '');
    setName(asset.name ?? '');
    setCategory(asset.category ?? '');
    setBrand(asset.brand ?? '');
    setModel(asset.model ?? '');
    setSerialNumber(asset.serial_number ?? '');
    setLabId(asset.lab_id ?? null);
    setTotalQuantity(asset.total_quantity ? String(asset.total_quantity) : '1');
    setLocationNote(asset.location_note ?? '');
    setPurchaseDate(asset.purchase_date ?? '');
    setSpecifications(asset.specifications ?? '');
    setInitialized(true);
  }, [detailQuery.data, initialized, isEditMode]);

  useEffect(() => {
    if (isEditMode || !catalogQuery.data || initialized) {
      return;
    }

    setLabId(catalogQuery.data.labs[0]?.id ?? null);
    setInitialized(true);
  }, [catalogQuery.data, initialized, isEditMode]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const resolvedLabId = labId ?? 0;
      const payload = {
        asset_code: assetCode.trim(),
        name: name.trim(),
        category: category.trim(),
        brand: brand.trim(),
        model: model.trim(),
        serial_number: serialNumber.trim(),
        lab_id: resolvedLabId,
        total_quantity: totalQuantity.trim(),
        location_note: locationNote.trim(),
        purchase_date: purchaseDate.trim(),
        specifications: specifications.trim(),
        image,
      };

      if (isEditMode) {
        return updateAdminAssetRequest(assetId as number, payload);
      }

      return createAdminAssetRequest(payload);
    },
    onSuccess: async () => {
      setLocalError(null);
      setLocalMessage(isEditMode ? 'Asset updated successfully.' : 'Asset created successfully.');
      setImage(null);
      await queryClient.invalidateQueries({ queryKey: ['admin-assets'] });
      if (isEditMode) {
        await queryClient.invalidateQueries({ queryKey: ['admin-asset', assetId] });
        return;
      }
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, isEditMode ? 'Asset update failed.' : 'Asset save failed.'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteAdminAssetRequest(assetId as number),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-assets'] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, 'Asset deletion failed.'));
    },
  });

  const labs = detailQuery.data?.labs ?? catalogQuery.data?.labs ?? [];
  const asset = detailQuery.data?.asset;
  const assetTone = asset ? riskTone(asset.risk_band ?? 'low', theme) : null;
  const selectedLab = useMemo(() => labs.find((item) => item.id === labId) ?? null, [labId, labs]);
  const imageUri = image?.uri || asset?.image_url || '';

  if ((isEditMode && detailQuery.isLoading) || catalogQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label={isEditMode ? 'Loading asset details...' : 'Loading asset editor...'} />
      </Screen>
    );
  }

  if ((isEditMode && (detailQuery.isError || !detailQuery.data)) || catalogQuery.isError || !catalogQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="The asset editor could not be loaded."
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

  async function pickImage() {
    const result = await DocumentPicker.getDocumentAsync({
      copyToCacheDirectory: true,
      multiple: false,
      type: ['image/*'],
    });

    if (result.canceled || !result.assets?.[0]) {
      return;
    }

    const assetResult = result.assets[0];
    setImage({
      uri: assetResult.uri,
      name: assetResult.name || 'asset-image.jpg',
      mimeType: assetResult.mimeType || 'image/jpeg',
    });
  }

  function confirmDelete() {
    Alert.alert(
      'Delete asset',
      'This asset can only be deleted if it has no maintenance history.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: () => {
            void deleteMutation.mutateAsync();
          },
        },
      ],
    );
  }

  return (
    <Screen>
      {asset ? (
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
            <View style={styles.heroMeta}>
              <Text style={[styles.title, { color: theme.colors.text }]}>{asset.name}</Text>
              <Text style={[styles.metaText, { color: theme.colors.primary }]}>
                {asset.asset_code}  |  {asset.lab_name || 'No lab'}
              </Text>
              <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
                Available {asset.quantity}/{asset.total_quantity}  |  Under maintenance {asset.maintenance_quantity}
              </Text>
            </View>
            <StatusPill kind="asset" status={asset.status} />
          </View>
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
          {isEditMode ? 'Edit Asset' : 'Create Asset'}
        </Text>
        <TextField
          autoCapitalize="characters"
          label="Asset code"
          onChangeText={setAssetCode}
          placeholder="AST-0001"
          value={assetCode}
        />
        <TextField label="Asset name" onChangeText={setName} value={name} />
        <TextField label="Category" onChangeText={setCategory} value={category} />
        <TextField label="Brand" onChangeText={setBrand} value={brand} />
        <TextField label="Model" onChangeText={setModel} value={model} />
        <TextField label="Serial number" onChangeText={setSerialNumber} value={serialNumber} />

        <View style={styles.fieldWrap}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Laboratory</Text>
          <Pressable
            onPress={() => {
              setLabModalOpen(true);
            }}
            style={[
              styles.selector,
              {
                backgroundColor: theme.colors.surfaceMuted,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.selectorText, { color: theme.colors.text }]}>
              {selectedLab?.label ?? 'Select laboratory'}
            </Text>
          </Pressable>
        </View>

        <TextField
          keyboardType="number-pad"
          label="Total quantity"
          onChangeText={setTotalQuantity}
          placeholder="1"
          value={totalQuantity}
        />
        <TextField label="Location note" onChangeText={setLocationNote} value={locationNote} />
        <PickerField
          allowClear
          label="Purchase date"
          mode="date"
          onChangeValue={setPurchaseDate}
          placeholder="Select purchase date"
          value={purchaseDate}
        />
        <TextField
          label="Specifications"
          multiline
          numberOfLines={4}
          onChangeText={setSpecifications}
          style={styles.multilineInput}
          textAlignVertical="top"
          value={specifications}
        />

        <View style={styles.imageGroup}>
          <Text style={[styles.fieldLabel, { color: theme.colors.text }]}>Asset image</Text>
          {imageUri ? <Image source={{ uri: imageUri }} style={styles.previewImage} /> : null}
          <Pressable
            onPress={() => {
              void pickImage();
            }}
            style={[
              styles.secondaryButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>
              {imageUri ? 'Replace Asset Image' : 'Attach Asset Image'}
            </Text>
          </Pressable>
        </View>
      </View>

      {localMessage ? (
        <View
          style={[
            styles.feedbackCard,
            {
              backgroundColor: theme.colors.successSoft,
            },
          ]}
        >
          <Text style={[styles.feedbackText, { color: theme.colors.success }]}>{localMessage}</Text>
        </View>
      ) : null}
      {localError ? (
        <View
          style={[
            styles.feedbackCard,
            {
              backgroundColor: theme.colors.dangerSoft,
            },
          ]}
        >
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
          {saveMutation.isPending ? 'Saving...' : isEditMode ? 'Save Asset' : 'Create Asset'}
        </Text>
      </Pressable>

      {asset ? (
        <>
          <View
            style={[
              styles.card,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Predictive Maintenance Insight</Text>
            <View style={styles.summaryRow}>
              <View style={[styles.summaryPill, { backgroundColor: assetTone?.background ?? theme.colors.successSoft }]}>
                <Text style={[styles.summaryText, { color: assetTone?.accent ?? theme.colors.success }]}>
                  {assetTone?.label ?? 'Stable'}
                </Text>
              </View>
              <View style={[styles.summaryPill, { backgroundColor: theme.colors.primarySoft }]}>
                <Text style={[styles.summaryText, { color: theme.colors.primary }]}>
                  Risk {asset.risk_percent}%
                </Text>
              </View>
              <View style={[styles.summaryPill, { backgroundColor: theme.colors.surfaceMuted }]}>
                <Text style={[styles.summaryText, { color: theme.colors.text }]}>
                  {asset.decision_priority.toUpperCase()} PRIORITY
                </Text>
              </View>
            </View>
            <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>{asset.decision_label}</Text>
            <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
              Bookings in last 30 days {asset.bookings_last_30d}  |  Bookings in last 90 days {asset.bookings_last_90d}
            </Text>
            {asset.next_due_at ? (
              <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
                Next estimated due date {formatDateLabel(asset.next_due_at)}
              </Text>
            ) : null}
            {asset.reasons.length ? (
              <View style={styles.reasonGroup}>
                <Text style={[styles.reasonHeading, { color: theme.colors.textMuted }]}>
                  Why this asset was flagged
                </Text>
                {asset.reasons.map((reason, index) => (
                  <Text key={`asset-reason-${index}`} style={[styles.metaText, { color: theme.colors.textMuted }]}>
                    {'• '}{reason}
                  </Text>
                ))}
              </View>
            ) : null}
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
            <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Maintenance Summary</Text>
            <View style={styles.summaryRow}>
              <View
                style={[
                  styles.summaryPill,
                  {
                    backgroundColor: theme.colors.warningSoft,
                  },
                ]}
              >
                <Text style={[styles.summaryText, { color: theme.colors.warning }]}>
                  Cases {asset.maintenance_total}
                </Text>
              </View>
              <View
                style={[
                  styles.summaryPill,
                  {
                    backgroundColor: theme.colors.primarySoft,
                  },
                ]}
              >
                <Text style={[styles.summaryText, { color: theme.colors.primary }]}>
                  Open {asset.maintenance_open}
                </Text>
              </View>
            </View>
            {asset.last_completed_at ? (
              <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
                Last completed {formatDateLabel(asset.last_completed_at)}
              </Text>
            ) : null}
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
            <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Maintenance History</Text>
            {detailQuery.data?.maintenance_history.length ? (
              detailQuery.data.maintenance_history.map((record) => (
                <View
                  key={record.id}
                  style={[
                    styles.historyCard,
                    {
                      backgroundColor: theme.colors.surfaceMuted,
                    },
                  ]}
                >
                  <View style={styles.historyHeader}>
                    <View style={styles.historyMeta}>
                      <Text style={[styles.historyTitle, { color: theme.colors.text }]}>{record.title}</Text>
                      <Text style={[styles.metaText, { color: theme.colors.primary }]}>
                        {record.priority.toUpperCase()}  |  {record.issue_type.replace('_', ' ').toUpperCase()}
                      </Text>
                    </View>
                    <StatusPill kind="maintenance" status={record.status} />
                  </View>
                  <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
                    Reported by {record.reported_by_name || 'Unknown'} {record.technician_name ? `| Assigned PIC: ${record.technician_name}` : ''}
                  </Text>
                  <Text style={[styles.metaText, { color: theme.colors.textMuted }]}>
                    Quantity affected {record.quantity_affected}  |  Created {formatDateLabel(record.created_at)}
                  </Text>
                </View>
              ))
            ) : (
              <EmptyState title="No maintenance history" message="This asset has not recorded any maintenance cases yet." />
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
            <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Delete Asset</Text>
            {asset.maintenance_total > 0 ? (
              <EmptyState
                title="Delete blocked"
                message="This asset has maintenance history. Keep the record for traceability."
              />
            ) : (
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
                  {deleteMutation.isPending ? 'Deleting...' : 'Delete Asset'}
                </Text>
              </Pressable>
            )}
          </View>
        </>
      ) : (
        <EmptyState
          title="New asset record"
          message="Create the asset here to keep inventory, maintenance history, and booking availability aligned."
        />
      )}

      <SelectionModal
        onClose={() => {
          setLabModalOpen(false);
        }}
        onSelect={(value) => {
          setLabId(value ? Number(value) : null);
        }}
        options={labs.map((labOption) => ({
          id: String(labOption.id),
          label: labOption.label,
        }))}
        selectedId={labId ? String(labId) : null}
        title="Select Laboratory"
        visible={labModalOpen}
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
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
  },
  heroMeta: {
    flex: 1,
    gap: 4,
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
  multilineInput: {
    minHeight: 110,
    paddingTop: 12,
  },
  imageGroup: {
    gap: 10,
  },
  previewImage: {
    borderRadius: 16,
    height: 180,
    width: '100%',
  },
  metaText: {
    fontSize: 13,
    lineHeight: 18,
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
  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  summaryPill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  summaryText: {
    fontSize: 12,
    fontWeight: '800',
  },
  reasonGroup: {
    gap: 6,
  },
  reasonHeading: {
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.2,
    textTransform: 'uppercase',
  },
  historyCard: {
    borderRadius: 14,
    gap: 8,
    padding: 14,
  },
  historyHeader: {
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
  },
  historyMeta: {
    flex: 1,
    gap: 4,
  },
  historyTitle: {
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
