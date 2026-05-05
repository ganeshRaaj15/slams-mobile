<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use App\Libraries\ApprovalFlowResolver;
use App\Libraries\NotificationService;
use App\Models\BookingModel;
use App\Models\BookingApplicantModel;
use App\Models\BookingAssetModel;
use App\Models\AssetModel;
use App\Models\LabServiceModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class BookingController extends BaseController
{
    /*
    |--------------------------------------------------------------------------
    | SLOT DEFINITIONS  (Will later be moved to settings table)
    |--------------------------------------------------------------------------
    */
    protected function getSlotDefinitions(): array
    {
        $slotsJson = setting('system.booking_slots') ?? '';
        $slots = [];

        if ($slotsJson) {
            $decoded = json_decode($slotsJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $slot) {
                    $start = isset($slot['start']) ? $this->normalizeTime($slot['start']) : '';
                    $end = isset($slot['end']) ? $this->normalizeTime($slot['end']) : '';
                    if ($start && $end && $start < $end) {
                        $label = $slot['label'] ?? ($start . ' - ' . $end);
                        $slots[] = [
                            'label' => $label,
                            'start' => $start,
                            'end' => $end,
                        ];
                    }
                }
            }
        }

        if (!empty($slots)) {
            usort($slots, function ($a, $b) {
                if ($a['start'] === $b['start']) {
                    return strcmp($a['end'], $b['end']);
                }
                return strcmp($a['start'], $b['start']);
            });
            return $slots;
        }

        return [
            ['label' => '08:00 - 10:00', 'start' => '08:00:00', 'end' => '10:00:00'],
            ['label' => '10:00 - 12:00', 'start' => '10:00:00', 'end' => '12:00:00'],
            ['label' => '13:00 - 15:00', 'start' => '13:00:00', 'end' => '15:00:00'],
            ['label' => '15:00 - 17:00', 'start' => '15:00:00', 'end' => '17:00:00'],
        ];
    }

    protected function normalizeTime(string $time): string
    {
        $time = trim($time);

        if ($time === '') return $time;
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) return $time;
        if (preg_match('/^\d{2}:\d{2}$/', $time)) return $time . ':00';

        return $time;
    }

    /*
    |--------------------------------------------------------------------------
    | ASSET STRING PARSER "1:2,5:1" => [1=>2,5=>1]
    |--------------------------------------------------------------------------
    */
    protected function parseAssetsString(?string $raw): array
    {
        $result = [];
        if (empty($raw)) return $result;

        foreach (explode(',', $raw) as $pair) {
            [$id, $qty] = array_pad(explode(':', trim($pair)), 2, null);
            $id  = (int) $id;
            $qty = (int) $qty;
            if ($id > 0 && $qty > 0) {
                $result[$id] = $qty;
            }
        }
        return $result;
    }

    protected function findLabService(int $labId, int $serviceId): ?array
    {
        if ($labId <= 0 || $serviceId <= 0) {
            return null;
        }

        return (new LabServiceModel())
            ->where('id', $serviceId)
            ->where('laboratory_id', $labId)
            ->where('is_active', 1)
            ->first();
    }

    protected function defaultServiceAssets(int $labId, int $serviceId): array
    {
        if ($this->findLabService($labId, $serviceId) === null) {
            return [];
        }

        $rows = (new AssetModel())
            ->select('id')
            ->where('lab_id', $labId)
            ->where('lab_service_id', $serviceId)
            ->orderBy('id', 'ASC')
            ->findAll();

        $selected = [];
        foreach ($rows as $row) {
            $selected[(int) $row['id']] = 1;
        }

        return $selected;
    }

    protected function resolveSelectedAssets(int $labId, int $serviceId, ?string $assetsRaw): array
    {
        $parsed = $this->parseAssetsString($assetsRaw);

        if ($serviceId <= 0) {
            return $parsed;
        }

        $serviceDefaults = $this->defaultServiceAssets($labId, $serviceId);
        if ($serviceDefaults === []) {
            return [];
        }

        if ($parsed === []) {
            return $serviceDefaults;
        }

        $allowedIds = array_fill_keys(array_keys($serviceDefaults), true);
        $filtered = array_intersect_key($parsed, $allowedIds);

        return $filtered === [] ? $serviceDefaults : $filtered;
    }

    /*
    |--------------------------------------------------------------------------
    | ASSET AVAILABILITY ENGINE
    |--------------------------------------------------------------------------
    */
    protected function computeRemainingForSlot(
        int $labId,
        string $date,
        string $start,
        string $end,
        array $selectedAssets
    ): array {

        if (empty($selectedAssets)) return [];

        $assetModel = new AssetModel();

        // Base quantities
        $assets = $assetModel->where('lab_id', $labId)
                             ->whereIn('id', array_keys($selectedAssets))
                             ->findAll();

        $base = [];
        foreach ($assets as $asset) {
            $base[(int)$asset['id']] = (int)$asset['quantity'];
        }

        // Ensure all selected assets have base quantity
        foreach ($selectedAssets as $id => $val) {
            if (!isset($base[$id])) $base[$id] = 0;
        }

        $db = \Config\Database::connect();

        $start = $this->normalizeTime($start);
        $end   = $this->normalizeTime($end);

        // Get used quantities for overlapping bookings
        $rows = $db->table('booking_assets ba')
            ->select('ba.asset_id, SUM(ba.quantity_used) AS used_qty')
            ->join('bookings b', 'b.id = ba.booking_id')
            ->where('b.lab_id', $labId)
            ->where('b.date', $date)
            ->whereIn('b.status', ['PENDING', 'APPROVED'])
            ->where('b.start_time <', $end)   // overlap rules
            ->where('b.end_time >', $start)
            ->whereIn('ba.asset_id', array_keys($selectedAssets))
            ->groupBy('ba.asset_id')
            ->get()->getResultArray();

        $used = [];
        foreach ($rows as $r) {
            $used[(int)$r['asset_id']] = (int)$r['used_qty'];
        }

        // Compute remaining
        $remaining = [];
        foreach ($selectedAssets as $assetId => $need) {
            $remaining[$assetId] = max(($base[$assetId] ?? 0) - ($used[$assetId] ?? 0), 0);
        }

        return $remaining;
    }

    /*
    |--------------------------------------------------------------------------
    | MONTH VIEW - FULLCALENDAR (ASSET-AWARE)
    |--------------------------------------------------------------------------
    */
    public function calendarWithAssets(int $labId): ResponseInterface
    {
        $serviceId = (int) $this->request->getGet('service_id');
        $selected = $this->resolveSelectedAssets($labId, $serviceId, (string) $this->request->getGet('assets'));

        return $this->response->setJSON([
            'unavailableDates' => $this->calendarAssetsInternal($labId, $selected)
        ]);
    }

    protected function calendarAssetsInternal(int $labId, array $selected): array
    {
        if (empty($selected)) return [];

        $db = \Config\Database::connect();
        $bookingModel = new BookingModel();
        $today = date('Y-m-d');
        $slotDefs = $this->getSlotDefinitions();

        $dates = $db->table('bookings')
            ->select('DISTINCT date')
            ->where('lab_id', $labId)
            ->where('date >=', $today)
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->get()->getResultArray();

        $dates = array_column($dates, 'date');
        $unavailable = [];

        foreach ($dates as $date) {

            $hasValidSlot = false;

            foreach ($slotDefs as $slot) {
                if ($bookingModel->hasLabConflict($labId, $date, $slot['start'], $slot['end'])) {
                    continue;
                }

                $remaining = $this->computeRemainingForSlot(
                    $labId, $date, $slot['start'], $slot['end'], $selected
                );

                $slotOK = true;

                foreach ($selected as $id => $need) {
                    if (($remaining[$id] ?? 0) < $need) {
                        $slotOK = false;
                        break;
                    }
                }

                if ($slotOK) {
                    $hasValidSlot = true;
                    break;
                }
            }

            if (! $hasValidSlot) {
                $unavailable[] = $date;
            }
        }

        return $unavailable;
    }

    /*
    |--------------------------------------------------------------------------
    | DAY VIEW - TIMESLOT DETAIL (ASSET-AWARE)
    |--------------------------------------------------------------------------
    */
    public function dayWithAssets(int $labId, string $date): ResponseInterface
    {
        $serviceId = (int) $this->request->getGet('service_id');
        $selected = $this->resolveSelectedAssets($labId, $serviceId, (string) $this->request->getGet('assets'));

        return $this->response->setJSON([
            'slots' => $this->dayAssetsInternal($labId, $date, $selected)
        ]);
    }

    protected function dayAssetsInternal(int $labId, string $date, array $selected): array
    {
        if (empty($selected)) return [];

        $slotDefs = $this->getSlotDefinitions();
        $assetModel = new AssetModel();
        $bookingModel = new BookingModel();

        $assets = $assetModel->where('lab_id', $labId)
                             ->whereIn('id', array_keys($selected))
                             ->findAll();

        $assetNames = [];
        foreach ($assets as $a) {
            $assetNames[(int)$a['id']] = $a['name'];
        }

        $slots = [];
        $today = date('Y-m-d');
        $now   = date('H:i:s');
        foreach ($slotDefs as $slot) {

            $remaining = $this->computeRemainingForSlot(
                $labId, $date, $slot['start'], $slot['end'], $selected
            );

            $assetsInfo = [];
            $slotOK = true;
            $reason = null;

            if ($bookingModel->hasLabConflict($labId, $date, $slot['start'], $slot['end'])) {
                $slotOK = false;
                $reason = 'Laboratory already booked for this slot.';
            }

            foreach ($selected as $assetId => $need) {
                $rem = $remaining[$assetId] ?? 0;

                $assetsInfo[] = [
                    'id'        => $assetId,
                    'name'      => $assetNames[$assetId] ?? "Asset #$assetId",
                    'requested' => $need,
                    'remaining' => $rem,
                ];

                if ($rem < $need) $slotOK = false;
            }

            if ($date < $today || ($date === $today && $slot['end'] <= $now)) {
                $slotOK = false;
                $reason = 'Slot is already in the past.';
            }

            $slots[] = [
                'label'    => $slot['label'],
                'start'    => substr($slot['start'], 0, 5),
                'end'      => substr($slot['end'], 0, 5),
                'can_book' => $slotOK,
                'reason'   => $reason,
                'assets'   => $assetsInfo,
            ];
        }

        return $slots;
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX CHECK SLOT
    |--------------------------------------------------------------------------
    */
    public function checkSlot(): ResponseInterface
    {
        $labId     = (int) $this->request->getPost('lab_id');
        $serviceId = (int) $this->request->getPost('service_id');
        $date      = $this->request->getPost('date');
        $startTime = $this->request->getPost('start_time');
        $endTime   = $this->request->getPost('end_time');
        $assetsRaw = $this->request->getPost('asset_selection');

        if ($serviceId <= 0 || $this->findLabService($labId, $serviceId) === null) {
            return $this->response->setJSON([
                'conflict' => true,
                'reason' => 'Please choose a valid laboratory service before checking availability.'
            ]);
        }

        $selected = $this->resolveSelectedAssets($labId, $serviceId, is_string($assetsRaw) ? $assetsRaw : null);

        if (!$labId || !$date || !$startTime || !$endTime || empty($selected)) {
            return $this->response->setJSON([
                'conflict' => true,
                'reason' => 'Missing data or no linked equipment is available for the selected service.'
            ]);
        }

        $start = $this->normalizeTime($startTime);
        $end   = $this->normalizeTime($endTime);
        if ($start >= $end) {
            return $this->response->setJSON([
                'conflict' => true,
                'reason'   => 'End time must be later than start time.'
            ]);
        }

        $today = date('Y-m-d');
        $now   = date('H:i:s');

        if ($date < $today || ($date === $today && $end <= $now)) {
            return $this->response->setJSON([
                'conflict' => true,
                'reason'   => 'Slot is already in the past.'
            ]);
        }

        $bookingModel = new BookingModel();
        if ($bookingModel->hasLabConflict($labId, $date, $start, $end)) {
            return $this->response->setJSON([
                'conflict' => true,
                'reason'   => 'This laboratory already has an active booking for that time.'
            ]);
        }

        $remaining = $this->computeRemainingForSlot(
            $labId, $date, $start, $end, $selected
        );

        foreach ($selected as $id => $need) {
            if (($remaining[$id] ?? 0) < $need) {
                return $this->response->setJSON(['conflict' => true]);
            }
        }

        return $this->response->setJSON(['conflict' => false]);
    }

    /*
    |--------------------------------------------------------------------------
    | BOOKING SUBMISSION (UTHM ONLY)
    |--------------------------------------------------------------------------
    */
    public function submit(): ResponseInterface
    {
        $rules = [
            'lab_id'     => 'required|integer',
            'service_id' => 'required|integer',
            'date'       => 'required|valid_date[Y-m-d]',
            'start_time' => 'required',
            'end_time'   => 'required',
            'activity'   => 'required|string',
            'pdf'        => 'uploaded[pdf]|mime_in[pdf,application/pdf]|max_size[pdf,8192]',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        helper('auth');

        if (! auth()->loggedIn()) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'You must log in to submit a booking.'
            ]);
        }

        $user = auth()->user();

        if ($user->inGroup('external')) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'External users cannot submit bookings directly. Please use the lab access request flow instead.'
            ]);
        }

        $userType = 'UTHM';
        $labId = (int) $this->request->getPost('lab_id');
        $serviceId = (int) $this->request->getPost('service_id');
        $date = (string) $this->request->getPost('date');
        $startTime = $this->normalizeTime((string) $this->request->getPost('start_time'));
        $endTime = $this->normalizeTime((string) $this->request->getPost('end_time'));
        $activity = trim((string) $this->request->getPost('activity'));
        $supervisorName = trim((string) $this->request->getPost('supervisor_name'));
        $supervisorEmail = trim((string) $this->request->getPost('supervisor_email'));
        $supervisorPhone = trim((string) $this->request->getPost('supervisor_phone'));

        $service = $this->findLabService($labId, $serviceId);
        if ($service === null) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Please choose a valid service for this laboratory.'
            ]);
        }

        if ($startTime >= $endTime) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'End time must be later than start time.'
            ]);
        }

        $names = $this->request->getPost('applicant_name') ?? [];
        $ids = $this->request->getPost('applicant_id') ?? [];
        $emails = $this->request->getPost('applicant_email') ?? [];
        $phones = $this->request->getPost('applicant_phone') ?? [];
        $faculties = $this->request->getPost('applicant_faculty') ?? [];

        $rowCount = max(count((array) $names), count((array) $ids), count((array) $emails), count((array) $phones), count((array) $faculties));
        $applicants = [];

        for ($i = 0; $i < $rowCount; $i++) {
            $name = trim((string) ($names[$i] ?? ''));
            $matricId = trim((string) ($ids[$i] ?? ''));
            $email = trim((string) ($emails[$i] ?? ''));
            $phone = trim((string) ($phones[$i] ?? ''));
            $facultyValue = trim((string) ($faculties[$i] ?? ''));

            if ($name === '' && $matricId === '' && $email === '' && $phone === '' && $facultyValue === '') {
                continue;
            }

            if ($name === '' || $matricId === '' || $email === '' || $phone === '' || $facultyValue === '') {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Each applicant must include name, ID, email, phone, and faculty.'
                ]);
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'One or more applicant email addresses are invalid.'
                ]);
            }

            $applicants[] = [
                'name' => $name,
                'matric_id' => $matricId,
                'email' => $email,
                'phone' => $phone,
                'faculty' => $facultyValue,
            ];
        }

        if ($applicants === []) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Please add at least one applicant before submitting.'
            ]);
        }

        $facultyId = (int) $applicants[0]['faculty'];
        if ($facultyId <= 0) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Please select a valid faculty for the primary applicant.'
            ]);
        }

        $approvalFlow = (new ApprovalFlowResolver())->resolveForFacultyId($facultyId);
        if ($approvalFlow === null) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'The selected faculty is not recognized. Please refresh and try again.'
            ]);
        }
        $selectedAssets = $this->resolveSelectedAssets(
            $labId,
            $serviceId,
            (string) $this->request->getPost('asset_selection')
        );

        if (empty($selectedAssets)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'No linked equipment is available for the selected service.'
            ]);
        }

        $bookingModel = new BookingModel();
        if ($bookingModel->hasLabConflict($labId, $date, $startTime, $endTime)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'This laboratory is already booked for the selected date and time.'
            ]);
        }

        $remaining = $this->computeRemainingForSlot($labId, $date, $startTime, $endTime, $selectedAssets);

        foreach ($selectedAssets as $assetId => $qtyNeeded) {
            if (($remaining[$assetId] ?? 0) < $qtyNeeded) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Selected assets are not available for that slot.'
                ]);
            }
        }

        $pdfFile = $this->request->getFile('pdf');
        $pdfPath = null;
        $storedPdfName = null;

        $bookingApplicantModel = new BookingApplicantModel();
        $bookingAssetModel = new BookingAssetModel();
        $db = \Config\Database::connect();

        $db->transBegin();

        try {
            $conflicts = $bookingModel->activeLabConflictsForUpdate($labId, $date, $startTime, $endTime);
            if ($conflicts !== []) {
                throw new \DomainException('This laboratory slot was just taken. Please choose another time.');
            }

            $remaining = $this->computeRemainingForSlot($labId, $date, $startTime, $endTime, $selectedAssets);
            foreach ($selectedAssets as $assetId => $qtyNeeded) {
                if (($remaining[$assetId] ?? 0) < $qtyNeeded) {
                    throw new \DomainException('Selected assets are no longer available for that slot.');
                }
            }

            if ($pdfFile && $pdfFile->isValid() && ! $pdfFile->hasMoved()) {
                $uploadDir = WRITEPATH . 'uploads/pdfs';
                if (! is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $storedPdfName = $pdfFile->getRandomName();
                if (! $pdfFile->move($uploadDir, $storedPdfName)) {
                    throw new \RuntimeException('Unable to store uploaded PDF.');
                }
                $pdfPath = '/uploads/pdfs/' . $storedPdfName;
            }

            $bookingId = $bookingModel->insert([
                'user_id'          => auth()->id(),
                'lab_id'           => $labId,
                'service_id'       => $serviceId,
                'user_type'        => $userType,
                'faculty_id'       => $facultyId,
                'approval_flow'    => $approvalFlow,
                'date'             => $date,
                'start_time'       => $startTime,
                'end_time'         => $endTime,
                'activity'         => $activity,
                'supervisor_name'  => $supervisorName ?: null,
                'supervisor_email' => $supervisorEmail ?: null,
                'supervisor_phone' => $supervisorPhone ?: null,
                'pdf_path'         => $pdfPath,
                'status'           => 'PENDING',
            ], true);

            foreach ($selectedAssets as $assetId => $qty) {
                $bookingAssetModel->insert([
                    'booking_id'    => $bookingId,
                    'asset_id'      => $assetId,
                    'quantity_used' => $qty,
                ]);
            }

            $bookingApplicantModel->insertBatchApplicants($bookingId, $applicants);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to save booking data.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($storedPdfName && is_file(WRITEPATH . 'uploads/pdfs/' . $storedPdfName)) {
                @unlink(WRITEPATH . 'uploads/pdfs/' . $storedPdfName);
            }
            log_message('error', 'Booking submission failed: ' . $e->getMessage());

            return $this->response->setJSON([
                'status'  => 'error',
                'message' => $e instanceof \DomainException
                    ? $e->getMessage()
                    : 'Failed to save booking. Please try again.'
            ]);
        }

        NotificationService::dispatchSafely(
            fn(NotificationService $notifications) => $notifications->notifyBookingSubmitted($bookingModel->find($bookingId) ?: []),
            'booking submitted'
        );

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Booking submitted successfully and is pending approval.',
        ]);
    }
}


