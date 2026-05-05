<?php

namespace App\Controllers\Api;

use App\Controllers\Dashboard\IssueReportController as WebIssueReportController;
use App\Libraries\NotificationService;
use CodeIgniter\Shield\Entities\User;

class NativeIssueReportController extends WebIssueReportController
{
    public function index()
    {
        $user = $this->reporterUser();
        if (! $user instanceof User) {
            return $this->unauthenticated();
        }

        $assets = $this->availableAssetsForReporter($user);
        $recentReports = $this->maintenanceModel->withRelations()
            ->where('maintenance_records.reported_by', $user->id)
            ->orderBy('maintenance_records.created_at', 'DESC')
            ->findAll(8);

        return $this->response->setJSON([
            'status' => 'success',
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'assets' => array_map(fn(array $asset): array => [
                'id' => (int) $asset['id'],
                'name' => (string) ($asset['name'] ?? ''),
                'asset_code' => (string) ($asset['asset_code'] ?? ''),
                'status' => (string) ($asset['status'] ?? ''),
                'quantity' => (int) ($asset['quantity'] ?? 0),
                'total_quantity' => (int) ($asset['total_quantity'] ?? 0),
                'lab_name' => (string) ($asset['lab_name'] ?? ''),
                'lab_room' => (string) ($asset['lab_room'] ?? ''),
                'requires_unit_reference' => (int) ($asset['total_quantity'] ?? 0) > 1,
            ], $assets),
            'recent_reports' => array_map(function (array $report): array {
                return [
                    'id' => (int) $report['id'],
                    'asset_id' => (int) ($report['asset_id'] ?? 0),
                    'asset_name' => (string) ($report['asset_name'] ?? ''),
                    'asset_code' => (string) ($report['asset_code'] ?? ''),
                    'lab_name' => (string) ($report['laboratory_name'] ?? ''),
                    'lab_room' => (string) ($report['laboratory_room'] ?? ''),
                    'title' => (string) ($report['title'] ?? ''),
                    'priority' => (string) ($report['priority'] ?? ''),
                    'status' => (string) ($report['status'] ?? ''),
                    'status_label' => $this->maintenanceModel->statusLabel((string) ($report['status'] ?? '')),
                    'quantity_affected' => (int) ($report['quantity_affected'] ?? 0),
                    'unit_reference' => (string) ($report['unit_reference'] ?? ''),
                    'created_at' => (string) ($report['created_at'] ?? ''),
                    'updated_at' => (string) ($report['updated_at'] ?? ''),
                    'report_photo_url' => $this->mediaUrl((string) ($report['report_photo_path'] ?? '')),
                ];
            }, $recentReports),
        ]);
    }

