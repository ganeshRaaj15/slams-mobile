<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\NativeUserSerializer;
use App\Models\BookingModel;
use App\Models\ExternalRequestModel;
use App\Models\LaboratoryModel;
use App\Models\MaintenanceRecordModel;
use App\Models\NotificationModel;
use CodeIgniter\Shield\Entities\User;

class NativeBootstrapController extends BaseController
{
    protected NativeUserSerializer $serializer;

    public function __construct()
    {
        helper('auth');
        $this->serializer = new NativeUserSerializer();
    }

    public function show()
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

        $serializedUser = $this->serializer->serialize($user);
        $role = (string) ($serializedUser['primary_role'] ?? 'student');

        return $this->response->setJSON([
            'status' => 'success',
            'user' => $serializedUser,
            'navigation' => $this->navigationForRole($role),
            'summary' => $this->summaryForRole($user, $role),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function navigationForRole(string $role): array
    {
        return match ($role) {
            'external' => [
                ['id' => 'home', 'label' => 'Home'],
                ['id' => 'labs', 'label' => 'Labs'],
                ['id' => 'requests', 'label' => 'Requests'],
                ['id' => 'notifications', 'label' => 'Alerts'],
                ['id' => 'profile', 'label' => 'Profile'],
            ],
            'student', 'staff' => [
                ['id' => 'home', 'label' => 'Home'],
                ['id' => 'labs', 'label' => 'Labs'],
                ['id' => 'bookings', 'label' => 'Bookings'],
                ['id' => 'issues', 'label' => 'Issues'],
                ['id' => 'notifications', 'label' => 'Alerts'],
                ['id' => 'profile', 'label' => 'Profile'],
            ],
            'pic' => [
                ['id' => 'home', 'label' => 'Home'],
                ['id' => 'labs', 'label' => 'Labs'],
                ['id' => 'issues', 'label' => 'Issues'],
                ['id' => 'approvals', 'label' => 'Approvals'],
                ['id' => 'reports', 'label' => 'Reports'],
                ['id' => 'requests', 'label' => 'External'],
                ['id' => 'notifications', 'label' => 'Alerts'],
                ['id' => 'profile', 'label' => 'Profile'],
            ],
            'technician' => [
                ['id' => 'home', 'label' => 'Home'],
                ['id' => 'labs', 'label' => 'Labs'],
                ['id' => 'maintenance', 'label' => 'Maintenance'],
                ['id' => 'notifications', 'label' => 'Alerts'],
                ['id' => 'profile', 'label' => 'Profile'],
            ],
            'manager' => [
                ['id' => 'home', 'label' => 'Home'],
                ['id' => 'labs', 'label' => 'Labs'],
                ['id' => 'approvals', 'label' => 'Approvals'],
                ['id' => 'reports', 'label' => 'Reports'],
                ['id' => 'requests', 'label' => 'External'],
                ['id' => 'notifications', 'label' => 'Alerts'],
                ['id' => 'profile', 'label' => 'Profile'],
            ],
            'admin' => [
                ['id' => 'home', 'label' => 'Home'],
                ['id' => 'labs', 'label' => 'Labs'],
                ['id' => 'approvals', 'label' => 'Approvals'],
                ['id' => 'reports', 'label' => 'Reports'],
                ['id' => 'requests', 'label' => 'External'],
                ['id' => 'admin', 'label' => 'Admin'],
                ['id' => 'notifications', 'label' => 'Alerts'],
                ['id' => 'profile', 'label' => 'Profile'],
            ],
            default => [
                ['id' => 'home', 'label' => 'Home'],
                ['id' => 'labs', 'label' => 'Labs'],
                ['id' => 'notifications', 'label' => 'Alerts'],
                ['id' => 'profile', 'label' => 'Profile'],
            ],
        };
    }

    protected function summaryForRole(User $user, string $role): array
    {
        $notificationCount = (new NotificationModel())
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->countAllResults();

        return match ($role) {
            'external' => $this->externalSummary($user, $notificationCount),
            'pic' => $this->picSummary($user, $notificationCount),
            'manager' => $this->managerSummary($notificationCount),
            'admin' => $this->adminSummary($notificationCount),
            'technician' => $this->technicianSummary($user, $notificationCount),
            default => $this->studentSummary($user, $notificationCount),
        };
    }

    protected function studentSummary(User $user, int $notificationCount): array
    {
        $bookingModel = new BookingModel();
        $activeBookings = (int) $bookingModel
            ->where('user_id', $user->id)
            ->whereIn('status', BookingModel::ACTIVE_STATUSES)
            ->countAllResults();

        $pending = (int) (new BookingModel())
            ->where('user_id', $user->id)
            ->where('status', 'PENDING')
            ->countAllResults();

        $approved = (int) (new BookingModel())
            ->where('user_id', $user->id)
            ->where('status', 'APPROVED')
            ->countAllResults();

        $nextBooking = $bookingModel
            ->select('bookings.id, bookings.date, bookings.start_time, bookings.end_time, bookings.status, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where('bookings.user_id', $user->id)
            ->whereIn('bookings.status', BookingModel::ACTIVE_STATUSES)
            ->where('bookings.date >=', date('Y-m-d'))
            ->orderBy('bookings.date', 'ASC')
            ->orderBy('bookings.start_time', 'ASC')
            ->first();

        return [
            'role' => 'student',
            'attention_count' => max($activeBookings, $notificationCount),
            'attention_label' => $activeBookings > 0 ? $activeBookings . ' active booking(s)' : 'Ready to book',
            'attention_meta' => 'Track reservations, approvals, and lab activity in one place.',
            'stats' => [
                ['id' => 'active_bookings', 'label' => 'Active', 'value' => $activeBookings, 'tone' => 'primary'],
                ['id' => 'pending', 'label' => 'Pending', 'value' => $pending, 'tone' => 'warning'],
                ['id' => 'approved', 'label' => 'Approved', 'value' => $approved, 'tone' => 'success'],
                ['id' => 'notifications', 'label' => 'Alerts', 'value' => $notificationCount, 'tone' => 'neutral'],
            ],
            'next_item' => $nextBooking ? [
                'type' => 'booking',
                'title' => (string) ($nextBooking['lab_name'] ?? 'Upcoming Booking'),
                'subtitle' => trim(((string) ($nextBooking['date'] ?? '')) . '  ' . substr((string) ($nextBooking['start_time'] ?? ''), 0, 5) . '-' . substr((string) ($nextBooking['end_time'] ?? ''), 0, 5)),
                'meta' => trim((string) ($nextBooking['lab_room'] ?? '')),
            ] : null,
            'message' => 'View your bookings, report issues, and monitor approval updates from your dashboard.',
        ];
    }

    protected function externalSummary(User $user, int $notificationCount): array
    {
        $requestModel = new ExternalRequestModel();
        $activeStatuses = ['pending_pic_approval', 'pending_manager_approval', 'needs_information', 'approved_for_scheduling'];
        $activeRequests = (int) $requestModel
            ->where('user_id', $user->id)
            ->whereIn('status', $activeStatuses)
            ->countAllResults();
        $needsInfo = (int) (new ExternalRequestModel())
            ->where('user_id', $user->id)
            ->where('status', 'needs_information')
            ->countAllResults();
        $approvedForScheduling = (int) (new ExternalRequestModel())
            ->where('user_id', $user->id)
            ->where('status', 'approved_for_scheduling')
            ->countAllResults();

        $latest = $requestModel
            ->select('external_requests.id, external_requests.status, external_requests.preferred_date, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = external_requests.lab_id', 'left')
            ->where('external_requests.user_id', $user->id)
            ->orderBy('external_requests.updated_at', 'DESC')
            ->first();

        return [
            'role' => 'external',
            'attention_count' => max($activeRequests, $notificationCount),
            'attention_label' => $activeRequests > 0 ? $activeRequests . ' active request(s)' : 'Request flow clear',
            'attention_meta' => 'External users request lab access here instead of booking slots directly.',
            'stats' => [
                ['id' => 'active_requests', 'label' => 'Active', 'value' => $activeRequests, 'tone' => 'primary'],
                ['id' => 'needs_information', 'label' => 'Needs Info', 'value' => $needsInfo, 'tone' => 'warning'],
                ['id' => 'approved_for_scheduling', 'label' => 'Approved', 'value' => $approvedForScheduling, 'tone' => 'success'],
                ['id' => 'notifications', 'label' => 'Alerts', 'value' => $notificationCount, 'tone' => 'neutral'],
            ],
            'next_item' => $latest ? [
                'type' => 'external_request',
                'title' => (string) ($latest['lab_name'] ?? 'Latest Request'),
                'subtitle' => $requestModel->statusLabel((string) ($latest['status'] ?? 'pending_pic_approval')),
                'meta' => trim(((string) ($latest['preferred_date'] ?? '')) . '  ' . ((string) ($latest['lab_room'] ?? ''))),
            ] : null,
            'message' => 'Requests stay in review until staff schedule the final booking internally.',
        ];
    }

    protected function picSummary(User $user, int $notificationCount): array
    {
        $labIds = $this->picLabIds((string) $user->email);

        $pendingPic = $labIds === [] ? 0 : (int) (new BookingModel())
            ->whereIn('lab_id', $labIds)
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 0)
            ->countAllResults();

        $pendingManager = $labIds === [] ? 0 : (int) (new BookingModel())
            ->whereIn('lab_id', $labIds)
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 1)
            ->where('approved_by_manager', 0)
            ->where('approval_flow !=', 'FKMP_APPROVAL')
            ->countAllResults();

        $externalReview = $labIds === [] ? 0 : (int) (new ExternalRequestModel())
            ->whereIn('lab_id', $labIds)
            ->where('status', 'pending_pic_approval')
            ->countAllResults();

        return [
            'role' => 'pic',
            'attention_count' => $pendingPic + $externalReview,
            'attention_label' => ($pendingPic + $externalReview) > 0 ? ($pendingPic + $externalReview) . ' reviews waiting' : 'Queue is clear',
            'attention_meta' => 'Review bookings, monitor requests, and oversee assigned laboratories.',
            'stats' => [
                ['id' => 'pending_pic', 'label' => 'Need PIC', 'value' => $pendingPic, 'tone' => 'warning'],
                ['id' => 'pending_manager', 'label' => 'Mgr Handoff', 'value' => $pendingManager, 'tone' => 'primary'],
                ['id' => 'external_review', 'label' => 'External', 'value' => $externalReview, 'tone' => 'accent'],
                ['id' => 'notifications', 'label' => 'Alerts', 'value' => $notificationCount, 'tone' => 'neutral'],
            ],
            'next_item' => null,
            'message' => 'Approvals, issue reporting, external requests, and reports are available for your assigned laboratories.',
        ];
    }

    protected function managerSummary(int $notificationCount): array
    {
        $pendingManager = (int) (new BookingModel())
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 1)
            ->where('approved_by_manager', 0)
            ->countAllResults();

        $externalReview = (int) (new ExternalRequestModel())
            ->where('status', 'pending_manager_approval')
            ->countAllResults();

        $upcomingApproved = (int) (new BookingModel())
            ->where('status', 'APPROVED')
            ->where('date >=', date('Y-m-d'))
            ->where('date <=', date('Y-m-d', strtotime('+7 days')))
            ->countAllResults();

        return [
            'role' => 'manager',
            'attention_count' => $pendingManager + $externalReview,
            'attention_label' => ($pendingManager + $externalReview) > 0 ? ($pendingManager + $externalReview) . ' reviews pending' : 'Manager queue is clear',
            'attention_meta' => 'Handle approvals, monitor demand, and review external requests.',
            'stats' => [
                ['id' => 'pending_manager', 'label' => 'Pending', 'value' => $pendingManager, 'tone' => 'warning'],
                ['id' => 'external_review', 'label' => 'External', 'value' => $externalReview, 'tone' => 'accent'],
                ['id' => 'upcoming_approved', 'label' => 'Next 7 Days', 'value' => $upcomingApproved, 'tone' => 'success'],
                ['id' => 'notifications', 'label' => 'Alerts', 'value' => $notificationCount, 'tone' => 'neutral'],
            ],
            'next_item' => null,
            'message' => 'Use reports and review queues to manage cross-laboratory scheduling and oversight.',
        ];
    }

