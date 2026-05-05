<?php

namespace App\Libraries;

use CodeIgniter\Shield\Entities\User;

class StudentRoleService
{
    public const DEFAULT_STUDENT_EMAIL_DOMAIN = '@student.uthm.edu.my';

    public function resolveStudentEmailDomain(?string $configuredDomain = null): string
    {
        $domain = $configuredDomain;

        if ($domain === null) {
            $domain = setting('system.student_email_domain');
            $domain = is_string($domain) ? $domain : '';
        }

        $normalized = $this->normalizeDomain($domain);

        return $normalized !== '' ? $normalized : self::DEFAULT_STUDENT_EMAIL_DOMAIN;
    }

    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return '';
        }

        return '@' . ltrim($domain, '@');
    }

    public function emailMatchesStudentDomain(?string $email, ?string $domain = null): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return false;
        }

        return str_ends_with($email, $this->resolveStudentEmailDomain($domain));
    }

    public function syncStudentAccess(User $user): bool
    {
        $email = strtolower(trim((string) $user->email));

        if (! $this->emailMatchesStudentDomain($email)) {
            return false;
        }

        $currentGroups = array_values(array_unique(array_map(
            static fn(string $group): string => strtolower($group),
            $user->getGroups() ?? []
        )));

        $targetGroups = array_values(array_filter(
            $currentGroups,
            static fn(string $group): bool => $group !== 'external'
        ));

        if (! in_array('student', $targetGroups, true)) {
            $targetGroups[] = 'student';
        }

        $currentComparison = $currentGroups;
        $targetComparison = $targetGroups;
        sort($currentComparison);
        sort($targetComparison);

        if ($currentComparison === $targetComparison) {
            return false;
        }

        $user->syncGroups(...$targetGroups);

        return true;
    }
}
