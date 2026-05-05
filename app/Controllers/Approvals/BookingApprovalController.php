<?php

namespace App\Controllers\Approvals;

use App\Controllers\BaseController;
use App\Libraries\NotificationService;
use App\Libraries\UserRoleResolver;
use App\Models\BookingModel;
use App\Models\LaboratoryModel;
use Config\Database;

class BookingApprovalController extends BaseController
{
    public function approve($id)
    {
        helper('auth');

        if (! auth()->loggedIn()) {
            return $this->respondForbidden('You must be logged in to approve bookings.');
        }

        $bookingModel = new BookingModel();
        $labModel = new LaboratoryModel();
        $booking = $bookingModel->find($id);

        if (! $booking) {
            return $this->respondNotFound('Booking not found.');
        }

        $user = auth()->user();
        $userEmail = strtolower(trim((string) $user->email));
        $approverRole = (new UserRoleResolver())->approvalRole($user);
        $isPic = $approverRole === 'pic';
        $isManager = $approverRole === 'manager';
        $isAdmin = $approverRole === 'admin';

        if (! $isPic && ! $isManager && ! $isAdmin) {
            return $this->respondForbidden('You are not authorized to approve this booking.');
        }

        $isFkmpBooking = $booking['approval_flow'] === 'FKMP_APPROVAL';
        $lab = $labModel->find($booking['lab_id']);
        if (! $lab) {
            return $this->respondNotFound('Associated laboratory not found.');
        }

        if ($booking['status'] === 'CANCELLED') {
            return $this->respondForbidden('Cannot approve a cancelled booking.');
        }

        if ($message = $this->ensureAssetsStillAvailable($booking)) {
            return $this->respondForbidden($message);
        }

        if ($isPic) {
            if (empty($lab['pic_email']) || strtolower(trim((string) $lab['pic_email'])) !== $userEmail) {
                return $this->respondForbidden('You are not the PIC for this laboratory.');
            }
            if ($booking['status'] === 'REJECTED') {
                return $this->respondForbidden('Cannot approve a rejected booking.');
            }
            if ($booking['status'] === 'APPROVED') {
                return $this->successResponse('This booking is already approved.', 'APPROVED');
            }

            if (! (int) $booking['approved_by_pic']) {
                $updates = [
                    'approved_by_pic' => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                if ($isFkmpBooking) {
                    $updates['approved_by_manager'] = 1;
                    $updates['status'] = 'APPROVED';
                    $bookingModel->update($id, $updates);
                    $updated = $bookingModel->find($id) ?: array_merge($booking, $updates);
                    NotificationService::dispatchSafely(
                        fn(NotificationService $notifications) => $notifications->notifyBookingApproved($updated),
                        'booking approved by PIC'
                    );

                    return $this->successResponse('Booking approved successfully (Final approval for FKMP).', 'APPROVED');
                }

                $updates['status'] = 'PENDING';
                $bookingModel->update($id, $updates);
                $updated = $bookingModel->find($id) ?: array_merge($booking, $updates);
                NotificationService::dispatchSafely(
                    fn(NotificationService $notifications) => $notifications->notifyBookingPendingManager($updated),
                    'booking pending manager'
                );

                return $this->successResponse('PIC approved. Booking now awaits Manager approval.', 'PENDING_MANAGER');
            }

            return $this->successResponse('PIC has already approved this booking.', $isFkmpBooking ? 'APPROVED' : 'PENDING_MANAGER');
        }

        if ($isManager) {
            if ($isFkmpBooking) {
                return $this->respondForbidden('Manager approval is not required for FKMP bookings.');
            }
            if (! (int) $booking['approved_by_pic']) {
                return $this->respondForbidden('PIC must approve before the Manager can approve.');
            }
            if ($booking['status'] === 'REJECTED') {
                return $this->respondForbidden('Cannot approve a rejected booking.');
            }
            if ((int) $booking['approved_by_manager'] && $booking['status'] === 'APPROVED') {
                return $this->successResponse('Booking already approved.', 'APPROVED');
            }

            $bookingModel->update($id, [
                'approved_by_manager' => 1,
                'status' => 'APPROVED',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $updated = $bookingModel->find($id) ?: array_merge($booking, ['approved_by_manager' => 1, 'status' => 'APPROVED']);
            NotificationService::dispatchSafely(
                fn(NotificationService $notifications) => $notifications->notifyBookingApproved($updated),
                'booking approved by manager'
            );

            return $this->successResponse('Booking fully approved by Manager.', 'APPROVED');
        }

        if ($isAdmin) {
            return $this->approveAsAdmin($id, $booking, $bookingModel, $isFkmpBooking);
        }

        return $this->respondForbidden('You are not authorized to approve this booking.');
    }

    public function reject($id)
    {
        helper('auth');

        if (! auth()->loggedIn()) {
            return $this->respondForbidden('You must be logged in to reject bookings.');
        }

        $bookingModel = new BookingModel();
        $labModel = new LaboratoryModel();
        $booking = $bookingModel->find($id);

        if (! $booking) {
            return $this->respondNotFound('Booking not found.');
        }

        $user = auth()->user();
        $userEmail = strtolower(trim((string) $user->email));
        $approverRole = (new UserRoleResolver())->approvalRole($user);
        $isPic = $approverRole === 'pic';
        $isManager = $approverRole === 'manager';
        $isAdmin = $approverRole === 'admin';

        if (! $isPic && ! $isManager && ! $isAdmin) {
            return $this->respondForbidden('You are not authorized to reject this booking.');
        }
        if ($booking['status'] === 'REJECTED') {
            return $this->successResponse('Booking already rejected.', 'REJECTED');
        }
        if ($booking['status'] === 'CANCELLED') {
            return $this->respondForbidden('This booking was cancelled by the applicant and cannot be rejected.');
        }

        $isFkmpBooking = $booking['approval_flow'] === 'FKMP_APPROVAL';
        $lab = $labModel->find($booking['lab_id']);
        if (! $lab) {
            return $this->respondNotFound('Associated laboratory not found.');
        }

        if ($isPic) {
            if (empty($lab['pic_email']) || strtolower(trim((string) $lab['pic_email'])) !== $userEmail) {
                return $this->respondForbidden('You are not the PIC for this laboratory.');
            }

            $updates = [
                'status' => 'REJECTED',
                'approved_by_pic' => 0,
                'approved_by_manager' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $bookingModel->update($id, $updates);
            $updated = $bookingModel->find($id) ?: array_merge($booking, $updates);
            NotificationService::dispatchSafely(
                fn(NotificationService $notifications) => $notifications->notifyBookingRejected($updated, 'PIC'),
                'booking rejected by PIC'
            );

            return $this->successResponse('Booking rejected by PIC.', 'REJECTED');
        }

        if ($isManager) {
            if ($isFkmpBooking) {
                return $this->respondForbidden('Manager cannot reject FKMP bookings - only PIC can handle them.');
            }

            $bookingModel->update($id, [
                'status' => 'REJECTED',
                'approved_by_manager' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $updated = $bookingModel->find($id) ?: array_merge($booking, ['status' => 'REJECTED', 'approved_by_manager' => 0]);
            NotificationService::dispatchSafely(
                fn(NotificationService $notifications) => $notifications->notifyBookingRejected($updated, 'Lab Manager'),
                'booking rejected by manager'
            );

            return $this->successResponse('Booking rejected by Manager.', 'REJECTED');
        }

        if ($isAdmin) {
            return $this->rejectAsAdmin($id, $booking, $bookingModel);
        }

        return $this->respondForbidden('You are not authorized to reject this booking.');
    }

    protected function approveAsAdmin(int $id, array $booking, BookingModel $bookingModel, bool $isFkmpBooking)
    {
        if ((int) ($booking['approved_by_pic'] ?? 0) === 0) {
            $updates = [
                'approved_by_pic' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($isFkmpBooking) {
                $updates['approved_by_manager'] = 1;
                $updates['status'] = 'APPROVED';
                $bookingModel->update($id, $updates);
                $updated = $bookingModel->find($id) ?: array_merge($booking, $updates);
                NotificationService::dispatchSafely(
                    fn(NotificationService $notifications) => $notifications->notifyBookingApproved($updated),
                    'booking approved by administrator'
                );

                return $this->successResponse('Administrator completed final approval for this FKMP booking.', 'APPROVED');
            }

            $updates['status'] = 'PENDING';
            $bookingModel->update($id, $updates);
            $updated = $bookingModel->find($id) ?: array_merge($booking, $updates);
            NotificationService::dispatchSafely(
                fn(NotificationService $notifications) => $notifications->notifyBookingPendingManager($updated),
                'booking advanced to manager by administrator'
            );

            return $this->successResponse('Administrator approved the PIC stage. Booking now awaits Manager approval.', 'PENDING_MANAGER');
        }

        if ($booking['status'] === 'APPROVED' && (int) ($booking['approved_by_manager'] ?? 0) === 1) {
            return $this->successResponse('Booking already approved.', 'APPROVED');
        }

        $bookingModel->update($id, [
            'approved_by_manager' => 1,
            'status' => 'APPROVED',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $updated = $bookingModel->find($id) ?: array_merge($booking, ['approved_by_manager' => 1, 'status' => 'APPROVED']);
        NotificationService::dispatchSafely(
            fn(NotificationService $notifications) => $notifications->notifyBookingApproved($updated),
            'booking approved by administrator'
        );

        return $this->successResponse('Administrator completed the final approval.', 'APPROVED');
    }

    protected function rejectAsAdmin(int $id, array $booking, BookingModel $bookingModel)
    {
        $updates = [
            'status' => 'REJECTED',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ((int) ($booking['approved_by_pic'] ?? 0) === 0) {
            $updates['approved_by_pic'] = 0;
            $updates['approved_by_manager'] = 0;
        } else {
            $updates['approved_by_manager'] = 0;
        }

        $bookingModel->update($id, $updates);
        $updated = $bookingModel->find($id) ?: array_merge($booking, $updates);
        NotificationService::dispatchSafely(
            fn(NotificationService $notifications) => $notifications->notifyBookingRejected($updated, 'Administrator'),
            'booking rejected by administrator'
        );

        return $this->successResponse('Booking rejected by Administrator.', 'REJECTED');
    }

    protected function ensureAssetsStillAvailable(array $booking): ?string
    {
        $db = Database::connect();
        $bookingModel = new BookingModel();

        if ($bookingModel->hasLabConflict(
            (int) $booking['lab_id'],
            (string) $booking['date'],
            (string) $booking['start_time'],
            (string) $booking['end_time'],
            (int) $booking['id']
        )) {
            return 'This laboratory already has another active booking for the selected time slot.';
        }

        $assetRows = $db->table('booking_assets')
            ->select('asset_id, quantity_used')
            ->where('booking_id', $booking['id'])
            ->get()
            ->getResultArray();

        if ($assetRows === []) {
            return 'This booking has no linked asset records.';
        }

        $assetIds = array_map(static fn(array $row): int => (int) $row['asset_id'], $assetRows);
        $assets = $db->table('assets')
            ->select('id, name, quantity, total_quantity, status')
            ->whereIn('id', $assetIds)
            ->get()
            ->getResultArray();

        $assetMap = [];
        foreach ($assets as $asset) {
            $assetMap[(int) $asset['id']] = $asset;
        }

        $usedRows = $db->table('booking_assets ba')
            ->select('ba.asset_id, SUM(ba.quantity_used) AS used_qty')
            ->join('bookings b', 'b.id = ba.booking_id')
            ->where('b.lab_id', $booking['lab_id'])
            ->where('b.date', $booking['date'])
            ->whereIn('b.status', ['PENDING', 'APPROVED'])
            ->where('b.id !=', $booking['id'])
            ->where('b.start_time <', $booking['end_time'])
            ->where('b.end_time >', $booking['start_time'])
            ->whereIn('ba.asset_id', $assetIds)
            ->groupBy('ba.asset_id')
            ->get()
            ->getResultArray();

        $usedMap = [];
        foreach ($usedRows as $row) {
            $usedMap[(int) $row['asset_id']] = (int) $row['used_qty'];
        }

        foreach ($assetRows as $row) {
            $assetId = (int) $row['asset_id'];
            $needed = (int) $row['quantity_used'];
            $asset = $assetMap[$assetId] ?? null;

            if (! $asset) {
                return 'One or more booking assets no longer exist.';
            }

            $remaining = max((int) ($asset['quantity'] ?? 0) - ($usedMap[$assetId] ?? 0), 0);
            if ($remaining < $needed) {
                return 'One or more selected assets no longer have enough available quantity for this booking slot.';
            }
        }

        return null;
    }

    protected function respondNotFound(string $msg)
    {
        if ($this->shouldReturnJson()) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => $msg]);
        }
        return redirect()->back()->with('error', $msg);
    }

    protected function respondForbidden(string $msg)
    {
        if ($this->shouldReturnJson()) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => $msg]);
        }
        return redirect()->back()->with('error', $msg);
    }

    protected function successResponse(string $msg, string $status)
    {
        if ($this->shouldReturnJson()) {
            return $this->response->setJSON(['status' => 'success', 'newStatus' => $status, 'message' => $msg]);
        }
        return redirect()->back()->with('message', $msg);
    }

    protected function shouldReturnJson(): bool
    {
        if ($this->request->isAJAX()) {
            return true;
        }

        return str_starts_with(trim((string) $this->request->getPath(), '/'), 'api/native/');
    }

}
