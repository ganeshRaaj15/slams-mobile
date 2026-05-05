import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import { RouteProp, useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, Switch, Text, View } from 'react-native';

import {
  createAdminLabRequest,
  deleteAdminLabRequest,
  getAdminLabRequest,
  updateAdminLabRequest,
} from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { TextField } from '../components/text-field';
import type { RootStackParamList } from '../navigation/types';
import { useAppTheme } from '../theme/use-app-theme';
import { readErrorMessage } from '../utils/error-message';

type PickedFile = {
  uri: string;
  name: string;
  mimeType: string;
};

export function AdminLabEditorScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const route = useRoute<RouteProp<RootStackParamList, 'AdminLabEditor'>>();
  const queryClient = useQueryClient();
  const labId = route.params?.labId ?? null;
  const isEditMode = labId !== null;

  const [name, setName] = useState('');
  const [room, setRoom] = useState('');
  const [description, setDescription] = useState('');
  const [capacity, setCapacity] = useState('');
  const [availabilityNote, setAvailabilityNote] = useState('');
  const [safetyNote, setSafetyNote] = useState('');
  const [picName, setPicName] = useState('');
  const [picEmail, setPicEmail] = useState('');
  const [picPhone, setPicPhone] = useState('');
  const [image, setImage] = useState<PickedFile | null>(null);
  const [picImage, setPicImage] = useState<PickedFile | null>(null);
  const [removeImage, setRemoveImage] = useState(false);
  const [removePicImage, setRemovePicImage] = useState(false);
  const [localMessage, setLocalMessage] = useState<string | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);
  const [initialized, setInitialized] = useState(false);

  const detailQuery = useQuery({
    queryKey: ['admin-lab', labId],
    queryFn: () => getAdminLabRequest(labId as number),
    enabled: isEditMode,
  });

  useEffect(() => {
    if (!detailQuery.data?.lab || initialized) {
      return;
    }

    const { lab } = detailQuery.data;
    setName(lab.name ?? '');
    setRoom(lab.room ?? '');
    setDescription(lab.description ?? '');
    setCapacity(lab.capacity ? String(lab.capacity) : '');
    setAvailabilityNote(lab.availability_note ?? '');
    setSafetyNote(lab.safety_note ?? '');
    setPicName(lab.pic_name ?? '');
    setPicEmail(lab.pic_email ?? '');
    setPicPhone(lab.pic_phone ?? '');
    setInitialized(true);
  }, [detailQuery.data, initialized]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: name.trim(),
        room: room.trim(),
        description: description.trim(),
        capacity: capacity.trim(),
        availability_note: availabilityNote.trim(),
        safety_note: safetyNote.trim(),
        pic_name: picName.trim(),
        pic_email: picEmail.trim(),
        pic_phone: picPhone.trim(),
        remove_image: removeImage,
        remove_pic_image: removePicImage,
        image,
        pic_image: picImage,
      };

      if (isEditMode) {
        return updateAdminLabRequest(labId as number, payload);
      }

      return createAdminLabRequest(payload);
    },
    onSuccess: async (data) => {
      setLocalError(null);
      setLocalMessage(
        typeof data.warning === 'string' && data.warning.trim() !== ''
          ? data.warning
          : isEditMode
            ? 'Laboratory updated successfully.'
            : 'Laboratory created successfully.',
      );
      setImage(null);
      setPicImage(null);
      setRemoveImage(false);
      setRemovePicImage(false);
      await queryClient.invalidateQueries({ queryKey: ['admin-labs'] });
      if (isEditMode) {
        await queryClient.invalidateQueries({ queryKey: ['admin-lab', labId] });
        return;
      }
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, isEditMode ? 'Laboratory update failed.' : 'Laboratory save failed.'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteAdminLabRequest(labId as number),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-labs'] });
      navigation.goBack();
    },
    onError: (error: unknown) => {
      setLocalError(readErrorMessage(error, 'Laboratory deletion failed.'));
    },
  });

  const lab = detailQuery.data?.lab;
  const labImageUri = useMemo(() => {
    if (removeImage) {
      return '';
    }
    if (image?.uri) {
      return image.uri;
    }
    return lab?.image_url ?? '';
  }, [image?.uri, lab?.image_url, removeImage]);
  const picImageUri = useMemo(() => {
    if (removePicImage) {
      return '';
    }
    if (picImage?.uri) {
      return picImage.uri;
    }
    return lab?.pic_image_url ?? '';
  }, [lab?.pic_image_url, picImage?.uri, removePicImage]);

  if (isEditMode && detailQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading laboratory details..." />
      </Screen>
    );
  }

  if (isEditMode && (detailQuery.isError || !detailQuery.data)) {
    return (
      <Screen>
        <ErrorState
          message="The laboratory editor could not be loaded."
          onRetry={() => {
            void detailQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  async function pickImage(kind: 'lab' | 'pic') {
    const result = await DocumentPicker.getDocumentAsync({
      copyToCacheDirectory: true,
      multiple: false,
      type: ['image/*'],
    });

    if (result.canceled || !result.assets?.[0]) {
      return;
    }

    const asset = result.assets[0];
    const selectedFile = {
      uri: asset.uri,
      name: asset.name || `${kind}-image.jpg`,
      mimeType: asset.mimeType || 'image/jpeg',
    };

    if (kind === 'lab') {
      setImage(selectedFile);
      setRemoveImage(false);
      return;
    }

    setPicImage(selectedFile);
    setRemovePicImage(false);
  }

  function confirmDelete() {
    Alert.alert(
      'Delete laboratory',
      'This removes the laboratory only if no assets are still assigned to it.',
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
          {isEditMode ? 'Edit Laboratory' : 'Create Laboratory'}
        </Text>
        <TextField label="Laboratory name" onChangeText={setName} value={name} />
        <TextField label="Room" onChangeText={setRoom} value={room} />
        <TextField
          keyboardType="number-pad"
          label="Capacity"
          onChangeText={setCapacity}
          placeholder="20"
          value={capacity}
        />
        <TextField
          label="Description"
          multiline
          numberOfLines={4}
          onChangeText={setDescription}
          style={styles.multilineInput}
          textAlignVertical="top"
          value={description}
        />
        <TextField
          label="Availability note"
          onChangeText={setAvailabilityNote}
          placeholder="Open weekdays, 8:00 - 17:00"
          value={availabilityNote}
        />
        <TextField
          label="Safety note"
          multiline
          numberOfLines={4}
          onChangeText={setSafetyNote}
          style={styles.multilineInput}
          textAlignVertical="top"
          value={safetyNote}
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>PIC Assignment</Text>
        <TextField label="PIC name" onChangeText={setPicName} value={picName} />
        <TextField
          autoCapitalize="none"
          keyboardType="email-address"
          label="PIC email"
          onChangeText={setPicEmail}
          value={picEmail}
        />
        <TextField keyboardType="phone-pad" label="PIC phone" onChangeText={setPicPhone} value={picPhone} />

        {lab?.pic_email && !lab.pic_account_linked ? (
          <Text style={[styles.warningText, { color: theme.colors.warning }]}>
            This PIC email is not linked to a user account yet.
          </Text>
        ) : null}
        {lab?.pic_account_linked && !lab.pic_account_has_role ? (
          <Text style={[styles.warningText, { color: theme.colors.warning }]}>
            The linked user exists but does not currently have the PIC role.
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
        <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Images</Text>

        <View style={styles.imageGroup}>
          <Text style={[styles.imageLabel, { color: theme.colors.text }]}>Laboratory image</Text>
          {labImageUri ? <Image source={{ uri: labImageUri }} style={styles.previewImage} /> : null}
          <Pressable
            onPress={() => {
              void pickImage('lab');
            }}
            style={[
              styles.secondaryButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>
              {labImageUri ? 'Replace Laboratory Image' : 'Attach Laboratory Image'}
            </Text>
          </Pressable>
          {isEditMode && (lab?.image_url || image) ? (
            <View style={styles.switchRow}>
              <Text style={[styles.switchLabel, { color: theme.colors.text }]}>Remove current image</Text>
              <Switch
                onValueChange={(value) => {
                  setRemoveImage(value);
                  if (value) {
                    setImage(null);
                  }
                }}
                value={removeImage}
              />
            </View>
          ) : null}
        </View>

        <View style={styles.imageGroup}>
          <Text style={[styles.imageLabel, { color: theme.colors.text }]}>PIC image</Text>
          {picImageUri ? <Image source={{ uri: picImageUri }} style={styles.previewImage} /> : null}
          <Pressable
            onPress={() => {
              void pickImage('pic');
            }}
            style={[
              styles.secondaryButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.secondaryButtonText, { color: theme.colors.text }]}>
              {picImageUri ? 'Replace PIC Image' : 'Attach PIC Image'}
            </Text>
          </Pressable>
          {isEditMode && (lab?.pic_image_url || picImage) ? (
            <View style={styles.switchRow}>
              <Text style={[styles.switchLabel, { color: theme.colors.text }]}>Remove current PIC image</Text>
              <Switch
                onValueChange={(value) => {
                  setRemovePicImage(value);
                  if (value) {
                    setPicImage(null);
                  }
                }}
                value={removePicImage}
              />
            </View>
          ) : null}
        </View>

        {lab ? (
          <View style={styles.summaryRow}>
            <View
              style={[
                styles.summaryPill,
                {
                  backgroundColor: theme.colors.primarySoft,
                },
              ]}
            >
              <Text style={[styles.summaryText, { color: theme.colors.primary }]}>Assets {lab.asset_total}</Text>
            </View>
            <View
              style={[
                styles.summaryPill,
                {
                  backgroundColor: theme.colors.warningSoft,
                },
              ]}
            >
              <Text style={[styles.summaryText, { color: theme.colors.warning }]}>
                Maintenance {lab.assets_in_maintenance}
              </Text>
            </View>
            <View
              style={[
                styles.summaryPill,
                {
                  backgroundColor: theme.colors.dangerSoft,
                },
              ]}
            >
              <Text style={[styles.summaryText, { color: theme.colors.danger }]}>Faulty {lab.faulty_assets}</Text>
            </View>
          </View>
        ) : null}
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
          {saveMutation.isPending ? 'Saving...' : isEditMode ? 'Save Laboratory' : 'Create Laboratory'}
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
          <Text style={[styles.sectionTitle, { color: theme.colors.text }]}>Delete Laboratory</Text>
          {lab?.asset_total ? (
            <EmptyState
              title="Delete blocked"
              message="This laboratory still has assigned assets. Reassign or delete those assets before removing the lab."
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
                {deleteMutation.isPending ? 'Deleting...' : 'Delete Laboratory'}
              </Text>
            </Pressable>
          )}
        </View>
      ) : null}
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
  multilineInput: {
    minHeight: 110,
    paddingTop: 12,
  },
  imageGroup: {
    gap: 10,
  },
  imageLabel: {
    fontSize: 14,
    fontWeight: '700',
  },
  previewImage: {
    borderRadius: 16,
    height: 180,
    width: '100%',
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
  switchRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  switchLabel: {
    fontSize: 13,
    fontWeight: '700',
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
  feedbackCard: {
    borderRadius: 16,
    padding: 14,
  },
  feedbackText: {
    fontSize: 13,
    fontWeight: '700',
    lineHeight: 18,
  },
  warningText: {
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
