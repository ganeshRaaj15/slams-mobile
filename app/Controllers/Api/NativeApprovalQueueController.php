<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\BookingDocumentLocator;
use App\Libraries\UserRoleResolver;
use App\Models\BookingApplicantModel;
use App\Models\BookingAssetModel;
use App\Models\BookingModel;
use App\Models\LaboratoryModel;
use CodeIgniter\Shield\Entities\User;

class NativeApprovalQueueController extends BaseController
{
    public function index()
    {
        helper('auth');

        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->unauthenticated();
        }

        $scope = $this->scopeForUser($user);
        if ($scope === null) {
            return $this->forbidden('Approval queue access is limited to PIC, Manager, and Admin roles.');
        }

        $stats = $this->queueStats($scope);
        $bookings = $this->queueItems($scope);

        return $this->response->setJSON([
            'status' => 'success',
            'role' => $scope['role'],
            'stats' => $stats,
            'bookings' => $bookings,
        ]);
    }

    public function show(int $id)
    {
        helper('auth');

        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->unauthenticated();
        }

        $scope = $this->scopeForUser($user);
        if ($scope === null) {
            return $this->forbidden('Approval queue access is limited to PIC, Manager, and Admin roles.');
        }

        $booking = $this->authorizedBooking($id, $scope);
        if (! $booking) {
            return $this->notFound('Booking not found in your approval queue.');
        }

        $assets = (new BookingAssetModel())
            ->select('booking_assets.asset_id, booking_assets.quantity_used, assets.name, assets.image, assets.model, assets.asset_code')
            ->join('assets', 'assets.id = booking_assets.asset_id', 'left')
            ->where('booking_assets.booking_id', $id)
            ->findAll();

        $applicants = (new BookingApplicantModel())->getForBooking($id);

        return $this->response->setJSON([
            'status' => 'success',
            'booking' => [
                'id' => (int) $booking['id'],
                'lab_id' => (int) ($booking['lab_id'] ?? 0),
                'lab_name' => (string) ($booking['lab_name'] ?? ''),
                'lab_room' => (string) ($booking['lab_room'] ?? ''),
                'pic_name' => (string) ($booking['pic_name'] ?? ''),
                'pic_email' => (string) ($booking['pic_email'] ?? ''),
                'pic_phone' => (string) ($booking['pic_phone'] ?? ''),
                'faculty_name' => (string) ($booking['faculty_name'] ?? ''),
                'is_fkmp' => (bool) ($booking['is_fkmp'] ?? false),
                'date' => (string) ($booking['date'] ?? ''),
                'start_time' => substr((string) ($booking['start_time'] ?? ''), 0, 5),
                'end_time' => substr((string) ($booking['end_time'] ?? ''), 0, 5),
                'activity' => (string) ($booking['activity'] ?? ''),
                'status' => (string) ($booking['status'] ?? ''),
                'approval_flow' => (string) ($booking['approval_flow'] ?? ''),
                'approved_by_pic' => (bool) ($booking['approved_by_pic'] ?? false),
                'approved_by_manager' => (bool) ($booking['approved_by_manager'] ?? false),
                'supervisor_name' => (string) ($booking['supervisor_name'] ?? ''),
                'supervisor_email' => (string) ($booking['supervisor_email'] ?? ''),
                'supervisor_phone' => (string) ($booking['supervisor_phone'] ?? ''),
                'stage' => $this->approvalStage($booking),
                'pdf_url' => $this->pdfUrl((string) ($booking['pdf_path'] ?? '')),
                'assets' => array_map(fn(array $asset): array => [
                    'asset_id' => (int) $asset['asset_id'],
                    'name' => (string) ($asset['name'] ?? ''),
                    'asset_code' => (string) ($asset['asset_code'] ?? ''),
                    'model' => (string) ($asset['model'] ?? ''),
                    'quantity_used' => (int) ($asset['quantity_used'] ?? 0),
                    'image' => (string) ($asset['image'] ?? ''),
                    'image_url' => $this->mediaUrl('images/assets/' . ltrim((string) ($asset['image'] ?? ''), '/')),
                ], $assets),
                'applicants' => array_map(static fn(array $applicant): array => [
                    'id' => (int) $applicant['id'],
                    'name' => (string) ($applicant['name'] ?? ''),
                    'matric_id' => (string) ($applicant['matric_id'] ?? ''),
                    'email' => (string) ($applicant['email'] ?? ''),
                    'phone' => (string) ($applicant['phone'] ?? ''),
                    'faculty' => (string) ($applicant['faculty'] ?? ''),
                ], $applicants),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function scopeForUser(User $user): ?array
    {
        $email = strtolower(trim((string) $user->email));
        $role = (new UserRoleResolver())->approvalRole($user);

        if ($role === 'pic') {
            $labs = (new LaboratoryModel())
                ->where('LOWER(TRIM(pic_email)) =', $email)
                ->orderBy('name', 'ASC')
                ->findAll();

            return [
                'role' => 'pic',
                'email' => $email,
                'lab_ids' => array_map(static fn(array $lab): int => (int) $lab['id'], $labs),
            ];
        }

        if ($role === 'manager' || $role === 'admin') {
            return [
                'role' => $role,
                'email' => $email,
                'lab_ids' => [],
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $scope
     */
    protected function queueStats(array $scope): array
    {
        $bookingModel = new BookingModel();

        if ($scope['role'] === 'pic') {
            $labIds = $scope['lab_ids'];
            if ($labIds === []) {
                return [
                    'queue_count' => 0,
                    'pending_pic' => 0,
                    'pending_manager' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'cancelled' => 0,
                ];
            }

            return [
                'queue_count' => (int) $bookingModel
                    ->whereIn('lab_id', $labIds)
                    ->where('status', 'PENDING')
                    ->where('approved_by_pic', 0)
                    ->countAllResults(),
                'pending_pic' => (int) (new BookingModel())
                    ->whereIn('lab_id', $labIds)
                    ->where('status', 'PENDING')
                    ->where('approved_by_pic', 0)
                    ->countAllResults(),
                'pending_manager' => (int) (new BookingModel())
                    ->whereIn('lab_id', $labIds)
                    ->where('status', 'PENDING')
                    ->where('approved_by_pic', 1)
                    ->where('approved_by_manager', 0)
                    ->where('approval_flow !=', 'FKMP_APPROVAL')
                    ->countAllResults(),
                'approved' => (int) (new BookingModel())
                    ->whereIn('lab_id', $labIds)
                    ->where('status', 'APPROVED')
                    ->countAllResults(),
                'rejected' => (int) (new BookingModel())
                    ->whereIn('lab_id', $labIds)
                    ->where('status', 'REJECTED')
                    ->countAllResults(),
                'cancelled' => (int) (new BookingModel())
                    ->whereIn('lab_id', $labIds)
                    ->where('status', 'CANCELLED')
                    ->countAllResults(),
            ];
        }

        if ($scope['role'] === 'admin') {
            return [
                'queue_count' => (int) $bookingModel
                    ->where('status', 'PENDING')
                    ->groupStart()
                        ->where('approved_by_pic', 0)
                        ->orGroupStart()
                            ->where('approved_by_pic', 1)
                            ->where('approved_by_manager', 0)
                            ->where('approval_flow !=', 'FKMP_APPROVAL')
                        ->groupEnd()
                    ->groupEnd()
                    ->countAllResults(),
                'pending_pic' => (int) (new BookingModel())
                    ->where('status', 'PENDING')
                    ->where('approved_by_pic', 0)
                    ->countAllResults(),
                'pending_manager' => (int) (new BookingModel())
                    ->where('status', 'PENDING')
                    ->where('approved_by_pic', 1)
                    ->where('approved_by_manager', 0)
                    ->where('approval_flow !=', 'FKMP_APPROVAL')
                    ->countAllResults(),
                'approved' => (int) (new BookingModel())
                    ->where('status', 'APPROVED')
                    ->countAllResults(),
                'rejected' => (int) (new BookingModel())
                    ->where('status', 'REJECTED')
                    ->countAllResults(),
                'cancelled' => (int) (new BookingModel())
                    ->where('status', 'CANCELLED')
                    ->countAllResults(),
            ];
        }

        return [
            'queue_count' => (int) $bookingModel
                ->where('status', 'PENDING')
                ->where('approved_by_pic', 1)
                ->where('approved_by_manager', 0)
                ->where('approval_flow !=', 'FKMP_APPROVAL')
                ->countAllResults(),
            'pending_manager' => (int) (new BookingModel())
                ->where('status', 'PENDING')
                ->where('approved_by_pic', 1)
                ->where('approved_by_manager', 0)
                ->where('approval_flow !=', 'FKMP_APPROVAL')
                ->countAllResults(),
            'approved' => (int) (new BookingModel())
                ->where('status', 'APPROVED')
                ->countAllResults(),
            'rejected' => (int) (new BookingModel())
                ->where('status', 'REJECTED')
                ->countAllResults(),
            'cancelled' => (int) (new BookingModel())
                ->where('status', 'CANCELLED')
                ->countAllResults(),
        ];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array<int, array<string, mixed>>
     */
    protected function queueItems(array $scope): array
    {
        $query = (new BookingModel())
            ->select('bookings.*, laboratories.name AS lab_name, laboratories.room AS lab_room, laboratories.pic_name, laboratories.pic_email, faculties.name_en AS faculty_name, faculties.is_fkmp')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->join('faculties', 'faculties.id = bookings.faculty_id', 'left');

        if ($scope['role'] === 'pic') {
            $labIds = $scope['lab_ids'];
            if ($labIds === []) {
                return [];
            }

            $query
                ->whereIn('bookings.lab_id', $labIds)
                ->where('bookings.status', 'PENDING')
                ->where('bookings.approved_by_pic', 0);
        } elseif ($scope['role'] === 'admin') {
            $query
                ->where('bookings.status', 'PENDING')
                ->groupStart()
                    ->where('bookings.approved_by_pic', 0)
                    ->orGroupStart()
                        ->where('bookings.approved_by_pic', 1)
                        ->where('bookings.approved_by_manager', 0)
                        ->where('bookings.approval_flow !=', 'FKMP_APPROVAL')
                    ->groupEnd()
                ->groupEnd();
        } else {
            $query
                ->where('bookings.status', 'PENDING')
                ->where('bookings.approved_by_pic', 1)
                ->where('bookings.approved_by_manager', 0)
                ->where('bookings.approval_flow !=', 'FKMP_APPROVAL');
        }

        $bookings = $query
            ->orderBy('bookings.date', 'ASC')
            ->orderBy('bookings.start_time', 'ASC')
            ->findAll();

        $assetPreviewMap = $this->assetPreviewMap(array_column($bookings, 'id'));

        return array_map(function (array $booking) use ($assetPreviewMap): array {
            $bookingId = (int) $booking['id'];

            return [
                'id' => $bookingId,
                'lab_name' => (string) ($booking['lab_name'] ?? ''),
                'lab_room' => (string) ($booking['lab_room'] ?? ''),
                'faculty_name' => (string) ($booking['faculty_name'] ?? ''),
                'is_fkmp' => (bool) ($booking['is_fkmp'] ?? false),
                'date' => (string) ($booking['date'] ?? ''),
                'start_time' => substr((string) ($booking['start_time'] ?? ''), 0, 5),
                'end_time' => substr((string) ($booking['end_time'] ?? ''), 0, 5),
                'activity' => (string) ($booking['activity'] ?? ''),
                'status' => (string) ($booking['status'] ?? ''),
                'approval_flow' => (string) ($booking['approval_flow'] ?? ''),
                'stage' => $this->approvalStage($booking),
                'pic_name' => (string) ($booking['pic_name'] ?? ''),
                'pic_email' => (string) ($booking['pic_email'] ?? ''),
                'assets' => $assetPreviewMap[$bookingId] ?? [],
            ];
        }, $bookings);
    }

    /**
     * @param list<int> $bookingIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function assetPreviewMap(array $bookingIds): array
    {
        if ($bookingIds === []) {
            return [];
        }

        $rows = (new BookingAssetModel())
            ->select('booking_assets.booking_id, booking_assets.asset_id, booking_assets.quantity_used, assets.name')
            ->join('assets', 'assets.id = booking_assets.asset_id', 'left')
            ->whereIn('booking_assets.booking_id', $bookingIds)
            ->orderBy('booking_assets.booking_id', 'ASC')
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $bookingId = (int) $row['booking_id'];
            $map[$bookingId] ??= [];
            $map[$bookingId][] = [
                'asset_id' => (int) $row['asset_id'],
                'name' => (string) ($row['name'] ?? ''),
                'quantity_used' => (int) ($row['quantity_used'] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $scope
     * @return array<string, mixed>|null
     */
    protected function authorizedBooking(int $id, array $scope): ?array
    {
        $query = (new BookingModel())
            ->select('bookings.*, laboratories.name AS lab_name, laboratories.room AS lab_room, laboratories.pic_name, laboratories.pic_email, laboratories.pic_phone, faculties.name_en AS faculty_name, faculties.is_fkmp')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->join('faculties', 'faculties.id = bookings.faculty_id', 'left')
            ->where('bookings.id', $id);

        if ($scope['role'] === 'pic') {
            $labIds = $scope['lab_ids'];
            if ($labIds === []) {
                return null;
            }

            $query
                ->whereIn('bookings.lab_id', $labIds)
                ->where('bookings.status', 'PENDING')
                ->where('bookings.approved_by_pic', 0);
        } elseif ($scope['role'] === 'admin') {
            $query
                ->where('bookings.status', 'PENDING')
                ->groupStart()
                    ->where('bookings.approved_by_pic', 0)
                    ->orGroupStart()
                        ->where('bookings.approved_by_pic', 1)
                        ->where('bookings.approved_by_manager', 0)
                        ->where('bookings.approval_flow !=', 'FKMP_APPROVAL')
                    ->groupEnd()
                ->groupEnd();
        } else {
            $query
                ->where('bookings.status', 'PENDING')
                ->where('bookings.approved_by_pic', 1)
                ->where('bookings.approved_by_manager', 0)
                ->where('bookings.approval_flow !=', 'FKMP_APPROVAL');
        }

        return $query->first();
    }

    /**
     * @param array<string, mixed> $booking
     */
    protected function approvalStage(array $booking): string
    {
        if ((int) ($booking['approved_by_pic'] ?? 0) === 0) {
            return 'pic_review';
        }

        return 'manager_review';
    }

    protected function pdfUrl(string $pdfPath): string
    {
        $filename = basename(trim($pdfPath));
        if ($filename === '') {
            return '';
        }

        if ((new BookingDocumentLocator())->resolvePdfPath($filename) === null) {
            return '';
        }

        return $this->mediaUrl('api/native/documents/pdf/' . $filename);
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

    protected function unauthenticated()
    {
        return $this->response
            ->setStatusCode(401)
            ->setJSON([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }

    protected function forbidden(string $message)
    {
        return $this->response
            ->setStatusCode(403)
            ->setJSON([
                'status' => 'error',
                'message' => $message,
            ]);
    }

    protected function notFound(string $message)
    {
        return $this->response
            ->setStatusCode(404)
            ->setJSON([
                'status' => 'error',
                'message' => $message,
            ]);
    }
}
