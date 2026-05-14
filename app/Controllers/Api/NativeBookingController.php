<?php

namespace App\Controllers\Api;

use App\Controllers\Public\BookingController as WebBookingController;
use App\Models\BookingApplicantModel;
use App\Models\BookingAssetModel;
use App\Models\BookingModel;
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
                        return [
                            'id' => (int) $applicant['id'],
                            'name' => (string) ($applicant['name'] ?? ''),
                            'matric_id' => (string) ($applicant['matric_id'] ?? ''),
                            'email' => (string) ($applicant['email'] ?? ''),
                            'phone' => (string) ($applicant['phone'] ?? ''),
                            'faculty' => (string) ($applicant['faculty'] ?? ''),
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
        ];
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
