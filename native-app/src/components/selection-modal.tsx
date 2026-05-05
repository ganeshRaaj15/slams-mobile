import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { useAppTheme } from '../theme/use-app-theme';

type SelectionOption = {
  id: string;
  label: string;
  subtitle?: string;
};

export function SelectionModal({
  visible,
  title,
  options,
  selectedId,
  onSelect,
  onClose,
}: {
  visible: boolean;
  title: string;
  options: SelectionOption[];
  selectedId?: string | null;
  onSelect: (id: string) => void;
  onClose: () => void;
}) {
  const theme = useAppTheme();

  return (
    <Modal animationType="slide" onRequestClose={onClose} transparent visible={visible}>
      <View style={styles.overlay}>
        <View
          style={[
            styles.sheet,
            {
              backgroundColor: theme.colors.surface,
              borderColor: theme.colors.border,
            },
          ]}
        >
          <View style={styles.header}>
            <Text style={[styles.title, { color: theme.colors.text }]}>{title}</Text>
            <Pressable onPress={onClose}>
              <Text style={[styles.closeText, { color: theme.colors.primary }]}>Close</Text>
            </Pressable>
          </View>
          <ScrollView contentContainerStyle={styles.content}>
            {options.map((option) => {
              const selected = option.id === selectedId;
              return (
                <Pressable
                  key={option.id}
                  onPress={() => {
                    onSelect(option.id);
                    onClose();
                  }}
                  style={[
                    styles.option,
                    {
                      backgroundColor: selected ? theme.colors.primarySoft : theme.colors.surfaceMuted,
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.optionLabel,
                      {
                        color: selected ? theme.colors.primary : theme.colors.text,
                      },
                    ]}
                  >
                    {option.label}
                  </Text>
                  {option.subtitle ? (
                    <Text style={[styles.optionSubtitle, { color: theme.colors.textMuted }]}>
                      {option.subtitle}
                    </Text>
                  ) : null}
                </Pressable>
              );
            })}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    backgroundColor: 'rgba(10, 20, 32, 0.45)',
    flex: 1,
    justifyContent: 'flex-end',
  },
  sheet: {
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    borderWidth: 1,
    maxHeight: '75%',
    paddingBottom: 20,
    paddingHorizontal: 18,
    paddingTop: 18,
  },
  header: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 14,
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
  },
  closeText: {
    fontSize: 14,
    fontWeight: '700',
  },
  content: {
    gap: 10,
    paddingBottom: 8,
  },
  option: {
    borderRadius: 14,
    gap: 4,
    padding: 14,
  },
  optionLabel: {
    fontSize: 15,
    fontWeight: '700',
  },
  optionSubtitle: {
    fontSize: 13,
    lineHeight: 18,
  },
});
