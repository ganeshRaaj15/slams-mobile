<?php

namespace App\Libraries;

class BookingDocumentLocator
{
    /**
     * Resolve a booking PDF from the current writable storage first,
     * then fall back to the legacy public storage path.
     */
    public function resolvePdfPath(string $filename): ?string
    {
        $filename = basename($filename);
        if ($filename === '' || ! preg_match('/^[A-Za-z0-9._-]+\.pdf$/i', $filename)) {
            return null;
        }

        $candidates = [
            [WRITEPATH . 'uploads/pdfs', WRITEPATH . 'uploads/pdfs/' . $filename],
            [FCPATH . 'uploads/pdfs', FCPATH . 'uploads/pdfs/' . $filename],
        ];

        foreach ($candidates as [$basePath, $candidatePath]) {
            $base = realpath($basePath);
            $resolved = realpath($candidatePath);

            if (
                $base
                && $resolved
                && is_file($resolved)
                && str_starts_with($resolved, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            ) {
                return $resolved;
            }
        }

        return null;
    }
}
