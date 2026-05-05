<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class ResetPrototypeCatalog extends BaseCommand
{
    protected $group = 'SLAMS';
    protected $name = 'slams:reset-prototype-catalog';
    protected $description = 'Replace demo catalog data with the staged Excel laboratories, services, and prototype assets.';

    private const PIC_PASSWORD = 'Pic@12345';

    public function run(array $params)
    {
        $projectRoot = dirname(__DIR__, 2);
        $stagingPath = $projectRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'excel_row_staging.csv';

        if (! is_file($stagingPath)) {
            CLI::error('Missing staging file: ' . $stagingPath);
            CLI::write('Run `python scripts/extract_excel_catalog.py` first.', 'yellow');
            return;
        }

        $rows = $this->readCsv($stagingPath);
        if ($rows === []) {
            CLI::error('The staging file is empty. Regenerate it before running this command.');
            return;
        }

        $db = Database::connect();
        $this->assertTableExists($db, 'lab_services');
        $this->assertTableExists($db, 'service_equipment_models');

        $picMappings = $this->buildPicMappings($rows);

        CLI::write('Resetting prototype catalog and transaction data...', 'yellow');
        $this->resetPrototypeData($db);

        $db->transStart();

        $picUsers = $this->ensurePicUsers($db, $picMappings);
        $labIds = $this->insertLaboratories($db, $rows, $picUsers);
        [$serviceCount, $equipmentRowCount, $assetCount] = $this->insertServicesAndAssets($db, $rows, $labIds);

        $db->transComplete();

        if (! $db->transStatus()) {
            CLI::error('The catalog import failed. The command can be rerun after fixing the problem.');
            return;
        }

        $mappingPath = $this->writePlaceholderMapping($projectRoot, $picMappings);
        $this->removeStaleMaintenanceModel($projectRoot);

        CLI::write('Prototype catalog imported successfully.', 'green');
        CLI::write('Laboratories imported: ' . count($labIds), 'green');
        CLI::write('Services imported: ' . $serviceCount, 'green');
        CLI::write('Service equipment rows imported: ' . $equipmentRowCount, 'green');
        CLI::write('Assets imported: ' . $assetCount, 'green');
        CLI::write('PIC placeholder mapping saved to: ' . $mappingPath, 'green');
        CLI::write('Transactional tables were cleared for fresh prototype testing.', 'green');
    }

    private function assertTableExists($db, string $table): void
    {
        if (! $db->tableExists($table)) {
            throw new \RuntimeException("Required table is missing: {$table}. Run `php spark migrate` first.");
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open staging CSV: ' . $path);
        }

        $rows = [];
        $headers = null;

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(
                    static fn (?string $header): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
                    $data
                );
                continue;
            }

            if ($data === [null] || $data === false) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return array<int, array<string, string|int>>
     */
    private function buildPicMappings(array $rows): array
    {
        $mappings = [];
        $seen = [];
        $sequence = 1;

        foreach ($rows as $row) {
            $picName = trim((string) ($row['pic_name'] ?? ''));
            if ($picName === '' || isset($seen[$picName])) {
                continue;
            }

            $placeholder = sprintf('pic%02d', $sequence);
            $mappings[] = [
                'pic_name' => $picName,
                'placeholder_key' => $placeholder,
                'username' => $placeholder,
                'email' => $placeholder . '@uthm.edu.my',
            ];

            $seen[$picName] = true;
            $sequence++;
        }

        return $mappings;
    }

    private function resetPrototypeData($db): void
    {
        $tables = [
            'booking_assets',
            'booking_applicants',
            'bookings',
            'maintenance_logs',
            'maintenance_records',
            'notifications',
            'email_logs',
            'assets',
            'service_equipment_models',
            'lab_services',
            'laboratories',
        ];

        $db->disableForeignKeyChecks();

        foreach ($tables as $table) {
            if (! $db->tableExists($table)) {
                continue;
            }

            $db->simpleQuery('TRUNCATE TABLE `' . $table . '`');
        }

        $db->enableForeignKeyChecks();
    }

    /**
     * @param array<int, array<string, string|int>> $picMappings
     * @return array<string, array<string, string|int>>
     */
    private function ensurePicUsers($db, array $picMappings): array
    {
        $results = [];
        $now = date('Y-m-d H:i:s');

        foreach ($picMappings as $mapping) {
            $picName = (string) $mapping['pic_name'];
            $email = strtolower((string) $mapping['email']);
            $username = (string) $mapping['username'];

            $userId = $this->findUserIdByEmailOrUsername($db, $email, $username);

            if ($userId > 0) {
                $userPayload = [
                    'username' => $username,
                    'active' => 1,
                    'deleted_at' => null,
                    'updated_at' => $now,
                ];

                if ($db->fieldExists('full_name', 'users')) {
                    $userPayload['full_name'] = $picName;
                }

                $db->table('users')->where('id', $userId)->update($userPayload);
            } else {
                $userPayload = [
                    'username' => $username,
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($db->fieldExists('full_name', 'users')) {
                    $userPayload['full_name'] = $picName;
                }

                $db->table('users')->insert($userPayload);
                $userId = (int) $db->insertID();
            }

            $this->upsertIdentity($db, $userId, $email, self::PIC_PASSWORD, $now);
            $this->ensureUserGroup($db, $userId, 'pic', $now);

            $results[$picName] = [
                'user_id' => $userId,
                'email' => $email,
                'username' => $username,
            ];
        }

        return $results;
    }

    private function findUserIdByEmailOrUsername($db, string $email, string $username): int
    {
        $identity = $db->table('auth_identities')
            ->select('user_id')
            ->where('type', 'email_password')
            ->where('LOWER(secret) =', $email)
            ->get()
            ->getRowArray();

        if ($identity) {
            return (int) ($identity['user_id'] ?? 0);
        }

        $user = $db->table('users')
            ->select('id')
            ->where('LOWER(username) =', strtolower($username))
            ->get()
            ->getRowArray();

        return (int) ($user['id'] ?? 0);
    }

    private function upsertIdentity($db, int $userId, string $email, string $password, string $now): void
    {
        $identity = $db->table('auth_identities')
            ->select('id')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        $payload = [
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => $email,
            'secret2' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => $now,
        ];

        if ($identity) {
            $db->table('auth_identities')->where('id', $identity['id'])->update($payload);
            return;
        }

        $payload['created_at'] = $now;
        $db->table('auth_identities')->insert($payload);
    }

    private function ensureUserGroup($db, int $userId, string $groupName, string $now): void
    {
        if ($db->fieldExists('group', 'auth_groups_users')) {
            $existing = $db->table('auth_groups_users')
                ->where('user_id', $userId)
                ->where('group', $groupName)
                ->countAllResults();

            if ($existing === 0) {
                $db->table('auth_groups_users')->insert([
                    'user_id' => $userId,
                    'group' => $groupName,
                    'created_at' => $now,
                ]);
            }

            return;
        }

        if (! $db->fieldExists('group_id', 'auth_groups_users') || ! $db->tableExists('auth_groups')) {
            return;
        }

        $group = $db->table('auth_groups')
            ->select('id')
            ->where('name', $groupName)
            ->get()
            ->getRowArray();

        if (! $group) {
            return;
        }

        $existing = $db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group_id', $group['id'])
            ->countAllResults();

        if ($existing === 0) {
            $db->table('auth_groups_users')->insert([
                'user_id' => $userId,
                'group_id' => $group['id'],
            ]);
        }
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<string, array<string, string|int>> $picUsers
     * @return array<string, int>
     */
    private function insertLaboratories($db, array $rows, array $picUsers): array
    {
        $labs = [];
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $labName = trim((string) ($row['laboratory_name'] ?? ''));
            if ($labName === '' || isset($labs[$labName])) {
                continue;
            }

            $picName = trim((string) ($row['pic_name'] ?? ''));
            $picEmail = (string) ($picUsers[$picName]['email'] ?? '');

            $db->table('laboratories')->insert([
                'name' => $labName,
                'room' => null,
                'description' => 'Prototype catalog import from the FKMP laboratory workbook.',
                'capacity' => null,
                'availability_note' => 'Imported prototype data. Service selection will be added in the next workflow update.',
                'safety_note' => 'Refer to the listed service acceptance criteria and confirm operational readiness with the PIC.',
                'pic_name' => $picName,
                'pic_email' => $picEmail,
                'pic_phone' => null,
                'image' => null,
                'pic_image' => null,
            ]);

            $labs[$labName] = (int) $db->insertID();
        }

        return $labs;
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<string, int> $labIds
     * @return array{0:int,1:int,2:int}
     */
    private function insertServicesAndAssets($db, array $rows, array $labIds): array
    {
        $serviceCount = 0;
        $equipmentRowCount = 0;
        $assetCount = 0;
        $assetSequence = 1;
        $now = date('Y-m-d H:i:s');

        $currentServiceId = 0;
        $currentServiceName = '';
        $currentLabId = 0;
        $currentServiceCriteria = '';
        $currentServiceCalibration = 'unknown';
        $sortOrder = 1;

        foreach ($rows as $row) {
            $labName = trim((string) ($row['laboratory_name'] ?? ''));
            $labId = (int) ($labIds[$labName] ?? 0);

            if ($labId <= 0) {
                continue;
            }

            $rowType = trim((string) ($row['row_type'] ?? 'service'));
            $serviceName = trim((string) ($row['service_name'] ?? ''));
            $fieldName = trim((string) ($row['field_name'] ?? ''));
            $criteria = trim((string) ($row['acceptance_criteria'] ?? ''));
            $equipmentModel = trim((string) ($row['equipment_model'] ?? ''));
            $calibrationStatus = $this->normalizeCalibrationStatus((string) ($row['calibration_status'] ?? 'unknown'));
            $sourceRow = (int) ($row['source_row_no'] ?? 0);

            if ($rowType === 'service') {
                $db->table('lab_services')->insert([
                    'laboratory_id' => $labId,
                    'field_name' => $fieldName !== '' ? $fieldName : null,
                    'service_name' => $serviceName,
                    'acceptance_criteria' => $criteria !== '' ? $criteria : null,
                    'calibration_status' => $calibrationStatus,
                    'service_notes' => null,
                    'source_row_no' => $sourceRow > 0 ? $sourceRow : null,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $currentServiceId = (int) $db->insertID();
                $currentServiceName = $serviceName;
                $currentLabId = $labId;
                $currentServiceCriteria = $criteria;
                $currentServiceCalibration = $calibrationStatus;
                $sortOrder = 1;
                $serviceCount++;

                if ($criteria !== '' || $this->hasMeaningfulModel($equipmentModel)) {
                    $db->table('service_equipment_models')->insert([
                        'lab_service_id' => $currentServiceId,
                        'equipment_model' => $this->hasMeaningfulModel($equipmentModel) ? $equipmentModel : null,
                        'criteria_note' => $criteria !== '' ? $criteria : null,
                        'calibration_status' => $calibrationStatus,
                        'source_row_no' => $sourceRow > 0 ? $sourceRow : null,
                        'sort_order' => $sortOrder,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $equipmentRowCount++;
                    $sortOrder++;
                }

                $this->insertPrototypeAsset(
                    $db,
                    $currentServiceName,
                    $equipmentModel,
                    $fieldName,
                    $criteria,
                    $calibrationStatus,
                    $labId,
                    $currentServiceId,
                    $assetSequence,
                    $sourceRow,
                    $now
                );

                $assetCount++;
                $assetSequence++;
                continue;
            }

            if ($currentServiceId <= 0 || $currentLabId !== $labId) {
                continue;
            }

            if ($criteria !== '' || $this->hasMeaningfulModel($equipmentModel)) {
                $db->table('service_equipment_models')->insert([
                    'lab_service_id' => $currentServiceId,
                    'equipment_model' => $this->hasMeaningfulModel($equipmentModel) ? $equipmentModel : null,
                    'criteria_note' => $criteria !== '' ? $criteria : null,
                    'calibration_status' => $calibrationStatus,
                    'source_row_no' => $sourceRow > 0 ? $sourceRow : null,
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $equipmentRowCount++;
                $sortOrder++;
            }

            $currentServiceCriteria = $this->appendCriteria($currentServiceCriteria, $criteria);
            $currentServiceCalibration = $this->mergeCalibrationStatus($currentServiceCalibration, $calibrationStatus);

            $db->table('lab_services')
                ->where('id', $currentServiceId)
                ->update([
                    'acceptance_criteria' => $currentServiceCriteria !== '' ? $currentServiceCriteria : null,
                    'calibration_status' => $currentServiceCalibration,
                    'updated_at' => $now,
                ]);

            if ($this->hasMeaningfulModel($equipmentModel)) {
                $this->insertPrototypeAsset(
                    $db,
                    $currentServiceName,
                    $equipmentModel,
                    $fieldName,
                    $criteria,
                    $calibrationStatus,
                    $labId,
                    $currentServiceId,
                    $assetSequence,
                    $sourceRow,
                    $now
                );

                $assetCount++;
                $assetSequence++;
            }
        }

        return [$serviceCount, $equipmentRowCount, $assetCount];
    }

    private function insertPrototypeAsset(
        $db,
        string $serviceName,
        string $equipmentModel,
        string $fieldName,
        string $criteria,
        string $calibrationStatus,
        int $labId,
        int $labServiceId,
        int $assetSequence,
        int $sourceRow,
        string $now
    ): void {
        $name = $serviceName;
        if ($this->hasMeaningfulModel($equipmentModel)) {
            $name = $serviceName . ' - ' . $equipmentModel;
        }

        $db->table('assets')->insert([
            'lab_id' => $labId,
            'lab_service_id' => $labServiceId,
            'asset_code' => sprintf('AST-%04d', $assetSequence),
            'name' => $this->limitString($name, 255),
            'category' => $fieldName !== '' ? $fieldName : 'Imported Service',
            'brand' => null,
            'model' => $this->hasMeaningfulModel($equipmentModel) ? $this->limitString($equipmentModel, 100) : null,
            'serial_number' => null,
            'specifications' => $criteria !== '' ? $criteria : null,
            'status' => 'available',
            'location_note' => $this->buildLocationNote($calibrationStatus, $sourceRow),
            'purchase_date' => null,
            'quantity' => 1,
            'total_quantity' => 1,
            'image' => '/images/assets/placeholder_asset.png',
        ]);
    }

    private function hasMeaningfulModel(string $equipmentModel): bool
    {
        $trimmed = trim($equipmentModel);
        return $trimmed !== '' && $trimmed !== '-';
    }

    private function normalizeCalibrationStatus(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'valid' => 'valid',
            'expired' => 'expired',
            default => 'unknown',
        };
    }

    private function mergeCalibrationStatus(string $current, string $next): string
    {
        $current = $this->normalizeCalibrationStatus($current);
        $next = $this->normalizeCalibrationStatus($next);

        if ($current === 'expired' || $next === 'expired') {
            return 'expired';
        }

        if ($current === 'valid' || $next === 'valid') {
            return 'valid';
        }

        return 'unknown';
    }

    private function appendCriteria(string $existing, string $next): string
    {
        $next = trim($next);
        if ($next === '') {
            return $existing;
        }

        $parts = $existing === ''
            ? []
            : array_values(array_filter(array_map('trim', preg_split('/\s*\|\|\s*/', $existing) ?: [])));

        if (! in_array($next, $parts, true)) {
            $parts[] = $next;
        }

        return implode(' || ', $parts);
    }

    private function buildLocationNote(string $calibrationStatus, int $sourceRow): string
    {
        $label = ucfirst($this->normalizeCalibrationStatus($calibrationStatus));
        return 'Prototype catalog import. Calibration status: ' . $label . '. Source row: ' . $sourceRow . '.';
    }

    private function limitString(string $value, int $length): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
        }

        return strlen($value) > $length ? substr($value, 0, $length) : $value;
    }

    /**
     * @param array<int, array<string, string|int>> $picMappings
     */
    private function writePlaceholderMapping(string $projectRoot, array $picMappings): string
    {
        $path = $projectRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'pic_placeholder_mapping.csv';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to write PIC placeholder mapping: ' . $path);
        }

        fputcsv($handle, ['excel_pic_name', 'placeholder_username', 'placeholder_email']);

        foreach ($picMappings as $mapping) {
            fputcsv($handle, [
                (string) $mapping['pic_name'],
                (string) $mapping['username'],
                (string) $mapping['email'],
            ]);
        }

        fclose($handle);

        return $path;
    }

    private function removeStaleMaintenanceModel(string $projectRoot): void
    {
        $modelPath = $projectRoot . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'maintenance_predictor.json';

        if (is_file($modelPath)) {
            @unlink($modelPath);
        }
    }
}
