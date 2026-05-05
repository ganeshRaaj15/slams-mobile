<?php

declare(strict_types=1);

namespace App\Libraries;

class MaintenanceModelTrainer
{
    public function train(array $samples, int $iterations = 2500, float $learningRate = 0.08, float $l2 = 0.01): array
    {
        if (count($samples) < 12) {
            throw new \RuntimeException('At least 12 training samples are required to train the maintenance model.');
        }

        usort($samples, static fn(array $a, array $b): int => strcmp((string) $a['anchor_date'], (string) $b['anchor_date']));

        $featureNames = array_keys($samples[0]['features'] ?? []);
        if ($featureNames === []) {
            throw new \RuntimeException('Training samples do not contain feature columns.');
        }

        $splitIndex = max((int) floor(count($samples) * 0.7), 1);
        if ($splitIndex >= count($samples)) {
            $splitIndex = count($samples) - 1;
        }

        $trainSamples = array_slice($samples, 0, $splitIndex);
        $testSamples = array_slice($samples, $splitIndex);

        [$means, $stds] = $this->fitScaler($trainSamples, $featureNames);
        $weights = array_fill_keys($featureNames, 0.0);
        $bias = 0.0;

        [$positiveWeight, $negativeWeight] = $this->classWeights($trainSamples);

        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $weightGradients = array_fill_keys($featureNames, 0.0);
            $biasGradient = 0.0;

            foreach ($trainSamples as $sample) {
                $vector = $this->standardizeVector($sample['features'], $featureNames, $means, $stds);
                $label = (float) ($sample['label'] ?? 0);
                $probability = $this->sigmoid($this->linearScore($vector, $weights, $bias));
                $sampleWeight = $label >= 0.5 ? $positiveWeight : $negativeWeight;
                $error = ($probability - $label) * $sampleWeight;

                foreach ($featureNames as $name) {
                    $weightGradients[$name] += $error * $vector[$name];
                }

                $biasGradient += $error;
            }

            $count = max(count($trainSamples), 1);
            foreach ($featureNames as $name) {
                $gradient = ($weightGradients[$name] / $count) + ($l2 * $weights[$name]);
                $weights[$name] -= $learningRate * $gradient;
            }

            $bias -= $learningRate * ($biasGradient / $count);
        }

        $trainProbabilities = $this->predictProbabilities($trainSamples, $featureNames, $weights, $bias, $means, $stds);
        $threshold = $this->bestThreshold($trainProbabilities, array_column($trainSamples, 'label'));
        $testProbabilities = $this->predictProbabilities($testSamples, $featureNames, $weights, $bias, $means, $stds);

