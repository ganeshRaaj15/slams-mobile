<?php

namespace App\Libraries;

use App\Models\FacultyModel;

class ApprovalFlowResolver
{
    public const DIRECT_APPROVAL = 'FKMP_APPROVAL';
    public const TWO_STAGE_APPROVAL = 'FACULTY_APPROVAL';

    protected FacultyModel $facultyModel;

    public function __construct(?FacultyModel $facultyModel = null)
    {
        $this->facultyModel = $facultyModel ?? new FacultyModel();
    }

    public function resolveForFacultyId(int $facultyId): ?string
    {
        if ($facultyId <= 0) {
            return null;
        }

        $faculty = $this->facultyModel->find($facultyId);
        if (! is_array($faculty)) {
            return null;
        }

        return self::determineFlow($faculty, $this->configuredDirectApprovalFacultyId());
    }

    public static function determineFlow(array $faculty, int $configuredDirectApprovalFacultyId = 0): ?string
    {
        $facultyId = (int) ($faculty['id'] ?? 0);
        if ($facultyId <= 0) {
            return null;
        }

        $isDirectApprovalFaculty = (int) ($faculty['is_fkmp'] ?? 0) === 1
            || ($configuredDirectApprovalFacultyId > 0 && $facultyId === $configuredDirectApprovalFacultyId);

        return $isDirectApprovalFaculty ? self::DIRECT_APPROVAL : self::TWO_STAGE_APPROVAL;
    }

    protected function configuredDirectApprovalFacultyId(): int
    {
        foreach (['system.primary_faculty_id', 'system.fkmp_faculty_id'] as $settingKey) {
            try {
                $value = setting($settingKey);
            } catch (\Throwable $e) {
                $value = null;
            }

            $facultyId = (int) $value;
            if ($facultyId > 0) {
                return $facultyId;
            }
        }

        return 0;
    }
}
