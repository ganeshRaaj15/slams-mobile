import DateTimePicker, { type DateTimePickerEvent } from '@react-native-community/datetimepicker';
import { useMemo, useState } from 'react';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';

import { useAppTheme } from '../theme/use-app-theme';

type PickerMode = 'date' | 'time';

type PickerFieldProps = {
  label: string;
  mode: PickerMode;
  value: string;
  placeholder: string;
  onChangeValue: (value: string) => void;
  hint?: string;
  allowClear?: boolean;
  minimumDate?: Date;
};

function twoDigits(value: number) {
  return value.toString().padStart(2, '0');
}

function formatValue(mode: PickerMode, date: Date) {
  if (mode === 'date') {
    return `${date.getFullYear()}-${twoDigits(date.getMonth() + 1)}-${twoDigits(date.getDate())}`;
  }

  return `${twoDigits(date.getHours())}:${twoDigits(date.getMinutes())}`;
}

function resolvePickerDate(mode: PickerMode, value: string) {
  const trimmed = value.trim();
  const now = new Date();

  if (mode === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
    return new Date(`${trimmed}T12:00:00`);
  }

  if (mode === 'time' && /^\d{2}:\d{2}$/.test(trimmed)) {
    const [hours, minutes] = trimmed.split(':').map(Number);
    const resolved = new Date();
    resolved.setHours(hours || 0, minutes || 0, 0, 0);
    return resolved;
  }

  if (mode === 'date') {
    now.setHours(12, 0, 0, 0);
    return now;
  }

  now.setSeconds(0, 0);
  return now;
}

export function PickerField({
  label,
  mode,
  value,
  placeholder,
  onChangeValue,
  hint,
  allowClear = false,
  minimumDate,
}: PickerFieldProps) {
  const theme = useAppTheme();
  const [visible, setVisible] = useState(false);
  const pickerValue = useMemo(() => resolvePickerDate(mode, value), [mode, value]);

  function handleChange(event: DateTimePickerEvent, nextValue?: Date) {
    if (Platform.OS === 'android') {
      setVisible(false);
    }

    if (event.type === 'dismissed' || !nextValue) {
      return;
    }

    onChangeValue(formatValue(mode, nextValue));
  }

  return (
    <View style={styles.wrap}>
      <Text
        style={[
          styles.label,
          {
            color: theme.colors.text,
          },
        ]}
      >
        {label}
      </Text>
      {hint ? (
        <Text
          style={[
            styles.hint,
            {
              color: theme.colors.textMuted,
            },
          ]}
        >
          {hint}
        </Text>
      ) : null}

      <View style={styles.fieldRow}>
        <Pressable
          onPress={() => setVisible(true)}
          style={[
            styles.input,
            {
              backgroundColor: theme.colors.surfaceMuted,
              borderColor: theme.colors.borderStrong,
            },
          ]}
        >
          <Text
            style={[
              styles.valueText,
              {
                color: value ? theme.colors.text : theme.colors.textMuted,
              },
            ]}
          >
            {value || placeholder}
          </Text>
        </Pressable>

        {allowClear && value ? (
          <Pressable
            onPress={() => onChangeValue('')}
            style={[
              styles.clearButton,
              {
                backgroundColor: theme.colors.surfaceMuted,
              },
            ]}
          >
            <Text style={[styles.clearButtonText, { color: theme.colors.textMuted }]}>Clear</Text>
          </Pressable>
        ) : null}
      </View>

      {visible ? (
        <View
          style={[
            styles.pickerWrap,
            {
              backgroundColor: theme.colors.surfaceModal,
              borderColor: theme.colors.borderStrong,
            },
          ]}
        >
          <DateTimePicker
            display={Platform.OS === 'ios' ? 'spinner' : 'default'}
            minimumDate={minimumDate}
            mode={mode}
            onChange={handleChange}
            value={pickerValue}
          />
          {Platform.OS === 'ios' ? (
            <View style={styles.pickerActions}>
              {allowClear ? (
                <Pressable
                  onPress={() => {
                    onChangeValue('');
                    setVisible(false);
                  }}
                  style={[
                    styles.pickerActionButton,
                    {
                      backgroundColor: theme.colors.surfaceMuted,
                    },
                  ]}
                >
                  <Text style={[styles.pickerActionText, { color: theme.colors.textMuted }]}>Clear</Text>
                </Pressable>
              ) : null}
              <Pressable
                onPress={() => setVisible(false)}
                style={[
                  styles.pickerActionButton,
                  {
                    backgroundColor: theme.colors.primarySoft,
                  },
                ]}
              >
                <Text style={[styles.pickerActionText, { color: theme.colors.primary }]}>Done</Text>
              </Pressable>
            </View>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    gap: 6,
  },
  label: {
    fontSize: 14,
    fontWeight: '700',
  },
  hint: {
    fontSize: 12,
    lineHeight: 18,
  },
  fieldRow: {
    flexDirection: 'row',
    gap: 8,
  },
  input: {
    borderRadius: 14,
    borderWidth: 1,
    flex: 1,
    justifyContent: 'center',
    minHeight: 50,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  valueText: {
    fontSize: 15,
  },
  clearButton: {
    alignItems: 'center',
    borderRadius: 12,
    justifyContent: 'center',
    minWidth: 68,
    paddingHorizontal: 12,
  },
  clearButtonText: {
    fontSize: 12,
    fontWeight: '700',
  },
  pickerWrap: {
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
    padding: 8,
  },
  pickerActions: {
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'flex-end',
    paddingHorizontal: 8,
    paddingTop: 8,
  },
  pickerActionButton: {
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  pickerActionText: {
    fontSize: 13,
    fontWeight: '800',
  },
});
