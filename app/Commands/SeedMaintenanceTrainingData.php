<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\AssetModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

class SeedMaintenanceTrainingData extends BaseCommand
{
    protected $group = 'SLAMS';
    protected $name = 'slams:seed-maintenance-training-data';
    protected $description = 'Seed a controlled maintenance history for the imported asset catalog used by the local predictive maintenance model.';
    protected $usage = 'slams:seed-maintenance-training-data [--refresh]';

    public function run(array $params)
    {
        $db = Database::connect();
        $seedPrefix = 'ML Seed:';
        $refresh = in_array('--refresh', $params, true)
            || in_array('refresh', $params, true)
            || CLI::getOption('refresh') !== null;
        $existingSeedIds = $this->existingSeedIds($seedPrefix);

        if ($existingSeedIds !== []) {
            if (! $refresh) {
                CLI::write('Seeded maintenance training data already exists. Skipping.', 'yellow');
                CLI::write('Existing seeded rows: ' . count($existingSeedIds), 'yellow');
                CLI::write('Use --refresh to rebuild the seeded maintenance baseline.', 'yellow');
                return;
            }

            $db->transStart();
            $db->table('maintenance_logs')->whereIn('maintenance_id', $existingSeedIds)->delete();
            $db->table('maintenance_records')->whereIn('id', $existingSeedIds)->delete();
            $db->transComplete();

            if (! $db->transStatus()) {
                CLI::error('Failed to remove the existing seeded maintenance data.');
                return;
            }

            CLI::write('Removed existing seeded maintenance rows: ' . count($existingSeedIds), 'yellow');
        }

        $currentCount = $db->table('maintenance_records')->countAllResults();
        $rows = $this->seedRows();

        if ($rows === []) {
            CLI::error('No matching assets were found for the maintenance training seed set.');
            return;
        }

        $remainingCapacity = max(50 - $currentCount, 0);
        if ($remainingCapacity <= 0) {
            CLI::error('Cannot seed training data because the maintenance table already has 50 or more rows.');
            return;
        }

        if (count($rows) > $remainingCapacity) {
            $rows = array_slice($rows, 0, $remainingCapacity);
        }

        $db->transStart();
        $assetIds = [];

        foreach ($rows as $row) {
            $db->table('maintenance_records')->insert($row['record']);
            $maintenanceId = (int) $db->insertID();
            $assetIds[] = (int) $row['record']['asset_id'];

            $db->table('maintenance_logs')->insert([
                'maintenance_id' => $maintenanceId,
                'changed_by' => $row['record']['assigned_technician_id'],
                'from_status' => null,
                'to_status' => 'completed',
                'notes' => 'Seeded predictive maintenance baseline record.',
                'created_at' => $row['record']['completed_at'],
            ]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            CLI::error('Failed to seed the maintenance training data.');
            return;
        }

        $assetModel = new AssetModel();
        foreach (array_unique($assetIds) as $assetId) {
            $assetModel->syncManagedAvailability((int) $assetId);
        }

        CLI::write('Seeded maintenance training rows: ' . count($rows), 'green');
        CLI::write('Maintenance table total rows: ' . ($currentCount + count($rows)), 'green');
        CLI::write('Assets covered by training seed: ' . count(array_unique($assetIds)), 'green');
    }

    protected function seedRows(): array
    {
        $timezone = new DateTimeZone('Asia/Kuala_Lumpur');
        $selectedAssets = $this->selectedSeedAssets();
        $technicianId = $this->firstUserIdForGroup('technician');
        $reporterId = $this->firstUserIdForGroup('student') ?: $this->firstUserIdForGroup('pic') ?: $this->firstUserIdForGroup('admin');

        $rows = [];

        foreach ($selectedAssets as $selectedAsset) {
            $asset = $selectedAsset['asset'];
            $assetId = (int) ($asset['id'] ?? 0);
            $totalQuantity = max((int) ($asset['total_quantity'] ?? $asset['quantity'] ?? 1), 1);
            $unitReference = trim((string) ($asset['asset_code'] ?? ''));

            if ($unitReference === '') {
                $unitReference = 'AST-' . str_pad((string) $assetId, 4, '0', STR_PAD_LEFT);
            }

            foreach ($this->profileEvents((string) $selectedAsset['profile']) as $eventIndex => $event) {
                $scheduledAt = new DateTimeImmutable($event['scheduled_for'], $timezone);
                $completedAt = $scheduledAt->add(new DateInterval('PT2H'));
                $createdAt = $this->createdAtForIssueType($scheduledAt, (string) $event['issue_type']);
                $acceptedAt = $scheduledAt->sub(new DateInterval('PT45M'));
                $testedAt = $completedAt->sub(new DateInterval('PT20M'));
                $quantityAffected = min(max((int) $event['quantity'], 1), $totalQuantity);
                $summary = $this->composeEventSummary($asset, (string) $event['summary']);

                $rows[] = [
                    'record' => [
                        'asset_id' => $assetId,
                        'quantity_affected' => $quantityAffected,
                        'unit_reference' => $unitReference . '-' . str_pad((string) ($eventIndex + 1), 2, '0', STR_PAD_LEFT),
                        'reported_by' => $reporterId > 0 ? $reporterId : null,
                        'assigned_technician_id' => $technicianId > 0 ? $technicianId : null,
                        'title' => 'ML Seed: ' . ucfirst((string) $event['issue_type']) . ' - ' . $summary,
                        'issue_type' => (string) $event['issue_type'],
                        'priority' => (string) $event['priority'],
                        'description' => 'Synthetic maintenance history aligned to the imported asset catalog for predictive maintenance training: ' . $summary . '.',
                        'report_photo_path' => null,
                        'status' => 'completed',
                        'asset_status_before' => 'available',
                        'asset_status_after' => 'available',
                        'scheduled_for' => $scheduledAt->format('Y-m-d H:i:s'),
                        'accepted_at' => $acceptedAt->format('Y-m-d H:i:s'),
                        'diagnosis_notes' => 'Technician identified a maintenance need for ' . strtolower($summary) . '.',
                        'started_at' => $scheduledAt->format('Y-m-d H:i:s'),
                        'work_notes' => 'Technician completed the required servicing steps and equipment checks.',
                        'tested_at' => $testedAt->format('Y-m-d H:i:s'),
                        'test_notes' => 'Post-service operational checks passed and the equipment returned to stable operation.',
                        'completed_at' => $completedAt->format('Y-m-d H:i:s'),
                        'resolution_notes' => 'Maintenance completed successfully for predictive baseline training.',
                        'completion_photo_path' => null,
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at' => $completedAt->format('Y-m-d H:i:s'),
                    ],
                ];
            }
        }

        usort($rows, static fn(array $a, array $b): int => strcmp(
            (string) $a['record']['created_at'],
            (string) $b['record']['created_at']
        ));

        return $rows;
    }

    protected function existingSeedIds(string $seedPrefix): array
    {
        return array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            Database::connect()
                ->table('maintenance_records')
                ->select('id')
                ->like('title', $seedPrefix, 'after')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray()
        );
    }

