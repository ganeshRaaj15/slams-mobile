<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\MaintenancePredictionService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TrainMaintenanceModel extends BaseCommand
{
    protected $group = 'SLAMS';
    protected $name = 'slams:train-maintenance-model';
    protected $description = 'Train the local predictive maintenance model and persist it to writable/models.';
    protected $usage = 'slams:train-maintenance-model [horizonDays] [stepDays] [lookbackDays]';

    public function run(array $params)
    {
        $horizonDays = max((int) ($params[0] ?? 60), 14);
        $stepDays = max((int) ($params[1] ?? 7), 7);
        $lookbackDays = max((int) ($params[2] ?? 540), 180);

        try {
            $service = new MaintenancePredictionService();
            $model = $service->trainAndPersist($horizonDays, $stepDays, $lookbackDays);
            $summary = $service->getModelSummary();
            $metrics = $summary['metrics'] ?? [];

            CLI::write('Maintenance model trained successfully.', 'green');
            CLI::write('Model path: ' . ($summary['path'] ?? $service->modelPath()), 'green');
            CLI::write('Training samples: ' . (int) ($summary['dataset']['samples_total'] ?? 0), 'green');
            CLI::write('Test accuracy: ' . $this->formatPercent((float) ($metrics['accuracy'] ?? 0.0)), 'green');
            CLI::write('Test precision: ' . $this->formatPercent((float) ($metrics['precision'] ?? 0.0)), 'green');
            CLI::write('Test recall: ' . $this->formatPercent((float) ($metrics['recall'] ?? 0.0)), 'green');
            CLI::write('Test F1: ' . $this->formatPercent((float) ($metrics['f1'] ?? 0.0)), 'green');
            CLI::write('Decision threshold: ' . number_format((float) ($model['threshold'] ?? 0.5), 2), 'green');
        } catch (\Throwable $e) {
            log_message('error', 'Maintenance model training failed: ' . $e->getMessage());
            CLI::error('Maintenance model training failed: ' . $e->getMessage());
        }
    }

    protected function formatPercent(float $value): string
    {
        return number_format($value * 100, 1) . '%';
    }
}
