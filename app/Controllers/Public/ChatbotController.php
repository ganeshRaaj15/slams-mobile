<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use App\Models\BookingModel;
use CodeIgniter\HTTP\ResponseInterface;

class ChatbotController extends BaseController
{
    public function respond(): ResponseInterface
    {
        helper(['auth', 'security']);

        $payload = $this->request->getPost();
        if (!is_array($payload) || empty($payload)) {
            $contentType = strtolower((string)$this->request->getHeaderLine('Content-Type'));
            if (strpos($contentType, 'application/json') !== false) {
                try {
                    $jsonPayload = $this->request->getJSON(true);
                    if (is_array($jsonPayload)) {
                        $payload = $jsonPayload;
                    }
                } catch (\Throwable $e) {
                    $payload = $payload ?? [];
                }
            }
        }

        $message = trim((string)($payload['message'] ?? ''));
        if ($message === '') {
            return $this->response->setJSON([
                'reply' => 'Please enter a question so I can help.',
                'intent' => 'empty',
                'csrfHash' => csrf_hash(),
            ]);
        }

        $role = 'guest';
        $userId = null;
        if (auth()->loggedIn()) {
            $user = auth()->user();
            $userId = $user->id;

            if ($user->inGroup('admin')) {
                $role = 'admin';
            } elseif ($user->inGroup('manager')) {
                $role = 'manager';
            } elseif ($user->inGroup('pic')) {
                $role = 'pic';
            } elseif ($user->inGroup('external')) {
                $role = 'external';
            } elseif ($user->inGroup('student')) {
                $role = 'student';
            }
        }

        $normalized = strtolower($message);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        $reply = '';
        $intent = 'fallback';

        if (preg_match('/\b(help|commands|what can you do|how can you help)\b/', $normalized)) {
            $intent = 'help';
            $reply = $this->buildHelpMessage($role);
        } elseif (preg_match('/\b(my bookings|my booking|upcoming bookings|upcoming)\b/', $normalized)) {
            $intent = 'my_bookings';
            $reply = $this->getUpcomingBookingsReply($userId);
        } elseif (strpos($normalized, 'pending') !== false && strpos($normalized, 'approval') !== false) {
            $intent = 'pending_approvals';
            $reply = $this->getPendingApprovalsReply($role);
        } elseif (strpos($normalized, 'top lab') !== false || strpos($normalized, 'most booked lab') !== false) {
            $intent = 'top_labs';
            $reply = $this->getTopLabsReply();
        } elseif (strpos($normalized, 'total booking') !== false || strpos($normalized, 'booking count') !== false) {
            $intent = 'booking_counts';
            $reply = $this->getBookingCountsReply();
        } elseif (strpos($normalized, 'asset') !== false && (strpos($normalized, 'status') !== false || strpos($normalized, 'available') !== false || strpos($normalized, 'unavailable') !== false)) {
            $intent = 'asset_status';
            $reply = $this->getAssetStatusReply();
        } elseif (strpos($normalized, 'lab count') !== false || strpos($normalized, 'how many labs') !== false) {
            $intent = 'lab_count';
            $reply = $this->getLabCountReply();
        }

        if ($intent === 'fallback') {
            $reply = "I can help with lab insights like total bookings, top labs, asset status, or your upcoming bookings. Type \"help\" to see suggestions.";
        }

        return $this->response->setJSON([
            'reply' => $reply,
            'intent' => $intent,
            'csrfHash' => csrf_hash(),
        ]);
    }

    private function buildHelpMessage(string $role): string
    {
        $lines = [
            'Try asking:',
            '- Total bookings',
            '- Top labs by bookings',
            '- Asset status summary',
            '- My upcoming bookings',
        ];

        if (in_array($role, ['admin', 'manager', 'pic'], true)) {
            $lines[] = '- Pending approvals';
        }

        if ($role === 'guest') {
            $lines[] = 'Log in for role-based insights.';
        }

        return implode("\n", $lines);
    }

