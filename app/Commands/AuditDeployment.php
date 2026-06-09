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
        $baseURL = rtrim((string) $appConfig->baseURL, '/');
        $baseUrlHost = (string) (parse_url($baseURL, PHP_URL_HOST) ?: '');
        $baseUrlScheme = strtolower((string) (parse_url($baseURL, PHP_URL_SCHEME) ?: ''));
        $baseUrlPath = trim((string) (parse_url($baseURL, PHP_URL_PATH) ?: ''));

        $environment = strtolower((string) ENVIRONMENT);
        if ($environment === 'production') {
            $this->ok('Environment', 'CI environment is production.');
            $passes++;
        } else {
            $this->warn('Environment', 'CI environment is "' . ENVIRONMENT . '". Production deployments should use "production".');
            $warnings++;
        }

        if ($baseURL !== '' && $baseUrlHost !== '' && ! $this->isLocalDevelopmentHost($baseUrlHost)) {
            $this->ok('Base URL', 'Application base URL is set to ' . $baseURL . '.');
            $passes++;
        } else {
            $this->warn('Base URL', 'Application base URL still points to a local, private, or empty address: ' . ($baseURL !== '' ? $baseURL : '(empty)') . '.');
            $warnings++;
        }

        if ($baseUrlScheme === 'https') {
            $this->ok('Base URL Scheme', 'Application base URL uses HTTPS.');
            $passes++;
        } else {
            $this->warn('Base URL Scheme', 'Application base URL should use HTTPS in production: ' . ($baseURL !== '' ? $baseURL : '(empty)') . '.');
            $warnings++;
        }

        if ($baseUrlPath === '' || $baseUrlPath === '/') {
            $this->ok('Base URL Path', 'Application base URL does not expose the /public path.');
            $passes++;
        } else {
            $this->warn('Base URL Path', 'Application base URL includes a path segment (' . $baseUrlPath . '). For cPanel shared hosting, the public web root should serve the app from the domain root.');
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

        $criticalStaticFiles = [
            'public/images/assets/placeholder_asset.png' => FCPATH . 'images/assets/placeholder_asset.png',
            'public/images/pic/placeholder_pic.png' => FCPATH . 'images/pic/placeholder_pic.png',
            'public/images/labs/placeholder_lab.png' => FCPATH . 'images/labs/placeholder_lab.png',
            'public/images/labs/placeholder_lab.jpg' => FCPATH . 'images/labs/placeholder_lab.jpg',
            'public/images/logo.png' => FCPATH . 'images/logo.png',
            'public/images/staff/haslina_placeholder.jpg' => FCPATH . 'images/staff/haslina_placeholder.jpg',
            'public/images/staff/asyarofah_placeholder.jpg' => FCPATH . 'images/staff/asyarofah_placeholder.jpg',
            'public/images/staff/azwan_placeholder.jpg' => FCPATH . 'images/staff/azwan_placeholder.jpg',
        ];

        foreach ($criticalStaticFiles as $label => $filePath) {
            if (! is_file($filePath)) {
                $this->warn($label, 'Required static media is missing from the repository or deployment artifact.');
                $warnings++;
                continue;
            }

            if (filesize($filePath) === 0) {
                $this->warn($label, 'Static media exists but is zero bytes, so browsers will render it as a broken asset.');
                $warnings++;
                continue;
            }

            $this->ok($label, 'Static media is present and non-empty.');
            $passes++;
        }

        $heroVideoPath = FCPATH . 'images/night-time-aerial-compressed.mp4';
        if (! is_file($heroVideoPath)) {
            $this->warn('Hero Video', 'public/images/night-time-aerial-compressed.mp4 is missing.');
            $warnings++;
        } elseif ($this->isGitLfsPointerFile($heroVideoPath)) {
            $this->warn('Hero Video', 'The deployed hero video is still a Git LFS pointer file instead of the real MP4 binary.');
            $warnings++;
        } elseif (filesize($heroVideoPath) > 100 * 1024 * 1024) {
            $this->warn('Hero Video', 'The hero video exceeds 100 MB and depends on Git LFS or a separate media sync step during deployment.');
            $warnings++;
        } else {
            $this->ok('Hero Video', 'Hero video is present as a normal media file.');
            $passes++;
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

    private function isLocalDevelopmentHost(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (str_ends_with($host, '.test')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        return $this->isPrivateOrLoopbackIpv4($host);
    }

    private function isPrivateOrLoopbackIpv4(string $host): bool
    {
        if (
            str_starts_with($host, '10.')
            || str_starts_with($host, '127.')
            || str_starts_with($host, '169.254.')
            || str_starts_with($host, '192.168.')
        ) {
            return true;
        }

        if (! preg_match('/^172\.(\d{1,3})\./', $host, $matches)) {
            return false;
        }

        $secondOctet = (int) $matches[1];

        return $secondOctet >= 16 && $secondOctet <= 31;
    }

    private function isGitLfsPointerFile(string $filePath): bool
    {
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 256);
        fclose($handle);

        if ($sample === false) {
            return false;
        }

        return str_starts_with($sample, "version https://git-lfs.github.com/spec/v1\n");
    }
}
