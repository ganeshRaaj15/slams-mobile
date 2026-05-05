import { startTransition, useDeferredValue, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';

import { listLabsRequest } from '../api/endpoints';
import { EmptyState } from '../components/empty-state';
import { ErrorState } from '../components/error-state';
import { LoadingState } from '../components/loading-state';
import { Screen } from '../components/screen';
import { TextField } from '../components/text-field';
import { useAppTheme } from '../theme/use-app-theme';

export function LabsScreen() {
  const theme = useAppTheme();
  const navigation = useNavigation<any>();
  const [search, setSearch] = useState('');
  const deferredSearch = useDeferredValue(search.trim().toLowerCase());

  const labsQuery = useQuery({
    queryKey: ['labs'],
    queryFn: listLabsRequest,
  });

  if (labsQuery.isLoading) {
    return (
      <Screen scroll={false}>
        <LoadingState label="Loading laboratories..." />
      </Screen>
    );
  }

  if (labsQuery.isError || !labsQuery.data) {
    return (
      <Screen>
        <ErrorState
          message="Laboratories could not be loaded."
          onRetry={() => {
            void labsQuery.refetch();
          }}
        />
      </Screen>
    );
  }

  const filteredLabs = labsQuery.data.labs.filter((lab) => {
    if (!deferredSearch) {
      return true;
    }

    const haystack = `${lab.name} ${lab.room} ${lab.description}`.toLowerCase();
    return haystack.includes(deferredSearch);
  });

  return (
    <Screen>
      <TextField
        autoCapitalize="none"
        autoCorrect={false}
        label="Find laboratories"
        onChangeText={(text) => {
          startTransition(() => {
            setSearch(text);
          });
        }}
        placeholder="Search by name or room"
        value={search}
      />

      {filteredLabs.length === 0 ? (
        <EmptyState
          title="No laboratories matched"
          message="Try a different search term."
        />
      ) : (
        filteredLabs.map((lab) => (
          <Pressable
            key={lab.id}
            onPress={() => navigation.navigate('LabDetail', { labId: lab.id })}
            style={[
              styles.card,
              {
                backgroundColor: theme.colors.surface,
                borderColor: theme.colors.border,
              },
            ]}
          >
            <View style={styles.headerRow}>
              <View style={styles.titleWrap}>
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
              </View>
              <View
                style={[
                  styles.capacityBadge,
                  {
                    backgroundColor: theme.colors.primarySoft,
                  },
                ]}
              >
                <Text
                  style={[
                    styles.capacityText,
                    {
                      color: theme.colors.primary,
                    },
                  ]}
                >
                  Cap {lab.capacity || '-'}
                </Text>
              </View>
            </View>

            {lab.description ? (
              <Text
                numberOfLines={3}
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
                styles.pic,
                {
                  color: theme.colors.text,
                },
              ]}
            >
              PIC: {lab.pic_name || 'Not assigned'}
            </Text>
          </Pressable>
        ))
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 18,
    borderWidth: 1,
    gap: 10,
    padding: 16,
  },
  headerRow: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  titleWrap: {
    flex: 1,
    gap: 4,
    paddingRight: 12,
  },
  title: {
    fontSize: 18,
    fontWeight: '800',
  },
  room: {
    fontSize: 13,
    fontWeight: '700',
  },
  description: {
    fontSize: 14,
    lineHeight: 20,
  },
  pic: {
    fontSize: 13,
    fontWeight: '600',
  },
  capacityBadge: {
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  capacityText: {
    fontSize: 12,
    fontWeight: '800',
  },
});
