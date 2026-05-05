<?php

namespace App\Libraries;

use App\Models\BookingModel;
use App\Models\ExternalRequestModel;
use App\Models\LaboratoryModel;
use App\Models\MaintenanceRecordModel;
use App\Models\NotificationModel;

class MobileExperienceBuilder
{
    private static ?array $memoizedState = null;

    public function current(): array
    {
        if (self::$memoizedState !== null) {
            return self::$memoizedState;
        }

        helper('auth');

        $loggedIn = function_exists('auth') && auth()->loggedIn();
        $user = $loggedIn ? auth()->user() : null;

        $state = [
            'loggedIn' => $loggedIn,
            'role' => $this->resolveRole($user),
            'dashboardHref' => '/dashboard',
            'dashboardLabel' => 'Dashboard',
            'alertsBadge' => 0,
            'attentionCount' => 0,
            'attentionLabel' => 'Ready',
            'attentionMeta' => 'No mobile actions need attention right now.',
            'sheetTitle' => 'Mobile Actions',
            'sheetDescription' => 'Fast access to the most important tasks in SLAMS.',
            'quickActions' => [
                [
                    'href' => '/laboratories',
                    'icon' => 'bi-building',
                    'label' => 'Browse Labs',
                    'meta' => 'Check availability and plan a booking.',
                ],
                [
                    'href' => '/assets',
                    'icon' => 'bi-box-seam',
                    'label' => 'View Assets',
                    'meta' => 'Inspect equipment and quantities quickly.',
                ],
                [
                    'href' => '/contact',
                    'icon' => 'bi-envelope',
                    'label' => 'Contact',
                    'meta' => 'Reach the lab team when a request needs a person.',
                ],
                [
                    'href' => '/login',
                    'icon' => 'bi-box-arrow-in-right',
                    'label' => 'Login',
                    'meta' => 'Sign in to manage bookings, requests, and alerts.',
                ],
            ],
        ];

        if (! $loggedIn || ! $user) {
            return self::$memoizedState = $state;
        }

        $notificationCount = (new NotificationModel())
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->countAllResults();

        $state['alertsBadge'] = (int) $notificationCount;

        $role = $state['role'];
        $state['dashboardHref'] = $this->dashboardHrefForRole($role);
        $state['dashboardLabel'] = $this->dashboardLabelForRole($role);

        switch ($role) {
            case 'external':
                $openRequests = (new ExternalRequestModel())
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['submitted', 'under_review', 'needs_information', 'approved_for_scheduling'])
                    ->countAllResults();

                $state['attentionCount'] = $openRequests;
                $state['attentionLabel'] = $openRequests > 0 ? $openRequests . ' active request' . ($openRequests === 1 ? '' : 's') : 'Request flow clear';
                $state['attentionMeta'] = 'Submit a new lab-use request or track ongoing reviews.';
                $state['sheetTitle'] = 'External Request Actions';
                $state['sheetDescription'] = 'Track request status and send a new lab-use request without jumping through the booking flow.';
                $state['quickActions'] = [
                    [
                        'href' => '/dashboard/external/request',
                        'icon' => 'bi-plus-circle',
                        'label' => 'New Request',
                        'meta' => 'Submit a structured external access request.',
                    ],
                    [
                        'href' => '/dashboard/external',
                        'icon' => 'bi-clipboard-check',
                        'label' => 'Track Requests',
                        'meta' => 'See review progress and update requests when asked.',
                        'badge' => $openRequests,
                    ],
                    [
                        'href' => '/dashboard/notifications',
                        'icon' => 'bi-bell',
                        'label' => 'Alerts',
                        'meta' => 'Open review updates and status notifications.',
                        'badge' => $notificationCount,
                    ],
                    [
                        'href' => '/laboratories',
                        'icon' => 'bi-building',
                        'label' => 'Browse Labs',
                        'meta' => 'Review facilities before requesting access.',
                    ],
                ];
                break;

            case 'pic':
                $picLabIds = $this->picLabIds((string) $user->email);
                $pendingApprovals = $this->countPicPendingApprovals($picLabIds);
                $externalReviewCount = $this->countExternalReviewQueue($role, $picLabIds);
                $state['attentionCount'] = $pendingApprovals + $externalReviewCount;
                $state['attentionLabel'] = $state['attentionCount'] > 0 ? $state['attentionCount'] . ' items waiting' : 'Queue is clear';
                $state['attentionMeta'] = 'Approve bookings for your labs and review external intake from one place.';
                $state['sheetTitle'] = 'PIC Mobile Actions';
                $state['sheetDescription'] = 'Jump straight into booking approvals and external review work from your phone.';
                $state['quickActions'] = [
                    [
                        'href' => '/dashboard/pic',
                        'icon' => 'bi-check2-square',
                        'label' => 'Booking Queue',
                        'meta' => 'Approve or reject booking requests for assigned labs.',
                        'badge' => $pendingApprovals,
                    ],
                    [
                        'href' => '/dashboard/external-requests',
                        'icon' => 'bi-clipboard-data',
                        'label' => 'External Requests',
                        'meta' => 'Review external access intake for your labs.',
                        'badge' => $externalReviewCount,
                    ],
                    [
                        'href' => '/dashboard/notifications',
                        'icon' => 'bi-bell',
                        'label' => 'Alerts',
                        'meta' => 'Catch booking and maintenance updates quickly.',
                        'badge' => $notificationCount,
                    ],
                    [
                        'href' => '/laboratories',
                        'icon' => 'bi-building',
                        'label' => 'Lab Directory',
                        'meta' => 'Open lab details while reviewing a request.',
                    ],
                ];
                break;

            case 'manager':
                $pendingManagerApprovals = $this->countManagerPendingApprovals();
                $managerExternalReviews = $this->countExternalReviewQueue($role, []);
                $state['attentionCount'] = $pendingManagerApprovals + $managerExternalReviews;
                $state['attentionLabel'] = $state['attentionCount'] > 0 ? $state['attentionCount'] . ' reviews pending' : 'Manager queue is clear';
                $state['attentionMeta'] = 'Approve non-FKMP bookings and keep external review moving.';
                $state['sheetTitle'] = 'Manager Mobile Actions';
                $state['sheetDescription'] = 'Handle approvals, external intake, and alerts with fewer taps.';
                $state['quickActions'] = [
                    [
                        'href' => '/dashboard/manager',
                        'icon' => 'bi-clipboard-check',
                        'label' => 'Approval Queue',
                        'meta' => 'Review bookings that already passed PIC approval.',
                        'badge' => $pendingManagerApprovals,
                    ],
                    [
                        'href' => '/dashboard/external-requests',
                        'icon' => 'bi-clipboard-data',
                        'label' => 'External Requests',
                        'meta' => 'Review external access requests needing oversight.',
                        'badge' => $managerExternalReviews,
                    ],
                    [
                        'href' => '/dashboard/notifications',
                        'icon' => 'bi-bell',
                        'label' => 'Alerts',
                        'meta' => 'Open notifications without hunting through menus.',
                        'badge' => $notificationCount,
                    ],
                    [
                        'href' => '/laboratories',
                        'icon' => 'bi-building',
                        'label' => 'Lab Directory',
                        'meta' => 'Check rooms and context while approving.',
                    ],
                ];
                break;

            case 'admin':
                $adminPending = $this->countAdminPendingApprovals();
                $adminExternalReviews = $this->countExternalReviewQueue($role, []);
                $state['attentionCount'] = $adminPending + $adminExternalReviews;
                $state['attentionLabel'] = $state['attentionCount'] > 0 ? $state['attentionCount'] . ' admin tasks waiting' : 'Admin queue is clear';
                $state['attentionMeta'] = 'Keep approvals, external intake, and user operations moving on mobile.';
                $state['sheetTitle'] = 'Admin Mobile Actions';
                $state['sheetDescription'] = 'Reach the highest-traffic admin flows from a single mobile sheet.';
                $state['quickActions'] = [
                    [
                        'href' => '/dashboard/approvals',
                        'icon' => 'bi-check2-all',
                        'label' => 'Approval Queue',
                        'meta' => 'Open the consolidated booking approval screen.',
                        'badge' => $adminPending,
                    ],
                    [
                        'href' => '/dashboard/external-requests',
                        'icon' => 'bi-clipboard-data',
                        'label' => 'External Requests',
                        'meta' => 'Review external requests before they spill into manual handling.',
                        'badge' => $adminExternalReviews,
                    ],
                    [
                        'href' => '/dashboard/notifications',
                        'icon' => 'bi-bell',
                        'label' => 'Alerts',
                        'meta' => 'See what changed across the system.',
                        'badge' => $notificationCount,
                    ],
                    [
                        'href' => '/admin/users',
                        'icon' => 'bi-people',
                        'label' => 'Users',
                        'meta' => 'Jump to user management for operational fixes.',
                    ],
                ];
                break;

            case 'technician':
                $maintenanceModel = new MaintenanceRecordModel();
                $openStatuses = $maintenanceModel->openStatuses();
                $openCases = (new MaintenanceRecordModel())
                    ->whereIn('status', $openStatuses)
                    ->countAllResults();
                $assignedToMe = (new MaintenanceRecordModel())
                    ->where('assigned_technician_id', $user->id)
                    ->whereIn('status', $openStatuses)
                    ->countAllResults();

                $state['attentionCount'] = $assignedToMe > 0 ? $assignedToMe : $openCases;
                $state['attentionLabel'] = $assignedToMe > 0 ? $assignedToMe . ' assigned case' . ($assignedToMe === 1 ? '' : 's') : ($openCases > 0 ? $openCases . ' open cases' : 'No open cases');
                $state['attentionMeta'] = 'Move repairs forward without navigating the full technician dashboard.';
                $state['sheetTitle'] = 'Technician Mobile Actions';
                $state['sheetDescription'] = 'Open the maintenance queue, jump to assigned work, or schedule a new case from your phone.';
                $state['quickActions'] = [
                    [
                        'href' => '/dashboard/technician',
                        'icon' => 'bi-tools',
                        'label' => 'My Queue',
                        'meta' => 'Open technician stats and recent maintenance cases.',
                        'badge' => $assignedToMe,
                    ],
                    [
                        'href' => '/technician/maintenance',
                        'icon' => 'bi-list-check',
                        'label' => 'All Cases',
                        'meta' => 'Review the full maintenance workflow queue.',
                        'badge' => $openCases,
                    ],
                    [
                        'href' => '/technician/maintenance/create',
                        'icon' => 'bi-plus-square',
                        'label' => 'Schedule Case',
                        'meta' => 'Create a new maintenance record quickly.',
                    ],
                    [
                        'href' => '/dashboard/notifications',
                        'icon' => 'bi-bell',
                        'label' => 'Alerts',
                        'meta' => 'See new assignments and workflow changes.',
                        'badge' => $notificationCount,
                    ],
                ];
                break;

            case 'student':
            default:
                $activeBookings = (new BookingModel())
                    ->where('user_id', $user->id)
                    ->whereIn('status', BookingModel::ACTIVE_STATUSES)
                    ->countAllResults();

                $state['attentionCount'] = max($notificationCount, $activeBookings);
                $state['attentionLabel'] = $activeBookings > 0 ? $activeBookings . ' active booking' . ($activeBookings === 1 ? '' : 's') : 'Ready to book';
                $state['attentionMeta'] = 'Keep bookings, issue reports, and alerts within one thumb reach.';
                $state['sheetTitle'] = 'Mobile Booking Actions';
                $state['sheetDescription'] = 'Reach the high-frequency student and staff tasks without digging through the dashboard.';
                $state['quickActions'] = [
                    [
                        'href' => '/laboratories',
                        'icon' => 'bi-building',
                        'label' => 'Browse Labs',
                        'meta' => 'Check availability and start a reservation.',
                    ],
                    [
                        'href' => '/dashboard/student',
                        'icon' => 'bi-journal-bookmark',
                        'label' => 'My Bookings',
                        'meta' => 'Open booking history and active reservations.',
                        'badge' => $activeBookings,
                    ],
                    [
                        'href' => '/dashboard/report-issue',
                        'icon' => 'bi-tools',
                        'label' => 'Report Issue',
                        'meta' => 'Send an asset issue to the technician workflow.',
                    ],
                    [
                        'href' => '/dashboard/notifications',
                        'icon' => 'bi-bell',
                        'label' => 'Alerts',
                        'meta' => 'Catch approvals, rejections, and reminders.',
                        'badge' => $notificationCount,
                    ],
                ];
                break;
        }

