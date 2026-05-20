<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

class MaintenancePredictionService
{
    protected \CodeIgniter\Database\BaseConnection $db;
    protected DateTimeZone $timezone;
    protected MaintenanceFeatureExtractor $featureExtractor;
    protected MaintenanceModelTrainer $trainer;
    protected string $modelPath;

    public function __construct(
        ?\CodeIgniter\Database\BaseConnection $db = null,
        ?DateTimeZone $timezone = null,
        ?MaintenanceFeatureExtractor $featureExtractor = null,
        ?MaintenanceModelTrainer $trainer = null,
        ?string $modelPath = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->timezone = $timezone ?? new DateTimeZone('Asia/Kuala_Lumpur');
        $this->featureExtractor = $featureExtractor ?? new MaintenanceFeatureExtractor($this->db, $this->timezone);
        $this->trainer = $trainer ?? new MaintenanceModelTrainer();
        $this->modelPath = $modelPath ?? WRITEPATH . 'models/maintenance_predictor.json';
    }

    public function modelPath(): string
    {
        return $this->modelPath;
    }

    public function modelExists(): bool
    {
        return is_file($this->modelPath);
    }

    public function loadModel(): ?array
    {
        if (! $this->modelExists()) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($this->modelPath), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function saveModel(array $model): void
    {
        $directory = dirname($this->modelPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($this->modelPath, json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function trainAndPersist(int $horizonDays = 60, int $stepDays = 7, int $lookbackDays = 540): array
    {
        $samples = $this->featureExtractor->buildTrainingSamples($horizonDays, $stepDays, $lookbackDays);
        $model = $this->trainer->train($samples);
        $model['training'] = [
            'horizon_days' => $horizonDays,
            'step_days' => $stepDays,
            'lookback_days' => $lookbackDays,
            'records_used' => $this->db->table('maintenance_records')->countAllResults(),
            'assets_used' => $this->db->table('assets')->countAllResults(),
        ];
        $this->saveModel($model);

        return $model;
    }

    public function getModelSummary(): array
    {
        $model = $this->loadModel();
        if (! $model) {
            return [
                'available' => false,
                'path' => $this->modelPath,
            ];
        }

        return [
            'available' => true,
            'path' => $this->modelPath,
            'trained_at' => $model['trained_at'] ?? null,
            'threshold' => (float) ($model['threshold'] ?? 0.5),
            'metrics' => $model['metrics']['test'] ?? [],
            'dataset' => $model['dataset'] ?? [],
            'training' => $model['training'] ?? [],
        ];
    }

    public function predictAsset(int $assetId, ?array $model = null): ?array
    {
        $model = $model ?? $this->loadModel();
        if (! $model) {
            return null;
        }

        $asset = $this->db->table('assets a')
            ->select('a.id, a.name, a.asset_code, a.status, a.total_quantity, a.quantity, l.name AS lab_name, l.room AS lab_room')
            ->join('laboratories l', 'l.id = a.lab_id', 'left')
            ->where('a.id', $assetId)
            ->get()
            ->getRowArray();

        if (! $asset) {
            return null;
        }

        $features = $this->featureExtractor->extractCurrentAssetFeatures($assetId);
        if (! $features) {
            return null;
        }

        $modelProbability = $this->trainer->predictProbability($features, $model);
        $probability = max($modelProbability, $this->ruleBasedFloor($features));
        $threshold = (float) ($model['threshold'] ?? 0.5);

        return array_merge($asset, [
            'risk_probability' => $probability,
            'model_probability_raw' => $modelProbability,
            'risk_percent' => (int) round($probability * 100),
            'risk_band' => $this->riskBand($probability),
            'decision' => $this->decision($probability, $features, $threshold),
            'reasons' => $this->reasons($features),
            'features' => $features,
            'threshold' => $threshold,
        ]);
    }

    public function predictAllAssets(?array $model = null): array
    {
        $model = $model ?? $this->loadModel();
        if (! $model) {
            return [];
        }

        $assets = $this->db->table('assets')
            ->select('id')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $predictions = [];
        foreach ($assets as $asset) {
            $prediction = $this->predictAsset((int) ($asset['id'] ?? 0), $model);
            if ($prediction) {
                $predictions[] = $prediction;
            }
        }

        usort($predictions, static function (array $a, array $b): int {
            $riskSort = ($b['risk_probability'] <=> $a['risk_probability']);
            if ($riskSort !== 0) {
                return $riskSort;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $predictions;
    }

    public function decision(float $probability, array $features, float $threshold): array
    {
        $plannedGapDelta = (float) ($features['planned_gap_delta'] ?? 0.0);
        $correctiveRecent = (float) ($features['corrective_last_120d'] ?? 0.0);
        $highPriority = (float) ($features['high_priority_last_180d'] ?? 0.0);
        $bookingPressure = (float) ($features['bookings_last_30d'] ?? 0.0);
        $bookingUnitsPressure = (float) ($features['booking_units_last_90d'] ?? 0.0);

        if (
            $probability >= 0.72
            || $plannedGapDelta >= 45.0
            || $correctiveRecent >= 2.0
            || $highPriority >= 1.0
            || ($bookingPressure >= 6.0 && $bookingUnitsPressure >= 6.0)
        ) {
            return [
                'action' => 'schedule_now',
                'label' => 'Schedule preventive maintenance now',
                'priority' => 'high',
            ];
        }

        if ($probability >= max($threshold, 0.45) || $plannedGapDelta >= 14.0 || $bookingPressure >= 3.0) {
            return [
                'action' => 'inspect_soon',
                'label' => 'Inspect within 14 days',
                'priority' => 'medium',
            ];
        }

        return [
            'action' => 'monitor',
            'label' => 'Normal monitoring',
            'priority' => 'low',
        ];
    }

    protected function riskBand(float $probability): string
    {
        if ($probability >= 0.72) {
            return 'high';
        }
        if ($probability >= 0.45) {
            return 'medium';
        }

        return 'low';
    }

    protected function reasons(array $features): array
    {
        $reasons = [];
        $hasCorrectivePressure = false;

        if ((float) ($features['corrective_last_120d'] ?? 0.0) >= 2.0) {
            $reasons[] = 'Multiple corrective cases were recorded in the last 120 days.';
            $hasCorrectivePressure = true;
        }

        if ((float) ($features['high_priority_last_180d'] ?? 0.0) >= 1.0) {
            $reasons[] = 'At least one high-priority maintenance case was recorded recently.';
            $hasCorrectivePressure = true;
        }

        if ((float) ($features['planned_gap_delta'] ?? 0.0) >= 30.0) {
            $reasons[] = 'The planned maintenance interval has been exceeded.';
        }

        if ((float) ($features['bookings_last_30d'] ?? 0.0) >= 4.0) {
            $reasons[] = 'Recent booking demand is elevated for this asset.';
        }

        if ((float) ($features['booking_units_last_90d'] ?? 0.0) >= 6.0) {
            $reasons[] = 'High recent equipment utilization was detected from booking history.';
        }

        if ($hasCorrectivePressure && (float) ($features['events_last_30d'] ?? 0.0) >= 1.0) {
            $reasons[] = 'The asset had maintenance activity in the last 30 days.';
        }

        if ((float) ($features['corrective_ratio_365d'] ?? 0.0) >= 0.45) {
            $reasons[] = 'A large share of the last year\'s maintenance history was corrective rather than preventive.';
        }

        if ($reasons === []) {
            if ((float) ($features['days_since_last_planned'] ?? 999.0) <= 30.0) {
                $reasons[] = 'Planned maintenance was completed recently, so no immediate action is needed.';
            } else {
                $reasons[] = 'Recent maintenance history is stable and within the expected planned interval.';
            }
        }

        return array_slice($reasons, 0, 3);
    }

    protected function ruleBasedFloor(array $features): float
    {
        if ((float) ($features['corrective_last_120d'] ?? 0.0) >= 2.0) {
            return 0.82;
        }

        if ((float) ($features['high_priority_last_180d'] ?? 0.0) >= 1.0 && (float) ($features['events_last_30d'] ?? 0.0) >= 1.0) {
            return 0.74;
        }

        if ((float) ($features['planned_gap_delta'] ?? 0.0) >= 45.0) {
            return 0.76;
        }

        if ((float) ($features['bookings_last_30d'] ?? 0.0) >= 6.0 && (float) ($features['planned_gap_delta'] ?? 0.0) >= 14.0) {
            return 0.74;
        }

        if ((float) ($features['booking_units_last_90d'] ?? 0.0) >= 8.0) {
            return 0.6;
        }

        if ((float) ($features['planned_gap_delta'] ?? 0.0) >= 14.0) {
            return 0.52;
        }

        if ((float) ($features['corrective_ratio_365d'] ?? 0.0) >= 0.45 && (float) ($features['events_last_30d'] ?? 0.0) >= 1.0) {
            return 0.58;
        }

        return 0.0;
    }
}