        return [
            'version' => 1,
            'trained_at' => date('c'),
            'feature_names' => $featureNames,
            'means' => $means,
            'stds' => $stds,
            'weights' => $weights,
            'bias' => $bias,
            'threshold' => $threshold,
            'metrics' => [
                'train' => $this->evaluate($trainProbabilities, array_column($trainSamples, 'label'), $threshold),
                'test' => $this->evaluate($testProbabilities, array_column($testSamples, 'label'), $threshold),
            ],
            'dataset' => [
                'samples_total' => count($samples),
                'samples_train' => count($trainSamples),
                'samples_test' => count($testSamples),
                'positive_train' => array_sum(array_map(static fn(array $sample): int => (int) ($sample['label'] ?? 0), $trainSamples)),
                'positive_test' => array_sum(array_map(static fn(array $sample): int => (int) ($sample['label'] ?? 0), $testSamples)),
            ],
        ];
    }

    public function predictProbability(array $features, array $model): float
    {
        $featureNames = $model['feature_names'] ?? [];
        $weights = $model['weights'] ?? [];
        $means = $model['means'] ?? [];
        $stds = $model['stds'] ?? [];
        $bias = (float) ($model['bias'] ?? 0.0);

        $vector = $this->standardizeVector($features, $featureNames, $means, $stds);

        return $this->sigmoid($this->linearScore($vector, $weights, $bias));
    }

    public function featureContributions(array $features, array $model): array
    {
        $featureNames = $model['feature_names'] ?? [];
        $weights = $model['weights'] ?? [];
        $means = $model['means'] ?? [];
        $stds = $model['stds'] ?? [];
        $vector = $this->standardizeVector($features, $featureNames, $means, $stds);
        $contributions = [];

        foreach ($featureNames as $name) {
            $contributions[$name] = ($vector[$name] ?? 0.0) * (float) ($weights[$name] ?? 0.0);
        }

        arsort($contributions);

        return $contributions;
    }

    protected function fitScaler(array $samples, array $featureNames): array
    {
        $means = array_fill_keys($featureNames, 0.0);
        $stds = array_fill_keys($featureNames, 1.0);
        $count = max(count($samples), 1);

        foreach ($featureNames as $name) {
            $sum = 0.0;
            foreach ($samples as $sample) {
                $sum += (float) ($sample['features'][$name] ?? 0.0);
            }
            $means[$name] = $sum / $count;
        }

        foreach ($featureNames as $name) {
            $variance = 0.0;
            foreach ($samples as $sample) {
                $value = (float) ($sample['features'][$name] ?? 0.0);
                $variance += ($value - $means[$name]) ** 2;
            }
            $std = sqrt($variance / $count);
            $stds[$name] = $std > 1.0e-6 ? $std : 1.0;
        }

        return [$means, $stds];
    }

    protected function classWeights(array $samples): array
    {
        $positive = 0;
        foreach ($samples as $sample) {
            $positive += (int) ($sample['label'] ?? 0);
        }

        $total = max(count($samples), 1);
        $negative = max($total - $positive, 1);
        $positive = max($positive, 1);

        return [
            $total / (2 * $positive),
            $total / (2 * $negative),
        ];
    }

    protected function standardizeVector(array $features, array $featureNames, array $means, array $stds): array
    {
        $vector = [];

        foreach ($featureNames as $name) {
            $value = (float) ($features[$name] ?? 0.0);
            $vector[$name] = ($value - (float) ($means[$name] ?? 0.0)) / (float) ($stds[$name] ?? 1.0);
        }

        return $vector;
    }

    protected function linearScore(array $vector, array $weights, float $bias): float
    {
        $score = $bias;

        foreach ($vector as $name => $value) {
            $score += $value * (float) ($weights[$name] ?? 0.0);
        }

        return $score;
    }

    protected function predictProbabilities(array $samples, array $featureNames, array $weights, float $bias, array $means, array $stds): array
    {
        $probabilities = [];

        foreach ($samples as $sample) {
            $vector = $this->standardizeVector($sample['features'], $featureNames, $means, $stds);
            $probabilities[] = $this->sigmoid($this->linearScore($vector, $weights, $bias));
        }

        return $probabilities;
    }

    protected function bestThreshold(array $probabilities, array $labels): float
    {
        $bestThreshold = 0.5;
        $bestF1 = -1.0;

        for ($threshold = 0.35; $threshold <= 0.75; $threshold += 0.02) {
            $metrics = $this->evaluate($probabilities, $labels, $threshold);
            if (($metrics['f1'] ?? 0.0) > $bestF1) {
                $bestF1 = (float) ($metrics['f1'] ?? 0.0);
                $bestThreshold = round($threshold, 2);
            }
        }

        return $bestThreshold;
    }

    protected function evaluate(array $probabilities, array $labels, float $threshold): array
    {
        $tp = 0;
        $tn = 0;
        $fp = 0;
        $fn = 0;

        foreach ($probabilities as $index => $probability) {
            $actual = (int) ($labels[$index] ?? 0);
            $predicted = $probability >= $threshold ? 1 : 0;

            if ($predicted === 1 && $actual === 1) {
                $tp++;
            } elseif ($predicted === 0 && $actual === 0) {
                $tn++;
            } elseif ($predicted === 1) {
                $fp++;
            } else {
                $fn++;
            }
        }

        $total = max($tp + $tn + $fp + $fn, 1);
        $precision = $tp + $fp > 0 ? $tp / ($tp + $fp) : 0.0;
        $recall = $tp + $fn > 0 ? $tp / ($tp + $fn) : 0.0;
        $f1 = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;

        return [
            'accuracy' => ($tp + $tn) / $total,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $f1,
            'threshold' => $threshold,
            'confusion' => ['tp' => $tp, 'tn' => $tn, 'fp' => $fp, 'fn' => $fn],
        ];
    }

    protected function sigmoid(float $value): float
    {
        if ($value < -35.0) {
            return 0.0;
        }
        if ($value > 35.0) {
            return 1.0;
        }

        return 1.0 / (1.0 + exp(-1.0 * $value));
    }
}
