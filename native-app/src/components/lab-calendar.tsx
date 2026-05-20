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

function buildWeeks(year: number, month: number): (number | null)[][] {
  const firstDayOfWeek = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const weeks: (number | null)[][] = [];
  let week: (number | null)[] = Array(firstDayOfWeek).fill(null);

  for (let d = 1; d <= daysInMonth; d++) {
    week.push(d);
    if (week.length === 7) {
      weeks.push(week);
      week = [];
    }
  }

  if (week.length > 0) {
    while (week.length < 7) week.push(null);
    weeks.push(week);
  }

  return weeks;
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
    queryFn: () => listDaySlotsRequest(labId, selectedDate!, { service_id: 0, assets: '' }),
    enabled: !!selectedDate,
    staleTime: 60 * 1000,
  });

  const unavailableSet = new Set<string>(calendarQuery.data?.unavailableDates ?? []);
  const weeks = buildWeeks(year, month);

  function prevMonth() {
    if (month === 0) { setYear((y) => y - 1); setMonth(11); }
    else { setMonth((m) => m - 1); }
    setSelectedDate(null);
  }

  function nextMonth() {
    if (month === 11) { setYear((y) => y + 1); setMonth(0); }
    else { setMonth((m) => m + 1); }
    setSelectedDate(null);
  }

  function handleDayPress(ds: string, isPast: boolean, isUnavailable: boolean) {
    if (isPast || isUnavailable) return;
    setSelectedDate((prev) => (prev === ds ? null : ds));
  }

  return (
    <View style={[styles.card, { backgroundColor: theme.colors.surface, borderColor: theme.colors.border }]}>
      {/* Nav */}
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

      {/* Day header row */}
      <View style={styles.weekRow}>
        {DAY_LABELS.map((label) => (
          <View key={label} style={styles.dayCell}>
            <Text style={[styles.headerText, { color: theme.colors.textMuted }]}>{label}</Text>
          </View>
        ))}
      </View>

      {/* Calendar body */}
      {calendarQuery.isLoading ? (
        <ActivityIndicator color={theme.colors.primary} style={styles.loader} />
      ) : (
        weeks.map((week, wi) => (
          <View key={wi} style={styles.weekRow}>
            {week.map((d, di) => {
              if (d === null) {
                return <View key={di} style={styles.dayCell} />;
              }

              const ds = dateStr(year, month, d);
              const cellDate = new Date(year, month, d);
              cellDate.setHours(0, 0, 0, 0);
              const isPast = cellDate < today;
              const isToday = cellDate.getTime() === today.getTime();
              const isUnavailable = !isPast && unavailableSet.has(ds);
              const isSelected = selectedDate === ds;

              let bg = 'transparent';
              let textColor: string = isPast ? theme.colors.textMuted : theme.colors.text;
              let borderColor = 'transparent';
              let borderWidth = 0;

              if (isSelected) {
                bg = theme.colors.primary;
                textColor = '#ffffff';
              } else if (isToday) {
                bg = theme.colors.primarySoft;
                textColor = theme.colors.primary;
                borderColor = theme.colors.primary;
                borderWidth = 1;
              } else if (isUnavailable) {
                bg = theme.colors.dangerSoft ?? '#ffe4e4';
                textColor = theme.colors.danger;
              }

              return (
                <Pressable
                  key={ds}
                  disabled={isPast || isUnavailable}
                  onPress={() => handleDayPress(ds, isPast, isUnavailable)}
                  style={styles.dayCell}
                >
                  <View
                    style={[
                      styles.dayInner,
                      {
                        backgroundColor: bg,
                        borderColor,
                        borderWidth,
                        borderRadius: 8,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.dayText,
                        {
                          color: textColor,
                          fontWeight: isToday || isSelected ? '800' : '500',
                        },
                      ]}
                    >
                      {d}
                    </Text>
                  </View>
                </Pressable>
              );
            })}
          </View>
        ))
      )}

      {/* Legend */}
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

      {/* Day slot panel */}
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
                    { backgroundColor: slot.can_book ? theme.colors.successSoft : theme.colors.dangerSoft },
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

const DAY_CELL_HEIGHT = 44;
const DAY_INNER_SIZE = 36;

const styles = StyleSheet.create({
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 4,
    padding: 16,
  },
  navRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
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
  weekRow: {
    flexDirection: 'row',
  },
  dayCell: {
    alignItems: 'center',
    flex: 1,
    height: DAY_CELL_HEIGHT,
    justifyContent: 'center',
  },
  dayInner: {
    alignItems: 'center',
    height: DAY_INNER_SIZE,
    justifyContent: 'center',
    width: DAY_INNER_SIZE,
  },
  headerText: {
    fontSize: 11,
    fontWeight: '700',
    includeFontPadding: false,
    textAlign: 'center',
  },
  dayText: {
    fontSize: 13,
    includeFontPadding: false,
    textAlign: 'center',
  },
  legend: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 14,
    marginTop: 8,
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
    marginTop: 8,
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