        return self::$memoizedState = $state;
    }

    private function resolveRole($user): string
    {
        if (! $user) {
            return 'guest';
        }

        if ($user->inGroup('admin')) {
            return 'admin';
        }
        if ($user->inGroup('technician')) {
            return 'technician';
        }
        if ($user->inGroup('pic')) {
            return 'pic';
        }
        if ($user->inGroup('manager')) {
            return 'manager';
        }
        if ($user->inGroup('external')) {
            return 'external';
        }

        return 'student';
    }

    private function dashboardHrefForRole(string $role): string
    {
        return match ($role) {
            'admin' => '/dashboard/admin',
            'technician' => '/dashboard/technician',
            'pic' => '/dashboard/pic',
            'manager' => '/dashboard/manager',
            'external' => '/dashboard/external',
            'student' => '/dashboard/student',
            default => '/dashboard',
        };
    }

    private function dashboardLabelForRole(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'technician' => 'Tech',
            'pic' => 'PIC',
            'manager' => 'Manager',
            'external' => 'Requests',
            'student' => 'Dashboard',
            default => 'Dashboard',
        };
    }

    private function picLabIds(string $email): array
    {
        if ($email === '') {
            return [];
        }

        $labs = (new LaboratoryModel())
            ->where('LOWER(TRIM(pic_email)) =', strtolower(trim($email)))
            ->findAll();

        return array_map(static fn(array $lab): int => (int) $lab['id'], $labs);
    }

    private function countPicPendingApprovals(array $labIds): int
    {
        if ($labIds === []) {
            return 0;
        }

        return (new BookingModel())
            ->whereIn('lab_id', $labIds)
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 0)
            ->countAllResults();
    }

    private function countManagerPendingApprovals(): int
    {
        return (new BookingModel())
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 1)
            ->where('approved_by_manager', 0)
            ->countAllResults();
    }

    private function countAdminPendingApprovals(): int
    {
        return (new BookingModel())
            ->where('status', 'PENDING')
            ->groupStart()
                ->where('approved_by_pic', 0)
                ->orGroupStart()
                    ->where('approved_by_pic', 1)
                    ->where('approved_by_manager', 0)
                ->groupEnd()
            ->groupEnd()
            ->countAllResults();
    }

    private function countExternalReviewQueue(string $role, array $labIds): int
    {
        $builder = (new ExternalRequestModel())
            ->whereIn('status', ['submitted', 'under_review']);

        if ($role === 'pic') {
            if ($labIds === []) {
                return 0;
            }

            $builder->whereIn('lab_id', $labIds);
        }

        return $builder->countAllResults();
    }
}
