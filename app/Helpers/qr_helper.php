<?php

use CodeIgniter\HTTP\IncomingRequest;

if (! function_exists('qr_public_base_url')) {
    function qr_public_base_url(?IncomingRequest $request = null): string
    {
        $configured = trim((string) (env('app.qrBaseURL') ?: env('app_qrBaseURL') ?: ''));
        if ($configured !== '') {
            return rtrim($configured, '/') . '/';
        }

        $request ??= service('request');
        if ($request instanceof IncomingRequest) {
            $host = trim($request->getHeaderLine('Host'));
            if ($host !== '' && ! preg_match('/^(localhost|127(?:\.\d{1,3}){3}|::1)(:\d+)?$/i', $host)) {
                return $request->getUri()->getScheme() . '://' . $host . '/';
            }
        }

        return rtrim(base_url('/'), '/') . '/';
    }
}

if (! function_exists('qr_public_url')) {
    function qr_public_url(string $path = '', array $query = [], ?IncomingRequest $request = null): string
    {
        $url = qr_public_base_url($request) . ltrim($path, '/');

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }
}