    protected function selectedSeedAssets(): array
    {
        $assets = $this->assetCatalog();
        if ($assets === []) {
            return [];
        }

        $plan = [
            'MATERIALS CHARACTERIZATION' => ['high_corrective', 'low_stable'],
            'MECHANICAL MATERIAL TESTING' => ['high_corrective', 'medium_balanced'],
            'MATERIAL PREPARATION' => ['high_corrective', 'overdue_planned'],
            'NDT' => ['low_stable'],
            'VEHICLE AND ENGINE TESTING' => ['high_corrective'],
            'ENVIROMENTAL AND INDOOR AIR QUALITY' => ['medium_balanced'],
            'ADVANCE MACHINING' => ['high_corrective'],
        ];

        $grouped = [];
        foreach ($assets as $asset) {
            $grouped[$this->normalizedCategory((string) ($asset['category'] ?? ''))][] = $asset;
        }

        foreach ($grouped as &$categoryAssets) {
            usort($categoryAssets, function (array $left, array $right): int {
                $scoreCompare = $this->assetSpecificityScore($right) <=> $this->assetSpecificityScore($left);
                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }

                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            });
        }
        unset($categoryAssets);

        $selected = [];
        $selectedIds = [];

        foreach ($plan as $category => $profiles) {
            $categoryAssets = array_values($grouped[$category] ?? []);
            if ($categoryAssets === []) {
                continue;
            }

            $assetIndex = 0;
            foreach ($profiles as $profile) {
                while (isset($categoryAssets[$assetIndex]) && in_array((int) $categoryAssets[$assetIndex]['id'], $selectedIds, true)) {
                    $assetIndex++;
                }

                if (! isset($categoryAssets[$assetIndex])) {
                    break;
                }

                $selected[] = [
                    'asset' => $categoryAssets[$assetIndex],
                    'profile' => $profile,
                ];
                $selectedIds[] = (int) $categoryAssets[$assetIndex]['id'];
                $assetIndex++;
            }
        }

