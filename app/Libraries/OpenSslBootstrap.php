<?php

namespace App\Libraries;

class OpenSslBootstrap
{
    public static function ensureConfig(): void
    {
        if (! extension_loaded('openssl')) {
            return;
        }

        $current = getenv('OPENSSL_CONF');
        if (is_string($current) && trim($current) !== '' && is_file($current)) {
            return;
        }

        $default = ini_get('openssl.default_config');
        if (is_string($default) && trim($default) !== '' && is_file($default)) {
            putenv('OPENSSL_CONF=' . $default);
            $_ENV['OPENSSL_CONF'] = $default;
            $_SERVER['OPENSSL_CONF'] = $default;
            return;
        }

        $binaryDir = dirname((string) PHP_BINARY);
        $candidates = [
            $binaryDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            dirname($binaryDir) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            'C:\\laragon\\bin\\php\\php-8.3.26-Win32-vs16-x64\\extras\\ssl\\openssl.cnf',
            'C:\\laragon\\bin\\apache\\httpd-2.4.62-240904-win64-VS17\\conf\\openssl.cnf',
            'C:\\laragon\\bin\\git\\mingw64\\etc\\ssl\\openssl.cnf',
            'C:\\Program Files\\Common Files\\SSL\\openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '' || ! is_file($candidate)) {
                continue;
            }

            putenv('OPENSSL_CONF=' . $candidate);
            $_ENV['OPENSSL_CONF'] = $candidate;
            $_SERVER['OPENSSL_CONF'] = $candidate;
            return;
        }
    }
}
