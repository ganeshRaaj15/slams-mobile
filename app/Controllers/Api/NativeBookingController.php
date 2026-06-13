<?php

namespace App\Controllers\Api;

use App\Controllers\Public\BookingController as WebBookingController;
use App\Libraries\ApprovalFlowResolver;
use App\Libraries\NotificationService;
use App\Models\BookingApplicantModel;
use App\Models\BookingAssetModel;
use App\Models\BookingModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

class NativeBookingController extends WebBookingController
{
    public function __construct()
    {
        parent::__construct();
        helper('auth');
    }

    public function index()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        $status = trim((string) $this->request->getGet('status'));
        if (! in_array($status, BookingModel::CORE_STATUSES, true)) {
            $status = '';
        }

        $query = (new BookingModel())
            ->select('bookings.*, laboratories.name AS lab_name, laboratories.room AS lab_room, lab_services.service_name')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->join('lab_services', 'lab_services.id = bookings.service_id', 'left')
            ->where('bookings.user_id', $user->id)
            ->whereIn('bookings.status', BookingModel::CORE_STATUSES);

        if ($status !== '') {
            $query->where('bookings.status', $status);
        }

        $bookings = $query
            ->orderBy('bookings.date', 'DESC')
            ->orderBy('bookings.start_time', 'ASC')
            ->findAll();

        $stats = [
            'pending' => (int) (new BookingModel())->where('user_id', $user->id)->where('status', 'PENDING')->countAllResults(),
            'approved' => (int) (new BookingModel())->where('user_id', $user->id)->where('status', 'APPROVED')->countAllResults(),
            'rejected' => (int) (new BookingModel())->where('user_id', $user->id)->where('status', 'REJECTED')->countAllResults(),
            'cancelled' => (int) (new BookingModel())->where('user_id', $user->id)->where('status', 'CANCELLED')->countAllResults(),
        ];
        $stats['total'] = $stats['pending'] + $stats['approved'] + $stats['rejected'] + $stats['cancelled'];

