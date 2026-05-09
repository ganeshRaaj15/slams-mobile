<?php

namespace App\Libraries;

use CodeIgniter\Email\Email;
use Config\App as AppConfig;
use Config\Email as EmailConfig;

class SmtpEmail extends Email
{
    protected string $SMTPHeloHost = '';

    /**
     * @param array|EmailConfig|null $config
     */
    public function __construct($config = null)
    {
        if ($config instanceof EmailConfig) {
            $this->SMTPHeloHost = trim((string) $config->SMTPHeloHost);
        } elseif (is_array($config) && isset($config['SMTPHeloHost'])) {
            $this->SMTPHeloHost = trim((string) $config['SMTPHeloHost']);
        }

        parent::__construct($config);
    }

    protected function getHostname()
    {
        $superglobals = service('superglobals');
        $candidates = [
            $this->SMTPHeloHost,
            $this->baseUrlHost(),
            $superglobals->server('SERVER_NAME'),
            $superglobals->server('SERVER_ADDR'),
            gethostname() ?: null,
        ];

        foreach ($candidates as $candidate) {
            $hostname = $this->normalizeHostname($candidate);
            if ($hostname !== null) {
                return $hostname;
            }
        }

        return '[127.0.0.1]';
    }

    private function baseUrlHost(): ?string
    {
        $baseUrl = (string) config(AppConfig::class)->baseURL;
        $host = parse_url($baseUrl, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    private function normalizeHostname($candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/^\[(.+)\]$/', $candidate, $matches) === 1) {
            return filter_var($matches[1], FILTER_VALIDATE_IP) ? $candidate : null;
        }

        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return '[' . $candidate . ']';
        }

        if (
            str_contains($candidate, '.')
            && filter_var($candidate, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        ) {
            return $candidate;
        }

        return null;
    }
}
