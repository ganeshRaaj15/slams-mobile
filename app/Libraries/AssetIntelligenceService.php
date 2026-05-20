<?php

declare(strict_types=1);

namespace App\Libraries;

class AssetIntelligenceService
{
    protected MaintenancePredictionService $predictionService;
    protected MaintenanceForecastService $forecastService;

    public function __construct(
        ?MaintenancePredictionService $predictionService = null,
        ?MaintenanceForecastService $forecastService = null
    ) {
        $this->predictionService = $predictionService ?? new MaintenancePredictionService();
        $this->forecastService = $forecastService ?? new MaintenanceForecastService();
    }

    public function mapForAssets(int $daysAhead = 120): array
    {
        $predictions = [];
        foreach ($this->predictionService->predictAllAssets() as $prediction) {
            $predictions[(int) ($prediction['id'] ?? 0)] = $prediction;
        }

        $forecasts = [];
        foreach ($this->forecastService->getUpcomingForecasts($daysAhead) as $forecast) {
            $forecasts[(int) ($forecast['asset_id'] ?? 0)] = $forecast;
        }

        $map = [];
        foreach (array_unique(array_merge(array_keys($predictions), array_keys($forecasts))) as $assetId) {
            $prediction = $predictions[$assetId] ?? null;
            $forecast = $forecasts[$assetId] ?? null;
            $features = is_array($prediction['features'] ?? null) ? $prediction['features'] : [];
            $decision = is_array($prediction['decision'] ?? null)
                ? $prediction['decision']
                : [
                    'action' => 'monitor',
                    'label' => 'Normal monitoring',
                    'priority' => 'low',
                ];

            $map[(int) $assetId] = [
                'risk_probability' => (float) ($prediction['risk_probability'] ?? 0.0),
                'risk_percent' => (int) ($prediction['risk_percent'] ?? 0),
                'risk_band' => (string) ($prediction['risk_band'] ?? 'low'),
                'decision' => $decision,
                'decision_label' => (string) ($decision['label'] ?? 'Normal monitoring'),
                'decision_priority' => (string) ($decision['priority'] ?? 'low'),
                'reasons' => array_values(array_filter($prediction['reasons'] ?? [], static fn($value): bool => is_string($value) && trim($value) !== '')),
                'next_due_at' => (string) ($forecast['next_due_at'] ?? ''),
                'last_completed_at' => (string) ($forecast['last_completed_at'] ?? ''),
                'days_until' => isset($forecast['days_until']) ? (int) $forecast['days_until'] : null,
                'forecast_status' => (string) ($forecast['status'] ?? ''),
                'bookings_last_30d' => (int) round((float) ($features['bookings_last_30d'] ?? 0.0)),
                'bookings_last_90d' => (int) round((float) ($features['bookings_last_90d'] ?? 0.0)),
                'booking_units_last_90d' => (int) round((float) ($features['booking_units_last_90d'] ?? 0.0)),
                'days_since_last_booking' => (int) round((float) ($features['days_since_last_booking'] ?? 0.0)),
                'planned_gap_delta' => (int) round((float) ($features['planned_gap_delta'] ?? 0.0)),
            ];
        }

        return $map;
    }

    public function stats(array $intelligenceMap): array
    {
        $highRisk = 0;
        $dueSoon = 0;
        $predictedActions = 0;

        foreach ($intelligenceMap as $item) {
            if (($item['risk_band'] ?? 'low') === 'high') {
                $highRisk++;
            }
            if (($item['decision_priority'] ?? 'low') === 'high') {
                $predictedActions++;
            }
            $daysUntil = $item['days_until'] ?? null;
            if (is_int($daysUntil) && $daysUntil <= 30) {
                $dueSoon++;
            }
        }

        return [
            'high_risk' => $highRisk,
            'due_soon' => $dueSoon,
            'predicted_actions' => $predictedActions,
        ];
    }
}