        return $this->response->setJSON([
            'status' => 'success',
            'stats' => $stats,
            'bookings' => array_map(fn(array $booking): array => $this->serializeBookingSummary($booking), $bookings),
        ]);
    }

    public function recommendedSlots(int $labId)
    {
        $serviceId = (int) $this->request->getGet('service_id');
        $selected = $this->resolveSelectedAssets($labId, $serviceId, (string) $this->request->getGet('assets'));

        if ($serviceId <= 0) {
            return $this->response->setJSON([
                'status' => 'success',
                'slots' => [],
            ]);
        }

        $results = [];
        $today = new \DateTimeImmutable('today');

        for ($i = 0; $i < 14 && count($results) < 3; $i++) {
            $date = $today->modify('+' . $i . ' day')->format('Y-m-d');
            $slots = $this->dayAssetsInternal($labId, $date, $selected);

            foreach ($slots as $slot) {
                if (! ($slot['can_book'] ?? false)) {
                    continue;
                }

                $results[] = [
                    'date' => $date,
                    'label' => (string) ($slot['label'] ?? ''),
                    'start' => (string) ($slot['start'] ?? ''),
                    'end' => (string) ($slot['end'] ?? ''),
                ];

                if (count($results) >= 3) {
                    break;
                }
            }
        }

        return $this->response->setJSON([
            'status' => 'success',
            'slots' => $results,
        ]);
    }

    public function show(int $id)
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        $booking = (new BookingModel())
            ->select('bookings.*, laboratories.name AS lab_name, laboratories.room AS lab_room, laboratories.pic_name, laboratories.pic_email, laboratories.pic_phone, lab_services.service_name')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->join('lab_services', 'lab_services.id = bookings.service_id', 'left')
            ->where('bookings.id', $id)
            ->where('bookings.user_id', $user->id)
            ->first();

        if (! $booking) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Booking not found.',
                ]);
        }

        $assets = (new BookingAssetModel())->getForBooking($id);
        $applicants = (new BookingApplicantModel())->getForBooking($id);

        return $this->response->setJSON([
            'status' => 'success',
            'booking' => array_merge(
                $this->serializeBookingSummary($booking),
                [
                    'supervisor_name' => (string) ($booking['supervisor_name'] ?? ''),
                    'supervisor_email' => (string) ($booking['supervisor_email'] ?? ''),
                    'supervisor_phone' => (string) ($booking['supervisor_phone'] ?? ''),
                    'approval_flow' => (string) ($booking['approval_flow'] ?? ''),
                    'pdf_path' => (string) ($booking['pdf_path'] ?? ''),
                    'document_url' => $this->documentUrl((string) ($booking['pdf_path'] ?? '')),
                    'assets' => array_map(function (array $asset): array {
                        return [
                            'id' => (int) $asset['asset_id'],
                            'name' => (string) ($asset['asset_name'] ?? ''),
                            'quantity_used' => (int) ($asset['quantity_used'] ?? 0),
                            'image' => (string) ($asset['asset_image'] ?? ''),
                            'image_url' => $this->mediaUrl('images/assets/' . ltrim((string) ($asset['asset_image'] ?? ''), '/')),
                        ];
                    }, $assets),
                    'applicants' => array_map(static function (array $applicant): array {
                        $facultyRaw = trim((string) ($applicant['faculty'] ?? ''));
                        return [
                            'id' => (int) $applicant['id'],
                            'name' => (string) ($applicant['name'] ?? ''),
                            'matric_id' => (string) ($applicant['matric_id'] ?? ''),
                            'email' => (string) ($applicant['email'] ?? ''),
                            'phone' => (string) ($applicant['phone'] ?? ''),
                            'faculty' => $facultyRaw,
                            'faculty_id' => ctype_digit($facultyRaw) ? (int) $facultyRaw : null,
                        ];
                    }, $applicants),
                ]
            ),
        ]);
    }

    public function cancel(int $id)
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        $bookingModel = new BookingModel();
        $booking = $bookingModel
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $booking) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Booking not found.',
                ]);
        }

        if (($booking['status'] ?? '') !== 'PENDING') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Only pending bookings can be cancelled.',
                ]);
        }

        $bookingModel->update($id, [
            'status' => 'CANCELLED',
            'approved_by_pic' => 0,
            'approved_by_manager' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Booking cancelled successfully.',
        ]);
    }

    protected function serializeBookingSummary(array $booking): array
    {
        return [
            'id' => (int) $booking['id'],
            'lab_id' => (int) ($booking['lab_id'] ?? 0),
            'service_id' => isset($booking['service_id']) ? (int) $booking['service_id'] : null,
            'lab_name' => (string) ($booking['lab_name'] ?? ''),
            'lab_room' => (string) ($booking['lab_room'] ?? ''),
            'service_name' => (string) ($booking['service_name'] ?? ''),
            'date' => (string) ($booking['date'] ?? ''),
            'start_time' => substr((string) ($booking['start_time'] ?? ''), 0, 5),
            'end_time' => substr((string) ($booking['end_time'] ?? ''), 0, 5),
            'activity' => (string) ($booking['activity'] ?? ''),
            'status' => (string) ($booking['status'] ?? ''),
            'approved_by_pic' => (bool) ($booking['approved_by_pic'] ?? false),
            'approved_by_manager' => (bool) ($booking['approved_by_manager'] ?? false),
            'created_at' => (string) ($booking['created_at'] ?? ''),
            'updated_at' => (string) ($booking['updated_at'] ?? ''),
            'can_cancel' => (string) ($booking['status'] ?? '') === 'PENDING',
            'can_edit' => (string) ($booking['status'] ?? '') === 'PENDING',
        ];
    }

    public function update(int $id): ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        $bookingModel = new BookingModel();
        $booking = $bookingModel
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $booking) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Booking not found.',
                ]);
        }

        if ((string) ($booking['status'] ?? '') !== 'PENDING') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Only pending bookings can be edited.',
                ]);
        }

        $rules = [
            'date' => 'required|valid_date[Y-m-d]',
            'start_time' => 'required',
            'end_time' => 'required',
            'activity' => 'required|string',
            'pdf' => 'if_exist|mime_in[pdf,application/pdf]|max_size[pdf,8192]',
        ];

        if (! $this->validate($rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Validation failed.',
                    'errors' => $this->validator->getErrors(),
                ]);
        }

        $labId = (int) ($booking['lab_id'] ?? 0);
        $serviceId = (int) ($booking['service_id'] ?? 0);
        $date = (string) $this->request->getPost('date');
        $startTime = $this->normalizeTime((string) $this->request->getPost('start_time'));
        $endTime = $this->normalizeTime((string) $this->request->getPost('end_time'));
        $activity = trim((string) $this->request->getPost('activity'));
        $supervisorName = trim((string) $this->request->getPost('supervisor_name'));
        $supervisorEmail = trim((string) $this->request->getPost('supervisor_email'));
        $supervisorPhone = trim((string) $this->request->getPost('supervisor_phone'));

        if ($this->findLabService($labId, $serviceId) === null) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'The original laboratory service is no longer available for this booking.',
                ]);
        }

        if ($startTime >= $endTime) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'End time must be later than start time.',
                ]);
        }

        if ($this->findMatchingSlotDefinition($startTime, $endTime) === null) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Please choose one of the configured booking sessions for this laboratory.',
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
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Each applicant must include name, ID, email, phone, and faculty.',
                    ]);
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'One or more applicant email addresses are invalid.',
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
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Please add at least one applicant before saving.',
                ]);
        }

        $facultyId = (int) $applicants[0]['faculty'];
        if ($facultyId <= 0) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Please select a valid faculty for the primary applicant.',
                ]);
        }

        $approvalFlow = (new ApprovalFlowResolver())->resolveForFacultyId($facultyId);
        if ($approvalFlow === null) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'The selected faculty is not recognized. Please refresh and try again.',
                ]);
        }

        $selectedAssets = [];
        foreach ((new BookingAssetModel())->getForBooking($id) as $asset) {
            $assetId = (int) ($asset['asset_id'] ?? 0);
            $quantityUsed = (int) ($asset['quantity_used'] ?? 0);
            if ($assetId > 0 && $quantityUsed > 0) {
                $selectedAssets[$assetId] = $quantityUsed;
            }
        }

        if ($selectedAssets === []) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'This booking no longer has any linked equipment. Please create a new booking instead.',
                ]);
        }

        if ($this->slotService->hasBlockingReservation($labId, $date, $startTime, $endTime)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'This laboratory is reserved for the selected date and time.',
                ]);
        }

        $remaining = $this->computeRemainingForSlot($labId, $date, $startTime, $endTime, $selectedAssets, $id);
        foreach ($selectedAssets as $assetId => $qtyNeeded) {
            if (($remaining[$assetId] ?? 0) < $qtyNeeded) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Selected assets are not available for that slot.',
                    ]);
            }
        }

        $pdfFile = $this->request->getFile('pdf');
        $oldPdfPath = (string) ($booking['pdf_path'] ?? '');
        $newPdfPath = $oldPdfPath;
        $storedPdfName = null;
        $deleteOldPdf = false;

        $bookingApplicantModel = new BookingApplicantModel();
        $db = \Config\Database::connect();

        $db->transBegin();

        try {
            if ($this->slotService->hasBlockingReservation($labId, $date, $startTime, $endTime)) {
                throw new \DomainException('This laboratory slot is reserved. Please choose another time.');
            }

            $remaining = $this->computeRemainingForSlot($labId, $date, $startTime, $endTime, $selectedAssets, $id);
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

                $newPdfPath = '/uploads/pdfs/' . $storedPdfName;
                $deleteOldPdf = $oldPdfPath !== '' && $oldPdfPath !== $newPdfPath;
            }

            $bookingModel->update($id, [
                'faculty_id' => $facultyId,
                'approval_flow' => $approvalFlow,
                'approved_by_pic' => 0,
                'approved_by_manager' => 0,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'activity' => $activity,
                'supervisor_name' => $supervisorName ?: null,
                'supervisor_email' => $supervisorEmail ?: null,
                'supervisor_phone' => $supervisorPhone ?: null,
                'pdf_path' => $newPdfPath ?: null,
                'status' => 'PENDING',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $bookingApplicantModel->where('booking_id', $id)->delete();
            $bookingApplicantModel->insertBatchApplicants($id, $applicants);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to update booking data.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($storedPdfName && is_file(WRITEPATH . 'uploads/pdfs/' . $storedPdfName)) {
                @unlink(WRITEPATH . 'uploads/pdfs/' . $storedPdfName);
            }

            log_message('error', 'Booking update failed: ' . $e->getMessage());

            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $e instanceof \DomainException
                        ? $e->getMessage()
                        : 'Failed to update booking. Please try again.',
                ]);
        }

        if ($deleteOldPdf) {
            $oldFile = WRITEPATH . 'uploads/pdfs/' . basename($oldPdfPath);
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        NotificationService::dispatchSafely(
            fn(NotificationService $notifications) => $notifications->notifyBookingSubmitted($bookingModel->find($id) ?: []),
            'booking updated'
        );

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Booking updated successfully and resubmitted for approval.',
        ]);
    }

    protected function mediaUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_ends_with($path, '/')) {
            return '';
        }

        $scheme = $this->request->getUri()->getScheme();
        $host = $this->request->getHeaderLine('Host');

        return rtrim($scheme . '://' . $host, '/') . '/' . ltrim($path, '/');
    }

    protected function documentUrl(string $pdfPath): string
    {
        $filename = basename(trim($pdfPath));
        if ($filename === '' || ! preg_match('/^[A-Za-z0-9._-]+\.pdf$/i', $filename)) {
            return '';
        }

        return $this->mediaUrl('api/native/documents/pdf/' . $filename);
    }
}