    private function getUpcomingBookingsReply(?int $userId): string
    {
        if ($userId === null) {
            return 'Please log in so I can look up your bookings.';
        }

        $db = \Config\Database::connect();
        $rows = $db->table('bookings b')
            ->select('b.date, b.start_time, b.end_time, b.status, l.name AS lab_name')
            ->join('laboratories l', 'l.id = b.lab_id', 'left')
            ->where('b.user_id', $userId)
            ->where('b.date >=', date('Y-m-d'))
            ->orderBy('b.date', 'ASC')
            ->orderBy('b.start_time', 'ASC')
            ->limit(3)
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            return 'No upcoming bookings found.';
        }

        $lines = ['Your next bookings:'];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '- %s %s-%s at %s (%s)',
                $row['date'],
                substr($row['start_time'], 0, 5),
                substr($row['end_time'], 0, 5),
                $row['lab_name'] ?? 'Unknown lab',
                $row['status']
            );
        }

        return implode("\n", $lines);
    }

    private function getPendingApprovalsReply(string $role): string
    {
        if (!in_array($role, ['admin', 'manager', 'pic'], true)) {
            return 'Pending approvals are only available to PICs, managers, and admins.';
        }

        $db = \Config\Database::connect();

        if ($role === 'pic') {
            $count = $db->table('bookings')
                ->where('status', 'PENDING')
                ->where('approved_by_pic', 0)
                ->countAllResults();

            return "PIC approvals pending: {$count}.";
        }

        if ($role === 'manager') {
            $count = $db->table('bookings')
                ->where('status', 'PENDING')
                ->where('approved_by_pic', 1)
                ->where('approved_by_manager', 0)
                ->countAllResults();

            return "Manager approvals pending: {$count}.";
        }

        $pendingPic = $db->table('bookings')
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 0)
            ->countAllResults();

        $pendingManager = $db->table('bookings')
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 1)
            ->where('approved_by_manager', 0)
            ->countAllResults();

        return "Pending approvals: {$pendingPic} awaiting PIC, {$pendingManager} awaiting manager.";
    }

    private function getTopLabsReply(): string
    {
        $db = \Config\Database::connect();
        $rows = $db->table('bookings b')
            ->select('l.name AS lab_name, COUNT(*) AS total')
            ->join('laboratories l', 'l.id = b.lab_id', 'left')
            ->where('b.status', 'APPROVED')
            ->groupBy('l.name')
            ->orderBy('total', 'DESC')
            ->limit(3)
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            return 'No approved bookings yet, so there are no top labs.';
        }

        $lines = ['Top labs by approved bookings:'];
        foreach ($rows as $row) {
            $lines[] = sprintf('- %s: %d', $row['lab_name'] ?? 'Unknown lab', $row['total']);
        }

        return implode("\n", $lines);
    }

    private function getBookingCountsReply(): string
    {
        $bookingModel = new BookingModel();
        $counts = $bookingModel->countByStatus();
        $total = array_sum($counts);

        return sprintf(
            'Total bookings: %d (Approved: %d, Pending: %d, Rejected: %d).',
            $total,
            $counts['approved'] ?? 0,
            ($counts['pending'] ?? 0) + ($counts['pending_mgr'] ?? 0),
            $counts['rejected'] ?? 0
        );
    }

    private function getAssetStatusReply(): string
    {
        $db = \Config\Database::connect();
        $rows = $db->table('assets')
            ->select('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            return 'No assets found to summarize.';
        }

        $counts = [];
        foreach ($rows as $row) {
            $status = $row['status'] ?? 'unknown';
            $counts[$status] = (int)$row['total'];
        }

        $available = $counts['available'] ?? 0;
        $maintenance = $counts['maintenance'] ?? 0;
        $faulty = $counts['faulty'] ?? 0;
        $other = array_sum($counts) - $available - $maintenance - $faulty;

        $lines = [
            "Assets: {$available} available, {$maintenance} in maintenance, {$faulty} faulty.",
        ];

        if ($other > 0) {
            $lines[] = "Other statuses: {$other}.";
        }

        return implode(' ', $lines);
    }

    private function getLabCountReply(): string
    {
        $db = \Config\Database::connect();
        $count = $db->table('laboratories')->countAllResults();

        return "There are {$count} laboratories in the system.";
    }
}