    public function store()
    {
        $user = $this->reporterUser();
        if (! $user instanceof User) {
            return $this->unauthenticated();
        }

        $rules = [
            'asset_id' => 'required|integer',
            'quantity_affected' => 'required|integer|greater_than[0]',
            'title' => 'required|min_length[3]|max_length[255]',
            'priority' => 'required|in_list[low,medium,high,critical]',
            'description' => 'required|min_length[10]',
            'unit_reference' => 'permit_empty|max_length[120]',
            'report_photo' => 'permit_empty|max_size[report_photo,4096]|is_image[report_photo]|mime_in[report_photo,image/jpg,image/jpeg,image/png,image/webp]',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError();
        }

        $assetId = (int) $this->request->getPost('asset_id');
        $quantityAffected = (int) $this->request->getPost('quantity_affected');
        $unitReference = trim((string) $this->request->getPost('unit_reference'));
        $asset = $this->assetModel->find($assetId);
        if (! $asset) {
            return $this->unprocessable('Selected asset was not found.');
        }

        $allowedAsset = null;
        foreach ($this->availableAssetsForReporter($user) as $option) {
            if ((int) $option['id'] === $assetId) {
                $allowedAsset = $option;
                break;
            }
        }

        if (! $allowedAsset) {
            return $this->forbidden('You are not allowed to report issues for this asset.');
        }

        $availableUnits = max((int) ($asset['quantity'] ?? 0), 0);
        $totalUnits = max((int) ($asset['total_quantity'] ?? 0), $availableUnits);
        if ($availableUnits < 1) {
            return $this->unprocessable('This asset has no available units left to report. The technician is already handling the full stock.');
        }
        if ($quantityAffected > $availableUnits) {
            return $this->unprocessable('The affected quantity cannot exceed the currently available units for this asset.');
        }
        if ($totalUnits > 1 && $unitReference === '') {
            return $this->unprocessable('Please specify the workstation, unit code, seat number, or physical label for multi-unit equipment.');
        }

        $reportPhotoPath = $this->handlePhotoUpload('report_photo');

        $db = \Config\Database::connect();
        $db->transStart();

        $maintenanceId = $this->maintenanceModel->insert([
            'asset_id' => $assetId,
            'quantity_affected' => $quantityAffected,
            'unit_reference' => $unitReference !== '' ? $unitReference : null,
            'reported_by' => $user->id,
            'assigned_technician_id' => null,
            'title' => trim((string) $this->request->getPost('title')),
            'issue_type' => 'corrective',
            'priority' => trim((string) $this->request->getPost('priority')),
            'description' => trim((string) $this->request->getPost('description')),
            'report_photo_path' => $reportPhotoPath,
            'status' => 'reported',
            'asset_status_before' => $asset['status'],
            'asset_status_after' => null,
            'scheduled_for' => null,
            'accepted_at' => null,
            'diagnosis_notes' => null,
            'started_at' => null,
            'work_notes' => null,
            'tested_at' => null,
            'test_notes' => null,
            'completed_at' => null,
            'resolution_notes' => null,
            'completion_photo_path' => null,
        ], true);

        $logText = 'Issue reported by ' . ($user->full_name ?: $user->username) . ' for ' . $quantityAffected . ' unit(s).';
        if ($unitReference !== '') {
            $logText .= ' Unit reference: ' . $unitReference . '.';
        }

        $this->logModel->insert([
            'maintenance_id' => $maintenanceId,
            'changed_by' => $user->id,
            'from_status' => null,
            'to_status' => 'reported',
            'notes' => $logText,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assetModel->syncManagedAvailability($assetId);
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->unprocessable('Unable to submit the issue report right now.');
        }

        NotificationService::dispatchSafely(
            fn(NotificationService $notifications) => $notifications->notifyMaintenanceReported((int) $maintenanceId),
            'maintenance reported'
        );

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Issue report submitted successfully. The technician can now review it.',
            'report_id' => (int) $maintenanceId,
        ]);
    }

    private function reporterUser(): ?User
    {
        helper('auth');
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        if (! $user->inGroup('student') && ! $user->inGroup('staff') && ! $user->inGroup('pic')) {
            return null;
        }

        return $user;
    }

    private function mediaUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $scheme = $this->request->getUri()->getScheme();
        $host = $this->request->getHeaderLine('Host');

        return rtrim($scheme . '://' . $host, '/') . '/' . ltrim($path, '/');
    }

    private function validationError()
    {
        return $this->response
            ->setStatusCode(422)
            ->setJSON([
                'status' => 'error',
                'message' => implode(' ', $this->validator->getErrors()),
                'errors' => $this->validator->getErrors(),
            ]);
    }

    private function unauthenticated()
    {
        return $this->response
            ->setStatusCode(401)
            ->setJSON([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }

    private function forbidden(string $message)
    {
        return $this->response
            ->setStatusCode(403)
            ->setJSON([
                'status' => 'error',
                'message' => $message,
            ]);
    }

    private function unprocessable(string $message)
    {
        return $this->response
            ->setStatusCode(422)
            ->setJSON([
                'status' => 'error',
                'message' => $message,
            ]);
    }
}
