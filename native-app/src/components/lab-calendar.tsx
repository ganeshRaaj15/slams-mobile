import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { getLabCalendarRequest, listDaySlotsRequest } from '../api/endpoints';
import { useAppTheme } from '../theme/use-app-theme';

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function pad(n: number) {
  return String(n).padStart(2, '0');
}

function dateStr(year: number, month: number, day: number) {
  return `${year}-${pad(month + 1)}-${pad(day)}`;
}

function monthLabel(year: number, month: number) {
  return new Date(year, month, 1).toLocaleDateString('en-MY', {
    month: 'long',
    year: 'numeric',
  });
}

type Props = {
  labId: number;
};

export function LabCalendar({ labId }: Props) {
  const theme = useAppTheme();
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const [year, setYear] = useState(today.getFullYear());
  const [month, setMonth] = useState(today.getMonth());
  const [selectedDate, setSelectedDate] = useState<string | null>(null);

  const calendarQuery = useQuery({
    queryKey: ['lab-calendar', labId, year, month],
    queryFn: () => getLabCalendarRequest(labId),
    staleTime: 2 * 60 * 1000,
  });

  const dayQuery = useQuery({
    queryKey: ['lab-day-slots', labId, selectedDate],
    queryFn: () =>
      listDaySlotsRequest(labId, selectedDate!, { service_id: 0, assets: '' }),
    enabled: !!selectedDate,
    staleTime: 60 * 1000,
  });

  const unavailableSet = new Set<string>(calendarQuery.data?.unavailableDates ?? []);

  const firstDayOfWeek = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  function prevMonth() {
    if (month === 0) {
      setYear((y) => y - 1);
      setMonth(11);
    } else {
      setMonth((m) => m - 1);
    }
    setSelectedDate(null);
  }

  function nextMonth() {
    if (month === 11) {
      setYear((y) => y + 1);
      setMonth(0);
    } else {
      setMonth((m) => m + 1);
    }
    setSelectedDate(null);
  }

  function handleDayPress(ds: string, isPast: boolean, isUnavailable: boolean) {
    if (isPast || isUnavailable) return;
    setSelectedDate((prev) => (prev === ds ? null : ds));
  }

  const cells: React.ReactNode[] = DAY_LABELS.map((label) => (
    <View key={label} style={styles.headerCell}>
      <Text style={[styles.headerText, { color: theme.colors.textMuted }]}>{label}</Text>
    </View>
  ));

  for (let i = 0; i < firstDayOfWeek; i++) {
    cells.push(<View key={`empty-${i}`} style={styles.cell} />);
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const ds = dateStr(year, month, d);
    const cellDate = new Date(year, month, d);
    cellDate.setHours(0, 0, 0, 0);
    const isPast = cellDate < today;
    const isToday = cellDate.getTime() === today.getTime();
    const isUnavailable = !isPast && unavailableSet.has(ds);
    const isSelected = selectedDate === ds;
    const isAvailable = !isPast && !isUnavailable;

    let bg = 'transparent';
    let textColor = theme.colors.textMuted;
    let borderColor = 'transparent';

    if (isToday) {
      bg = theme.colors.primarySoft;
      textColor = theme.colors.primary;
      borderColor = theme.colors.primary;
    } else if (isSelected) {
      bg = theme.colors.primary;
      textColor = '#ffffff';
    } else if (isUnavailable) {
      bg = theme.colors.dangerSoft ?? '#ffe4e4';
      textColor = theme.colors.danger;
    } else if (isAvailable) {
      textColor = theme.colors.text;
    }

    cells.push(
      <Pressable
        key={ds}
        disabled={isPast || isUnavailable}
        onPress={() => handleDayPress(ds, isPast, isUnavailable)}
        style={[
          styles.cell,
          {
            backgroundColor: bg,
            borderColor,
            borderWidth: isToday && !isSelected ? 1 : 0,
            borderRadius: 8,
          },
        ]}
      >
        <Text style={[styles.dayText, { color: textColor, fontWeight: isToday ? '800' : '500' }]}>
          {d}
        </Text>
      </Pressable>,
    );
  }

  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      <View style={styles.navRow}>
        <Pressable hitSlop={8} onPress={prevMonth} style={styles.navBtn}>
          <Text style={[styles.navBtnText, { color: theme.colors.primary }]}>‹ Prev</Text>
        </Pressable>
        <Text style={[styles.monthLabel, { color: theme.colors.text }]}>
          {monthLabel(year, month)}
        </Text>
        <Pressable hitSlop={8} onPress={nextMonth} style={styles.navBtn}>
          <Text style={[styles.navBtnText, { color: theme.colors.primary }]}>Next ›</Text>
        </Pressable>
      </View>

      {calendarQuery.isLoading ? (
        <ActivityIndicator color={theme.colors.primary} style={styles.loader} />
      ) : (
        <View style={styles.grid}>{cells}</View>
      )}

      <View style={styles.legend}>
        <View style={styles.legendItem}>
          <View style={[styles.legendDot, { backgroundColor: theme.colors.primarySoft, borderColor: theme.colors.primary, borderWidth: 1 }]} />
          <Text style={[styles.legendText, { color: theme.colors.textMuted }]}>Today</Text>
        </View>
        <View style={styles.legendItem}>
          <View style={[styles.legendDot, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border, borderWidth: 1 }]} />
          <Text style={[styles.legendText, { color: theme.colors.textMuted }]}>Open</Text>
        </View>
        <View style={styles.legendItem}>
          <View style={[styles.legendDot, { backgroundColor: theme.colors.dangerSoft ?? '#ffe4e4' }]} />
          <Text style={[styles.legendText, { color: theme.colors.textMuted }]}>Full</Text>
        </View>
      </View>

      {selectedDate ? (
        <View style={[styles.dayPanel, { borderTopColor: theme.colors.border }]}>
          <Text style={[styles.dayPanelTitle, { color: theme.colors.text }]}>
            {new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-MY', {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
              year: 'numeric',
            })}
          </Text>
          {dayQuery.isLoading ? (
            <ActivityIndicator color={theme.colors.primary} style={styles.loader} />
          ) : dayQuery.data?.slots && dayQuery.data.slots.length > 0 ? (
            <View style={styles.slotList}>
              {dayQuery.data.slots.map((slot) => (
                <View
                  key={`${slot.start}-${slot.end}`}
                  style={[
                    styles.slotRow,
                    {
                      backgroundColor: slot.can_book
                        ? theme.colors.successSoft
                        : theme.colors.dangerSoft,
                    },
                  ]}
                >
                  <Text style={[styles.slotTime, { color: theme.colors.text }]}>
                    {slot.label || `${slot.start} – ${slot.end}`}
                  </Text>
                  <Text
                    style={[
                      styles.slotStatus,
                      { color: slot.can_book ? theme.colors.success : theme.colors.danger },
                    ]}
                  >
                    {slot.can_book ? 'Available' : (slot.reason ?? 'Unavailable')}
                  </Text>
                </View>
              ))}
            </View>
          ) : (
            <Text style={[styles.noSlots, { color: theme.colors.textMuted }]}>
              No time slots configured for this day.
            </Text>
          )}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 12,
    padding: 16,
  },
  navRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  navBtn: {
    paddingHorizontal: 4,
    paddingVertical: 6,
  },
  navBtnText: {
    fontSize: 14,
    fontWeight: '700',
  },
  monthLabel: {
    fontSize: 15,
    fontWeight: '800',
  },
  loader: {
    marginVertical: 24,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  headerCell: {
    alignItems: 'center',
    paddingVertical: 4,
    width: `${100 / 7}%`,
  },
  headerText: {
    fontSize: 11,
    fontWeight: '700',
  },
  cell: {
    alignItems: 'center',
    aspectRatio: 1,
    justifyContent: 'center',
    width: `${100 / 7}%`,
  },
  dayText: {
    fontSize: 13,
  },
  legend: {
    flexDirection: 'row',
    gap: 14,
    flexWrap: 'wrap',
  },
  legendItem: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 5,
  },
  legendDot: {
    borderRadius: 5,
    height: 10,
    width: 10,
  },
  legendText: {
    fontSize: 11,
  },
  dayPanel: {
    borderTopWidth: StyleSheet.hairlineWidth,
    gap: 10,
    paddingTop: 12,
  },
  dayPanelTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  slotList: {
    gap: 6,
  },
  slotRow: {
    borderRadius: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  slotTime: {
    fontSize: 13,
    fontWeight: '700',
  },
  slotStatus: {
    fontSize: 12,
    fontWeight: '600',
  },
  noSlots: {
    fontSize: 13,
    lineHeight: 18,
  },
});
