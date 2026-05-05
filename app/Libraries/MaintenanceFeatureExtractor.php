<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

class MaintenanceFeatureExtractor
{
    protected \CodeIgniter\Database\BaseConnection $db;
    protected DateTimeZone $timezone;
    protected array $plannedIssueTypes = ['preventive', 'inspection', 'calibration'];
    protected array $priorityWeights = [
        'low' => 0.25,
        'medium' => 0.5,
        'high' => 0.8,
        'critical' => 1.0,
    ];

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null, ?DateTimeZone $timezone = null)
    {
        $this->db = $db ?? Database::connect();
        $this->timezone = $timezone ?? new DateTimeZone('Asia/Kuala_Lumpur');
    }

    public function featureNames(): array
    {
        return [
            'total_quantity',
            'is_single_unit',
            'days_since_last_event',
            'days_since_last_planned',
            'days_since_last_corrective',
            'events_last_30d',
            'events_last_90d',
            'corrective_last_120d',
            'planned_last_180d',
            'high_priority_last_180d',
            'avg_gap_days',
            'avg_planned_gap_days',
            'planned_gap_delta',
            'mean_quantity_ratio_last_5',
            'corrective_ratio_365d',
        ];
    }

    public function buildTrainingSamples(int $horizonDays = 30, int $stepDays = 14, int $lookbackDays = 420): array
    {
        $assets = $this->assetRows();
        if ($assets === []) {
            return [];
        }

        $historyByAsset = $this->maintenanceHistoryByAsset(array_map(
            static fn(array $row): int => (int) $row['id'],
            $assets
        ));

        $today = new DateTimeImmutable('today', $this->timezone);
        $maxAnchor = $today->sub(new DateInterval('P' . max($horizonDays, 1) . 'D'));
        $globalStart = $today->sub(new DateInterval('P' . max($lookbackDays, 30) . 'D'));
        $samples = [];

        foreach ($assets as $asset) {
            $assetId = (int) $asset['id'];
            $history = $historyByAsset[$assetId] ?? [];
            if ($history === []) {
                continue;
            }

            $firstEventAt = $history[0]['event_at'];
            $anchor = $firstEventAt > $globalStart ? $firstEventAt : $globalStart;
            $anchor = $anchor->setTime(0, 0);

            while ($anchor <= $maxAnchor) {
                $features = $this->extractFeaturesFromHistory($asset, $history, $anchor);
                if (($features['events_last_90d'] ?? 0.0) > 0.0 || ($features['days_since_last_event'] ?? 999.0) < 365.0) {
                    $samples[] = [
                        'asset_id' => $assetId,
                        'anchor_date' => $anchor->format('Y-m-d'),
                        'features' => $features,
                        'label' => $this->hasFutureMaintenance($history, $anchor, $horizonDays) ? 1 : 0,
                    ];
                }

                $anchor = $anchor->add(new DateInterval('P' . max($stepDays, 7) . 'D'));
            }
        }

        usort($samples, static fn(array $a, array $b): int => strcmp($a['anchor_date'], $b['anchor_date']));

        return $samples;
    }

    public function extractCurrentAssetFeatures(int $assetId, ?DateTimeImmutable $anchor = null): ?array
    {
        $asset = $this->db->table('assets')
            ->select('id, lab_id, name, quantity, total_quantity, status')
            ->where('id', $assetId)
            ->get()
            ->getRowArray();

        if (! $asset) {
            return null;
        }

        $history = $this->maintenanceHistoryByAsset([$assetId])[$assetId] ?? [];
        $anchor = $anchor ?? new DateTimeImmutable('now', $this->timezone);

        return $this->extractFeaturesFromHistory($asset, $history, $anchor);
    }

    public function assetRows(): array
    {
        return $this->db->table('assets')
            ->select('id, lab_id, name, quantity, total_quantity, status')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    protected function maintenanceHistoryByAsset(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        $rows = $this->db->table('maintenance_records')
            ->select('asset_id, issue_type, priority, status, quantity_affected, created_at, updated_at, scheduled_for, started_at, completed_at')
            ->whereIn('asset_id', $assetIds)
            ->whereIn('status', ['scheduled', 'in_progress', 'testing', 'completed', 'cancelled'])
            ->orderBy('created_at', 'ASC')
            ->get()
            ->getResultArray();

        $history = [];

        foreach ($rows as $row) {
            $assetId = (int) ($row['asset_id'] ?? 0);
            $eventAt = $this->eventAt($row);
            if ($assetId <= 0 || ! $eventAt) {
                continue;
            }

            $history[$assetId][] = [
                'issue_type' => strtolower(trim((string) ($row['issue_type'] ?? 'other'))),
                'priority' => strtolower(trim((string) ($row['priority'] ?? 'medium'))),
                'status' => strtolower(trim((string) ($row['status'] ?? 'reported'))),
                'quantity_affected' => max((int) ($row['quantity_affected'] ?? 1), 1),
                'event_at' => $eventAt,
            ];
        }

        foreach ($history as &$events) {
            usort($events, static fn(array $a, array $b): int => $a['event_at'] <=> $b['event_at']);
        }

        return $history;
    }

    protected function eventAt(array $row): ?DateTimeImmutable
    {
        foreach (['completed_at', 'scheduled_for', 'started_at', 'updated_at', 'created_at'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            try {
                return new DateTimeImmutable($value, $this->timezone);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    protected function hasFutureMaintenance(array $history, DateTimeImmutable $anchor, int $horizonDays): bool
    {
        $cutoff = $anchor->add(new DateInterval('P' . max($horizonDays, 1) . 'D'));

        foreach ($history as $event) {
            $eventAt = $event['event_at'];
            if ($eventAt > $anchor && $eventAt <= $cutoff) {
                return true;
            }
        }

        return false;
    }

    protected function extractFeaturesFromHistory(array $asset, array $history, DateTimeImmutable $anchor): array
    {
        $totalQuantity = max((int) ($asset['total_quantity'] ?? $asset['quantity'] ?? 1), 1);
        $pastEvents = array_values(array_filter(
            $history,
            static fn(array $event): bool => $event['event_at'] <= $anchor
        ));

        $lastEvent = $this->lastMatchingEvent($pastEvents, static fn(array $event): bool => true);
        $lastPlanned = $this->lastMatchingEvent($pastEvents, fn(array $event): bool => $this->isPlanned($event['issue_type']));
        $lastCorrective = $this->lastMatchingEvent($pastEvents, static fn(array $event): bool => $event['issue_type'] === 'corrective');

        $eventsLast30 = $this->countInWindow($pastEvents, $anchor, 30);
        $eventsLast90 = $this->countInWindow($pastEvents, $anchor, 90);
        $correctiveLast120 = $this->countMatchingInWindow($pastEvents, $anchor, 120, static fn(array $event): bool => $event['issue_type'] === 'corrective');
        $plannedLast180 = $this->countMatchingInWindow($pastEvents, $anchor, 180, fn(array $event): bool => $this->isPlanned($event['issue_type']));
        $highPriorityLast180 = $this->countMatchingInWindow($pastEvents, $anchor, 180, fn(array $event): bool => $this->priorityWeight($event['priority']) >= 0.8);
        $eventsLast365 = $this->eventsInWindow($pastEvents, $anchor, 365);
        $correctiveLast365 = array_values(array_filter($eventsLast365, static fn(array $event): bool => $event['issue_type'] === 'corrective'));
        $plannedEvents = array_values(array_filter($pastEvents, fn(array $event): bool => $this->isPlanned($event['issue_type'])));

        $avgGap = $this->averageGapDays($pastEvents, 6, 180.0);
        $avgPlannedGap = $this->averageGapDays($plannedEvents, 4, 240.0);
        $daysSinceLastPlanned = $this->daysSince($anchor, $lastPlanned['event_at'] ?? null, 365.0);

        $recentFive = array_slice($pastEvents, -5);
        $meanQuantityRatio = 0.0;
        if ($recentFive !== []) {
            $sum = 0.0;
            foreach ($recentFive as $event) {
                $sum += min($event['quantity_affected'] / $totalQuantity, 1.0);
            }
            $meanQuantityRatio = $sum / count($recentFive);
        }

        return [
            'total_quantity' => (float) $totalQuantity,
            'is_single_unit' => $totalQuantity <= 1 ? 1.0 : 0.0,
            'days_since_last_event' => $this->daysSince($anchor, $lastEvent['event_at'] ?? null, 365.0),
            'days_since_last_planned' => $daysSinceLastPlanned,
            'days_since_last_corrective' => $this->daysSince($anchor, $lastCorrective['event_at'] ?? null, 365.0),
            'events_last_30d' => (float) $eventsLast30,
            'events_last_90d' => (float) $eventsLast90,
            'corrective_last_120d' => (float) $correctiveLast120,
            'planned_last_180d' => (float) $plannedLast180,
            'high_priority_last_180d' => (float) $highPriorityLast180,
            'avg_gap_days' => $avgGap,
            'avg_planned_gap_days' => $avgPlannedGap,
            'planned_gap_delta' => max($daysSinceLastPlanned - $avgPlannedGap, 0.0),
            'mean_quantity_ratio_last_5' => $meanQuantityRatio,
            'corrective_ratio_365d' => $eventsLast365 === [] ? 0.0 : count($correctiveLast365) / count($eventsLast365),
        ];
    }

    protected function isPlanned(string $issueType): bool
    {
        return in_array($issueType, $this->plannedIssueTypes, true);
    }

    protected function priorityWeight(string $priority): float
    {
        return $this->priorityWeights[$priority] ?? 0.5;
    }

    protected function lastMatchingEvent(array $events, callable $filter): ?array
    {
        for ($index = count($events) - 1; $index >= 0; $index--) {
            if ($filter($events[$index])) {
                return $events[$index];
            }
        }

        return null;
    }

    protected function daysSince(DateTimeImmutable $anchor, ?DateTimeImmutable $eventAt, float $defaultDays): float
    {
        if (! $eventAt) {
            return $defaultDays;
        }

        return max((float) $anchor->diff($eventAt)->format('%a'), 0.0);
    }

    protected function countInWindow(array $events, DateTimeImmutable $anchor, int $days): int
    {
        return count($this->eventsInWindow($events, $anchor, $days));
    }

    protected function countMatchingInWindow(array $events, DateTimeImmutable $anchor, int $days, callable $filter): int
    {
        return count(array_filter(
            $this->eventsInWindow($events, $anchor, $days),
            $filter
        ));
    }

    protected function eventsInWindow(array $events, DateTimeImmutable $anchor, int $days): array
    {
        $cutoff = $anchor->sub(new DateInterval('P' . max($days, 1) . 'D'));

        return array_values(array_filter(
            $events,
            static fn(array $event): bool => $event['event_at'] >= $cutoff && $event['event_at'] <= $anchor
        ));
    }

    protected function averageGapDays(array $events, int $tailCount, float $defaultValue): float
    {
        if (count($events) < 2) {
            return $defaultValue;
        }

        $events = array_slice($events, -1 * max($tailCount, 2));
        $gaps = [];

        for ($index = 1, $length = count($events); $index < $length; $index++) {
            $seconds = $events[$index]['event_at']->getTimestamp() - $events[$index - 1]['event_at']->getTimestamp();
            if ($seconds > 0) {
                $gaps[] = $seconds / 86400;
            }
        }

        if ($gaps === []) {
            return $defaultValue;
        }

        return array_sum($gaps) / count($gaps);
    }
}
