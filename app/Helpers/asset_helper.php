<?php

if (! function_exists('slams_asset')) {
    /**
     * Builds a public asset URL with a filemtime version so CSS/JS changes are
     * visible immediately instead of being hidden by browser or SW caches.
     */
    function slams_asset(string $path): string
    {
        $path = ltrim($path, '/');
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        $basePath = in_array($scriptDir, ['', '.', '/'], true) ? '' : rtrim($scriptDir, '/');
        $url = $basePath !== '' ? $basePath . '/' . $path : '/' . $path;

        if ($scriptName === '') {
            $url = base_url($path);
        }

        $file = FCPATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (! is_file($file)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . filemtime($file);
    }
}
