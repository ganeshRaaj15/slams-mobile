<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditDeployment extends BaseCommand
{
    protected $group = 'SLAMS';
    protected $name = 'slams:audit-deployment';
    protected $description = 'Audit common deployment hygiene risks for SLAMS before production rollout.';
    protected $usage = 'slams:audit-deployment';

    public function run(array $params)
    {
        $warnings = 0;
        $passes = 0;

        $appConfig = config('App');

        $environment = strtolower((string) ENVIRONMENT);
        if ($environment === 'production') {
            $this->ok('Environment', 'CI environment is production.');
            $passes++;
        } else {
            $this->warn('Environment', 'CI environment is "' . ENVIRONMENT . '". Production deployments should use "production".');
            $warnings++;
        }

        $baseURL = rtrim((string) $appConfig->baseURL, '/');
        if ($baseURL !== '' && ! preg_match('#https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $baseURL)) {
            $this->ok('Base URL', 'Application base URL is set to ' . $baseURL . '.');
            $passes++;
        } else {
            $this->warn('Base URL', 'Application base URL still points to a local address: ' . ($baseURL !== '' ? $baseURL : '(empty)') . '.');
            $warnings++;
        }

        if (defined('CI_DEBUG') && CI_DEBUG === false) {
            $this->ok('Debug Mode', 'CI_DEBUG is disabled.');
            $passes++;
        } else {
            $this->warn('Debug Mode', 'CI_DEBUG is enabled. Disable it in production.');
            $warnings++;
        }

        if ($appConfig->forceGlobalSecureRequests) {
            $this->ok('HTTPS Enforcement', 'forceGlobalSecureRequests is enabled.');
            $passes++;
        } else {
            $this->warn('HTTPS Enforcement', 'forceGlobalSecureRequests is disabled. Enable HTTPS enforcement unless TLS is handled upstream.');
            $warnings++;
        }

        $publicPdfCount = $this->countFiles(FCPATH . 'uploads/pdfs', '/\.pdf$/i');
        if ($publicPdfCount === 0) {
            $this->ok('Public PDFs', 'No legacy public PDFs were found under public/uploads/pdfs.');
            $passes++;
        } else {
            $this->warn('Public PDFs', $publicPdfCount . ' PDF file(s) still exist under public/uploads/pdfs. Keep rewrite protection in place and migrate or remove them when safe.');
            $warnings++;
        }

        $publicUploadDirs = [
            'public/uploads/labs' => FCPATH . 'uploads/labs',
            'public/uploads/pic' => FCPATH . 'uploads/pic',
            'public/images/users' => FCPATH . 'images/users',
            'public/images/maintenance' => FCPATH . 'images/maintenance',
        ];

        foreach ($publicUploadDirs as $label => $directory) {
            $fileCount = $this->countFiles($directory);
            if ($fileCount === 0) {
                $this->ok($label, 'No generated files detected.');
                $passes++;
                continue;
            }

            $this->warn($label, $fileCount . ' generated file(s) detected. Keep these out of source control and review retention for production.');
            $warnings++;
        }

        $runtimeDirs = [
            'writable/logs' => ROOTPATH . 'writable/logs',
            'writable/debugbar' => ROOTPATH . 'writable/debugbar',
            'writable/session' => ROOTPATH . 'writable/session',
        ];

        foreach ($runtimeDirs as $label => $directory) {
            $fileCount = $this->countFiles($directory);
            if ($fileCount === 0) {
                $this->ok($label, 'No runtime artifacts detected.');
                $passes++;
                continue;
            }

            $this->warn($label, $fileCount . ' runtime file(s) detected. Confirm log rotation and avoid packaging writable runtime data into deployments.');
            $warnings++;
        }

        CLI::newLine();
        if ($warnings === 0) {
            CLI::write('Deployment hygiene audit passed with ' . $passes . ' checks.', 'green');
            return;
        }

        CLI::write('Deployment hygiene audit completed with ' . $warnings . ' warning(s) and ' . $passes . ' passing check(s).', 'yellow');
    }

    private function countFiles(string $directory, ?string $namePattern = null): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            if (in_array($filename, ['index.html', '.htaccess', '.gitkeep'], true)) {
                continue;
            }

            if ($namePattern !== null && ! preg_match($namePattern, $filename)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function ok(string $label, string $message): void
    {
        CLI::write('[OK] ' . $label . ': ' . $message, 'green');
    }

    private function warn(string $label, string $message): void
    {
        CLI::write('[WARN] ' . $label . ': ' . $message, 'yellow');
    }
}