        return $selected;
    }

    protected function assetCatalog(): array
    {
        return Database::connect()
            ->table('assets')
            ->select('id, name, category, model, asset_code, total_quantity, quantity')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    protected function assetSpecificityScore(array $asset): int
    {
        $name = strtolower(trim((string) ($asset['name'] ?? '')));
        $model = strtolower(trim((string) ($asset['model'] ?? '')));
        $score = $model !== '' ? 100 : 0;

        foreach (['sem', 'x-ray', 'instron', 'olympus', 'flir', 'cnc', 'edm', 'furnace', 'dyno', 'meter', 'autolab'] as $keyword) {
            if (str_contains($name, $keyword) || str_contains($model, $keyword)) {
                $score += 12;
            }
        }

        if ($model !== '') {
            $score += min(strlen($model), 40);
        }

        return $score;
    }

    protected function normalizedCategory(string $category): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/', ' ', $category)));
    }

    protected function profileEvents(string $profile): array
    {
        return match ($profile) {
            'high_corrective' => [
                ['scheduled_for' => '2025-02-18 09:00:00', 'issue_type' => 'inspection', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'baseline operational inspection'],
                ['scheduled_for' => '2025-05-27 10:15:00', 'issue_type' => 'corrective', 'priority' => 'high', 'quantity' => 1, 'summary' => 'corrective stabilization after abnormal readings'],
                ['scheduled_for' => '2025-09-03 09:30:00', 'issue_type' => 'preventive', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'preventive servicing after sustained usage'],
                ['scheduled_for' => '2026-01-19 11:00:00', 'issue_type' => 'corrective', 'priority' => 'high', 'quantity' => 1, 'summary' => 'recurring subsystem repair after performance drift'],
                ['scheduled_for' => '2026-04-17 09:15:00', 'issue_type' => 'calibration', 'priority' => 'high', 'quantity' => 1, 'summary' => 'calibration recovery following repeat drift'],
            ],
            'overdue_planned' => [
                ['scheduled_for' => '2025-01-24 09:00:00', 'issue_type' => 'inspection', 'priority' => 'low', 'quantity' => 1, 'summary' => 'baseline condition inspection'],
                ['scheduled_for' => '2025-03-18 09:45:00', 'issue_type' => 'preventive', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'planned preventive servicing'],
                ['scheduled_for' => '2025-08-07 10:00:00', 'issue_type' => 'calibration', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'scheduled calibration cycle'],
                ['scheduled_for' => '2026-02-11 08:45:00', 'issue_type' => 'inspection', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'post-semester operational inspection'],
            ],
            'medium_balanced' => [
                ['scheduled_for' => '2025-03-06 09:00:00', 'issue_type' => 'inspection', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'semester readiness inspection'],
                ['scheduled_for' => '2025-07-14 10:15:00', 'issue_type' => 'preventive', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'midyear preventive servicing'],
                ['scheduled_for' => '2025-12-02 09:30:00', 'issue_type' => 'corrective', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'minor corrective response to intermittent issue'],
                ['scheduled_for' => '2026-03-26 10:00:00', 'issue_type' => 'calibration', 'priority' => 'medium', 'quantity' => 1, 'summary' => 'scheduled verification and calibration'],
            ],
            'low_stable' => [
                ['scheduled_for' => '2025-02-12 09:15:00', 'issue_type' => 'inspection', 'priority' => 'low', 'quantity' => 1, 'summary' => 'routine safety inspection'],
                ['scheduled_for' => '2025-09-10 09:00:00', 'issue_type' => 'inspection', 'priority' => 'low', 'quantity' => 1, 'summary' => 'scheduled operational check'],
                ['scheduled_for' => '2026-03-18 10:00:00', 'issue_type' => 'preventive', 'priority' => 'low', 'quantity' => 1, 'summary' => 'recent preventive upkeep with stable outcome'],
            ],
            default => [],
        };
    }

    protected function composeEventSummary(array $asset, string $summary): string
    {
        $reference = trim((string) ($asset['model'] ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($asset['name'] ?? 'Asset'));
        }

        return ucfirst($summary) . ' for ' . $reference;
    }

    protected function createdAtForIssueType(DateTimeImmutable $scheduledAt, string $issueType): DateTimeImmutable
    {
        return match ($issueType) {
            'corrective' => $scheduledAt->sub(new DateInterval('PT4H')),
            'inspection' => $scheduledAt->sub(new DateInterval('P2D')),
            default => $scheduledAt->sub(new DateInterval('P5D')),
        };
    }

    protected function firstUserIdForGroup(string $groupName): int
    {
        $db = Database::connect();

        try {
            if ($db->fieldExists('group', 'auth_groups_users')) {
                $row = $db->table('auth_groups_users')
                    ->select('user_id')
                    ->where('group', $groupName)
                    ->orderBy('user_id', 'ASC')
                    ->get()
                    ->getRowArray();

                return (int) ($row['user_id'] ?? 0);
            }

            if ($db->fieldExists('group_id', 'auth_groups_users') && $db->tableExists('auth_groups')) {
                $row = $db->table('auth_groups_users agu')
                    ->select('agu.user_id')
                    ->join('auth_groups ag', 'ag.id = agu.group_id', 'inner')
                    ->where('ag.name', $groupName)
                    ->orderBy('agu.user_id', 'ASC')
                    ->get()
                    ->getRowArray();

                return (int) ($row['user_id'] ?? 0);
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return 0;
    }
}