    protected function adminSummary(int $notificationCount): array
    {
        $pendingAll = (int) (new BookingModel())
            ->where('status', 'PENDING')
            ->groupStart()
                ->where('approved_by_pic', 0)
                ->orGroupStart()
                    ->where('approved_by_pic', 1)
                    ->where('approved_by_manager', 0)
                ->groupEnd()
            ->groupEnd()
            ->countAllResults();

        $externalReview = (int) (new ExternalRequestModel())
            ->whereIn('status', ['pending_pic_approval', 'pending_manager_approval'])
            ->countAllResults();

        $maintenanceOpen = (int) db_connect()->table('maintenance_records')
            ->whereIn('status', ['reported', 'scheduled', 'in_progress', 'testing'])
            ->countAllResults();

        return [
            'role' => 'admin',
            'attention_count' => $pendingAll + $externalReview,
            'attention_label' => ($pendingAll + $externalReview) > 0 ? ($pendingAll + $externalReview) . ' admin tasks waiting' : 'Admin queue is clear',
            'attention_meta' => 'Oversee approvals, requests, maintenance, and system operations.',
            'stats' => [
                ['id' => 'pending_all', 'label' => 'Approvals', 'value' => $pendingAll, 'tone' => 'warning'],
                ['id' => 'external_review', 'label' => 'External', 'value' => $externalReview, 'tone' => 'accent'],
                ['id' => 'maintenance_open', 'label' => 'Maintenance', 'value' => $maintenanceOpen, 'tone' => 'primary'],
                ['id' => 'notifications', 'label' => 'Alerts', 'value' => $notificationCount, 'tone' => 'neutral'],
            ],
            'next_item' => null,
            'message' => 'User management, reports, settings, laboratories, and assets are available from the admin workspace.',
        ];
    }

