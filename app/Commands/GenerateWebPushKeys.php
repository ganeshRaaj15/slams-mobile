<?php

namespace App\Commands;

use App\Libraries\OpenSslBootstrap;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Minishlink\WebPush\VAPID;

class GenerateWebPushKeys extends BaseCommand
{
    protected $group = 'SLAMS';
    protected $name = 'slams:generate-web-push-keys';
    protected $description = 'Generate VAPID keys for browser push notifications.';
    protected $usage = 'slams:generate-web-push-keys [subject]';

    public function run(array $params)
    {
        $subject = trim((string) ($params[0] ?? 'mailto:lab-admin@example.com'));
        OpenSslBootstrap::ensureConfig();

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $keys = $this->generateWithOpenSslCli();
            if ($keys === null) {
                CLI::error('Unable to generate VAPID keys: ' . $e->getMessage());
                return;
            }
        }

        CLI::write('Add these lines to your `.env` file:', 'yellow');
        CLI::newLine();
        CLI::write('webpush.vapidSubject = "' . $subject . '"', 'green');
        CLI::write('webpush.vapidPublicKey = "' . $keys['publicKey'] . '"', 'green');
        CLI::write('webpush.vapidPrivateKey = "' . $keys['privateKey'] . '"', 'green');
        CLI::newLine();
        CLI::write('Restart the app server after updating `.env`, then enable push from the app shell on a signed-in device.', 'yellow');
    }

    private function generateWithOpenSslCli(): ?array
    {
        $openssl = $this->findOpenSslBinary();
        if ($openssl === null) {
            return null;
        }

        $pemPath = tempnam(WRITEPATH, 'vapid_');
        if ($pemPath === false) {
            return null;
        }

        try {
            $genCommand = '"' . $openssl . '" ecparam -name prime256v1 -genkey -noout -out "' . $pemPath . '"' . $this->stderrRedirect();
            exec($genCommand, $genOutput, $genCode);
            if ($genCode !== 0 || ! is_file($pemPath)) {
                return null;
            }

            $inspectCommand = '"' . $openssl . '" ec -in "' . $pemPath . '" -text -noout' . $this->stderrRedirect();
            exec($inspectCommand, $inspectOutput, $inspectCode);
            if ($inspectCode !== 0) {
                return null;
            }

            $text = implode("\n", $inspectOutput);
            $privateHex = $this->matchHexBlock($text, '/priv:\s*((?:\s*[0-9a-f]{2}:?)+)\s+pub:/is');
            $publicHex = $this->matchHexBlock($text, '/pub:\s*((?:\s*[0-9a-f]{2}:?)+)\s+ASN1 OID:/is');

            if ($privateHex === '' || $publicHex === '') {
                return null;
            }

            $privateKey = hex2bin($privateHex);
            $publicKey = hex2bin($publicHex);
            if ($privateKey === false || $publicKey === false) {
                return null;
            }

            return [
                'publicKey' => $this->base64UrlEncode($publicKey),
                'privateKey' => $this->base64UrlEncode($privateKey),
            ];
        } finally {
            if (is_file($pemPath)) {
                @unlink($pemPath);
            }
        }
    }

    private function findOpenSslBinary(): ?string
    {
        $candidates = [
            'C:\\laragon\\bin\\git\\mingw64\\bin\\openssl.exe',
            'C:\\laragon\\bin\\apache\\httpd-2.4.62-240904-win64-VS17\\bin\\openssl.exe',
            'C:\\laragon\\bin\\git\\usr\\bin\\openssl.exe',
        ];

        $path = getenv('PATH');
        if (is_string($path) && trim($path) !== '') {
            foreach (explode(PATH_SEPARATOR, $path) as $dir) {
                $dir = trim($dir);
                if ($dir === '') {
                    continue;
                }

                $candidates[] = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . (DIRECTORY_SEPARATOR === '\\' ? 'openssl.exe' : 'openssl');
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function matchHexBlock(string $text, string $pattern): string
    {
        if (! preg_match($pattern, $text, $matches)) {
            return '';
        }

        return strtolower(preg_replace('/[^0-9a-f]/i', '', $matches[1]) ?? '');
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function stderrRedirect(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null';
    }
}
