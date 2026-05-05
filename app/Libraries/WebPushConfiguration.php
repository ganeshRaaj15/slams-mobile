<?php

namespace App\Libraries;

class WebPushConfiguration
{
    private array $overrides;

    public function __construct(array $overrides = [])
    {
        $this->overrides = $overrides;
    }

    public function isConfigured(): bool
    {
        return $this->vapidSubject() !== ''
            && $this->vapidPublicKey() !== ''
            && $this->vapidPrivateKey() !== '';
    }

    public function vapidSubject(): string
    {
        return $this->stringValue('subject', 'webpush.vapidSubject');
    }

    public function vapidPublicKey(): string
    {
        return $this->stringValue('publicKey', 'webpush.vapidPublicKey');
    }

    public function vapidPrivateKey(): string
    {
        return $this->stringValue('privateKey', 'webpush.vapidPrivateKey');
    }

    public function defaultTtl(): int
    {
        $override = $this->overrides['ttl'] ?? null;
        if ($override !== null && $override !== '') {
            return max((int) $override, 60);
        }

        return max((int) env('webpush.defaultTtl', 1800), 60);
    }

    public function authOptions(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return [
            'VAPID' => [
                'subject' => $this->vapidSubject(),
                'publicKey' => $this->vapidPublicKey(),
                'privateKey' => $this->vapidPrivateKey(),
            ],
        ];
    }

    public function clientConfig(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'publicKey' => $this->vapidPublicKey(),
        ];
    }

    public function diagnostics(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'subject' => $this->vapidSubject(),
            'hasPublicKey' => $this->vapidPublicKey() !== '',
            'hasPrivateKey' => $this->vapidPrivateKey() !== '',
            'defaultTtl' => $this->defaultTtl(),
        ];
    }

    private function stringValue(string $overrideKey, string $envKey): string
    {
        if (array_key_exists($overrideKey, $this->overrides)) {
            return trim((string) $this->overrides[$overrideKey]);
        }

        $value = env($envKey, '');
        return is_string($value) ? trim($value) : '';
    }
}