    protected function technicianSummary(User $user, int $notificationCount): array
    {
        $maintenanceModel = new MaintenanceRecordModel();
        $openStatuses = $maintenanceModel->openStatuses();

        $assigned = (int) (new MaintenanceRecordModel())
            ->where('assigned_technician_id', $user->id)
            ->whereIn('status', $openStatuses)
            ->countAllResults();

        $openTotal = (int) (new MaintenanceRecordModel())
            ->whereIn('status', $openStatuses)
            ->countAllResults();

        $testing = (int) (new MaintenanceRecordModel())
            ->where('status', 'testing')
            ->countAllResults();

        return [
            'role' => 'technician',
            'attention_count' => $assigned > 0 ? $assigned : $openTotal,
            'attention_label' => $assigned > 0 ? $assigned . ' assigned case(s)' : ($openTotal > 0 ? $openTotal . ' open case(s)' : 'No open cases'),
            'attention_meta' => 'Monitor assigned cases and track open maintenance work.',
            'stats' => [
                ['id' => 'assigned', 'label' => 'Assigned', 'value' => $assigned, 'tone' => 'primary'],
                ['id' => 'open_total', 'label' => 'Open', 'value' => $openTotal, 'tone' => 'warning'],
                ['id' => 'testing', 'label' => 'Testing', 'value' => $testing, 'tone' => 'accent'],
                ['id' => 'notifications', 'label' => 'Alerts', 'value' => $notificationCount, 'tone' => 'neutral'],
            ],
            'next_item' => null,
            'message' => 'Plan, update, test, and close maintenance cases from the technician workspace.',
        ];
    }

    /**
     * @return list<int>
     */
    protected function picLabIds(string $email): array
    {
        if ($email === '') {
            return [];
        }

        $labs = (new LaboratoryModel())
            ->where('LOWER(TRIM(pic_email)) =', strtolower(trim($email)))
            ->findAll();

        return array_map(static fn(array $lab): int => (int) $lab['id'], $labs);
    }
}
