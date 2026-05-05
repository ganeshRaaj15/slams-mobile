<?php

namespace App\Libraries;

use App\Models\EmailLogModel;
use App\Models\NotificationModel;
use Config\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

class NotificationService
{
    protected \CodeIgniter\Database\BaseConnection $db;
    protected NotificationModel $notificationModel;
    protected EmailLogModel $emailLogModel;
    protected DateTimeZone $timezone;

    public static function dispatchSafely(callable $callback, string $context = 'notification'): void
    {
        try {
            $callback(new self());
        } catch (\Throwable $e) {
            log_message('error', 'Notification dispatch failed [' . $context . ']: ' . $e->getMessage());
        }
    }

    public function __construct()
    {
        $this->db = Database::connect();
        $this->notificationModel = new NotificationModel();
        $this->emailLogModel = new EmailLogModel();
        $this->timezone = new DateTimeZone('Asia/Kuala_Lumpur');
    }

    public function notifyBookingSubmitted(array $booking): void
    {
        $context = $this->bookingContext((int) ($booking['id'] ?? 0), $booking);
        if (! $context) {
            return;
        }

        $studentLink = '/dashboard/student?focus_booking=' . (int) $context['id'];
        $approvalLink = '/dashboard/approvals?focus_booking=' . (int) $context['id'];
        $picPendingCount = $this->pendingPicCountForEmail((string) ($context['pic_email'] ?? ''));

        $studentMessage = 'Your booking request for ' . $this->bookingDescriptor($context) . ' has been successfully sent to the PIC for review.';
        $picMessage = 'A new booking request for ' . $this->bookingDescriptor($context) . ' is waiting for your approval. You currently have ' . $picPendingCount . ' booking request(s) requiring PIC attention.';

        $this->createUserNotifications($this->compactIds([(int) ($context['user_id'] ?? 0)]), 'booking', 'Booking Request Sent To PIC', $studentMessage, $studentLink, 'booking', (int) $context['id']);
        $this->createUserNotifications($this->compactIds([$this->findUserIdByEmail($context['pic_email'] ?? '')]), 'booking', 'New Booking Request Received', $picMessage, $approvalLink, 'booking', (int) $context['id']);

        $this->sendEmail(
            [$context['applicant_email'] ?? null],
            'FKMP Smart Lab: Booking Request Sent To PIC',
            $this->emailTemplate('Booking Request Submitted', [
                'Your booking request has been successfully sent to the PIC for review.',
                $this->bookingDetailBlock($context),
            ], site_url($studentLink), 'Open Booking Details')
        );

        $this->sendEmail(
            [$context['pic_email'] ?? null],
            'FKMP Smart Lab: New Booking Request Received',
            $this->emailTemplate('New Booking Request', [
                'A booking request has been submitted and is waiting for your review as PIC.',
                'You currently have ' . $picPendingCount . ' booking request(s) requiring your attention.',
                $this->bookingDetailBlock($context),
            ], site_url($approvalLink), 'Open Approval Queue')
        );
    }

    public function notifyBookingPendingManager(array $booking): void
    {
        $context = $this->bookingContext((int) ($booking['id'] ?? 0), $booking);
        if (! $context) {
            return;
        }

        $studentLink = '/dashboard/student?focus_booking=' . (int) $context['id'];
        $approvalLink = '/dashboard/approvals?focus_booking=' . (int) $context['id'];
        $managerPendingCount = $this->pendingManagerCount();

        $studentMessage = 'Your booking request for ' . $this->bookingDescriptor($context) . ' has been approved by the PIC and is now pending Lab Manager approval.';
        $managerMessage = 'A booking request for ' . $this->bookingDescriptor($context) . ' has been approved by the PIC and now needs Lab Manager approval. There are currently ' . $managerPendingCount . ' booking request(s) requiring Lab Manager attention.';

        $this->createUserNotifications($this->compactIds([(int) ($context['user_id'] ?? 0)]), 'booking', 'Booking Pending Lab Manager Approval', $studentMessage, $studentLink, 'booking', (int) $context['id']);
        $this->createUserNotifications($this->groupUserIds('manager'), 'booking', 'Booking Needs Lab Manager Approval', $managerMessage, $approvalLink, 'booking', (int) $context['id']);

        $this->sendEmail(
            [$context['applicant_email'] ?? null],
            'FKMP Smart Lab: PIC Approved - Pending Lab Manager Approval',
            $this->emailTemplate('PIC Approved Your Booking', [
                'Your booking request has been approved by the PIC and is now waiting for Lab Manager approval.',
                $this->bookingDetailBlock($context),
            ], site_url($studentLink), 'Open Booking Details')
        );

        $managerEmails = array_merge([$this->settingValue('system.lab_manager_email')], $this->emailsForUserIds($this->groupUserIds('manager')));
        $this->sendEmail(
            $managerEmails,
            'FKMP Smart Lab: Booking Awaiting Lab Manager Approval',
            $this->emailTemplate('Booking Needs Your Approval', [
                'A non-FKMP booking request has been approved by the PIC and is now waiting for Lab Manager approval.',
                'There are currently ' . $managerPendingCount . ' booking request(s) requiring your attention.',
                $this->bookingDetailBlock($context),
            ], site_url($approvalLink), 'Open Approval Queue')
        );
    }

    public function notifyBookingApproved(array $booking): void
    {
        $context = $this->bookingContext((int) ($booking['id'] ?? 0), $booking);
        if (! $context) {
            return;
        }

        $studentLink = '/dashboard/student?focus_booking=' . (int) $context['id'];
        $calendarUrl = $this->googleCalendarLink($context);
        $message = 'Your booking request for ' . $this->bookingDescriptor($context) . ' has been approved.';

        $this->createUserNotifications($this->compactIds([(int) ($context['user_id'] ?? 0)]), 'booking', 'Booking Approved', $message, $studentLink, 'booking', (int) $context['id']);

        $this->sendEmail(
            [$context['applicant_email'] ?? null],
            'FKMP Smart Lab: Booking Approved',
            $this->emailTemplate('Booking Approved', [
                'Your booking request has been approved.',
                $this->bookingDetailBlock($context),
                'You can add this booking to your calendar using the attached calendar invite, or use the Google Calendar button below.',
            ], $calendarUrl, 'Add To Google Calendar'),
            $this->calendarAttachment($context)
        );
    }

    public function notifyBookingRejected(array $booking, string $rejectedBy = ''): void
    {
        $context = $this->bookingContext((int) ($booking['id'] ?? 0), $booking);
        if (! $context) {
            return;
        }

        $studentLink = '/dashboard/student?focus_booking=' . (int) $context['id'];
        $label = $rejectedBy !== '' ? ' by ' . $rejectedBy : '';
        $message = 'Your booking request for ' . $this->bookingDescriptor($context) . ' has been rejected' . $label . '.';

        $this->createUserNotifications($this->compactIds([(int) ($context['user_id'] ?? 0)]), 'booking', 'Booking Rejected', $message, $studentLink, 'booking', (int) $context['id']);

        $this->sendEmail(
            [$context['applicant_email'] ?? null],
            'FKMP Smart Lab: Booking Rejected',
            $this->emailTemplate('Booking Rejected', [
                $message,
                $this->bookingDetailBlock($context),
            ], site_url($studentLink), 'Open Booking Details')
        );
    }

    public function notifyUpcomingBookingReminder(array $booking): void
    {
        $context = $this->bookingContext((int) ($booking['id'] ?? 0), $booking);
        if (! $context) {
            return;
        }

        $studentLink = '/dashboard/student?focus_booking=' . (int) $context['id'];
        $calendarUrl = $this->googleCalendarLink($context);
        $message = 'Reminder: your approved booking for ' . $this->bookingDescriptor($context) . ' is coming up soon.';

        if (! $this->reminderAlreadySent((int) $context['id'])) {
            $this->createUserNotifications($this->compactIds([(int) ($context['user_id'] ?? 0)]), 'booking_reminder', 'Upcoming Booking Reminder', $message, $studentLink, 'booking', (int) $context['id']);
        }

        $this->sendEmail(
            [$context['applicant_email'] ?? null],
            'FKMP Smart Lab: Upcoming Booking Reminder',
            $this->emailTemplate('Upcoming Booking Reminder', [
                'This is a reminder that your approved booking is coming up soon.',
                $this->bookingDetailBlock($context),
                'The calendar invite is attached again for your phone, tablet, or computer calendar.',
            ], $calendarUrl, 'Add To Google Calendar'),
            $this->calendarAttachment($context)
        );
    }

    public function sendUpcomingBookingReminders(int $hoursAhead = 24): int
    {
        $now = new DateTimeImmutable('now', $this->timezone);
        $cutoff = $now->add(new DateInterval('PT' . max($hoursAhead, 1) . 'H'));

        $bookings = $this->db->table('bookings b')
            ->select('b.id, b.date, b.start_time, b.status')
            ->where('b.status', 'APPROVED')
            ->where('b.date >=', $now->format('Y-m-d'))
            ->where('b.date <=', $cutoff->format('Y-m-d'))
            ->get()
            ->getResultArray();

        $sent = 0;
        foreach ($bookings as $booking) {
            $startAt = $this->bookingStartDateTime($booking);
            if (! $startAt || $startAt < $now || $startAt > $cutoff || $this->reminderAlreadySent((int) $booking['id'])) {
                continue;
            }

            try {
                $this->notifyUpcomingBookingReminder($booking);
                $sent++;
            } catch (\Throwable $e) {
                log_message('error', 'Booking reminder notification failed for booking #' . (int) $booking['id'] . ': ' . $e->getMessage());
            }
        }

        return $sent;
    }

    public function notifyMaintenanceReported(int $maintenanceId): void
    {
        $context = $this->maintenanceContext($maintenanceId);
        if (! $context) {
            return;
        }

        $technicianLink = '/technician/maintenance/edit/' . $maintenanceId;
        $reporterLink = '/dashboard/report-issue';
        $unitText = ! empty($context['unit_reference']) ? ' Unit: ' . $context['unit_reference'] . '.' : '';
        $technicianMessage = 'A maintenance issue was reported for ' . $context['asset_name'] . ' in ' . $context['lab_name'] . '.' . $unitText;
        $reporterMessage = 'Your issue report for ' . $context['asset_name'] . ' in ' . $context['lab_name'] . ' has been submitted and is waiting for technician review.';

        $this->createUserNotifications($this->groupUserIds('technician'), 'maintenance', 'New Maintenance Issue Reported', $technicianMessage, $technicianLink, 'maintenance', $maintenanceId);
        $this->createUserNotifications($this->compactIds([(int) ($context['reported_by'] ?? 0)]), 'maintenance', 'Issue Report Submitted', $reporterMessage, $reporterLink, 'maintenance', $maintenanceId);

        $this->sendEmail(
            $this->emailsForUserIds($this->groupUserIds('technician')),
            'FKMP Smart Lab: Maintenance Issue Reported',
            $this->emailTemplate('New Maintenance Issue', [
                'A new maintenance issue has been reported and needs technician attention.',
                'Case: ' . ($context['title'] ?? 'Maintenance Issue'),
                'Laboratory: ' . ($context['lab_name'] ?? '-'),
                'Equipment: ' . ($context['asset_name'] ?? '-'),
                $unitText !== '' ? trim($unitText) : null,
            ], site_url($technicianLink), 'Open Maintenance Case')
        );

        $this->sendEmail(
            [$context['reporter_email'] ?? null],
            'FKMP Smart Lab: Issue Report Submitted',
            $this->emailTemplate('Issue Report Submitted', [
                'Your maintenance issue report has been submitted successfully.',
                'Case: ' . ($context['title'] ?? 'Maintenance Issue'),
                'Laboratory: ' . ($context['lab_name'] ?? '-'),
                'Equipment: ' . ($context['asset_name'] ?? '-'),
            ], site_url($reporterLink), 'View Your Reports')
        );
    }

    public function notifyMaintenanceScheduled(int $maintenanceId): void
    {
        $context = $this->maintenanceContext($maintenanceId);
        if (! $context) {
            return;
        }

        $reporterLink = '/dashboard/report-issue';
        $message = 'Maintenance for ' . $context['asset_name'] . ' in ' . $context['lab_name'] . ' has been accepted and scheduled.';
        if (! empty($context['scheduled_for'])) {
            $message .= ' Scheduled for ' . date('d M Y H:i', strtotime($context['scheduled_for'])) . '.';
        }

        $this->createUserNotifications($this->compactIds([(int) ($context['reported_by'] ?? 0)]), 'maintenance', 'Maintenance Scheduled', $message, $reporterLink, 'maintenance', $maintenanceId);
        $this->sendEmail([$context['reporter_email'] ?? null], 'FKMP Smart Lab: Maintenance Scheduled', $this->emailTemplate('Maintenance Scheduled', [$message], site_url($reporterLink), 'View Your Reports'), null, ['entity_type' => 'maintenance', 'entity_id' => (int) $maintenanceId, 'notification_type' => 'maintenance']);
    }

    public function notifyMaintenanceCompleted(int $maintenanceId): void
    {
        $context = $this->maintenanceContext($maintenanceId);
        if (! $context) {
            return;
        }

        $message = 'Maintenance for ' . $context['asset_name'] . ' in ' . $context['lab_name'] . ' has been completed and the equipment is available again.';
        $this->createUserNotifications($this->compactIds([(int) ($context['reported_by'] ?? 0)]), 'maintenance', 'Maintenance Completed', $message, '/dashboard/report-issue', 'maintenance', $maintenanceId);
        $this->createUserNotifications($this->compactIds([$this->findUserIdByEmail($context['pic_email'] ?? '')]), 'maintenance', 'Equipment Available Again', $message, '/dashboard/pic', 'maintenance', $maintenanceId);
        $this->sendEmail([$context['reporter_email'] ?? null, $context['pic_email'] ?? null], 'FKMP Smart Lab: Maintenance Completed', $this->emailTemplate('Maintenance Completed', [$message]), null, ['entity_type' => 'maintenance', 'entity_id' => (int) $maintenanceId, 'notification_type' => 'maintenance']);
    }

    public function notifyMaintenanceDue(array $context): void
    {
        $assetId = (int) ($context['asset_id'] ?? 0);
        if ($assetId <= 0) {
            return;
        }

        $assetName = $context['name'] ?? $context['asset_name'] ?? 'Equipment';
        $labName = $context['lab_name'] ?? $context['laboratory_name'] ?? '';
        $lastCompleted = $context['last_completed_at'] ?? null;
        $nextDue = $context['next_due_at'] ?? null;
        $intervalDays = (int) ($context['interval_days'] ?? 0);
        $basis = $context['basis'] ?? '';
        $riskPercent = (int) ($context['risk_percent'] ?? 0);
        $decisionLabel = trim((string) ($context['decision_label'] ?? ''));
        $reasons = $context['reasons'] ?? [];

        $dueText = '';
        if ($nextDue instanceof \DateTimeImmutable) {
            $dueText = $nextDue->format('d M Y');
        } elseif (is_string($nextDue) && $nextDue !== '') {
            $dueText = date('d M Y', strtotime($nextDue));
        }

        $message = 'Preventive maintenance is due soon for ' . $assetName;
        if ($labName !== '') {
            $message .= ' in ' . $labName;
        }
        if ($dueText !== '') {
            $message .= '. Due by ' . $dueText . '.';
        } else {
            $message .= '.';
        }

        if ($lastCompleted instanceof \DateTimeImmutable) {
            $message .= ' Last completed on ' . $lastCompleted->format('d M Y') . '.';
        } elseif (is_string($lastCompleted) && $lastCompleted !== '') {
            $message .= ' Last completed on ' . date('d M Y', strtotime($lastCompleted)) . '.';
        }

        if ($intervalDays > 0) {
            $months = max((int) round($intervalDays / 30), 1);
            $message .= ' Estimated cycle: ~' . $months . ' month(s) (' . ($basis === 'average' ? 'average' : 'default') . ').';
        }

        if ($decisionLabel !== '') {
            $message .= ' Recommended action: ' . $decisionLabel . '.';
        }

        if ($riskPercent > 0) {
            $message .= ' Model risk score: ' . $riskPercent . '%.';
        }

        if (is_array($reasons) && $reasons !== []) {
            $message .= ' Reason: ' . trim((string) $reasons[0]);
        }

        $link = '/technician/maintenance?asset_id=' . $assetId;
        $this->createUserNotifications(
            $this->groupUserIds('technician'),
            'maintenance_due',
            'Maintenance Due Soon',
            $message,
            $link,
            'asset',
            $assetId
        );

        $emails = $this->emailsForUserIds($this->groupUserIds('technician'));
        if ($emails !== []) {
            $this->sendEmail(
                $emails,
                'FKMP Smart Lab: Preventive Maintenance Due Soon',
                $this->emailTemplate('Maintenance Due Soon', [
                    $message,
                    'Open the maintenance queue to plan the visit.',
                ], site_url($link), 'Open Maintenance Queue'),
                null,
                ['entity_type' => 'asset', 'entity_id' => $assetId, 'notification_type' => 'maintenance_due']
            );
        }
    }

    protected function reminderAlreadySent(int $bookingId): bool
    {
        return $this->notificationModel
            ->where('type', 'booking_reminder')
            ->where('entity_type', 'booking')
            ->where('entity_id', $bookingId)
            ->countAllResults() > 0;
    }

    protected function pendingPicCountForEmail(string $picEmail): int
    {
        $picEmail = strtolower(trim($picEmail));
        if ($picEmail === '') {
            return 0;
        }

        return $this->db->table('bookings b')
            ->join('laboratories l', 'l.id = b.lab_id', 'inner')
            ->where('LOWER(TRIM(l.pic_email)) =', $picEmail)
            ->where('b.status', 'PENDING')
            ->where('b.approved_by_pic', 0)
            ->countAllResults();
    }

    protected function pendingManagerCount(): int
    {
        return $this->db->table('bookings')
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 1)
            ->where('approved_by_manager', 0)
            ->where('approval_flow !=', 'FKMP_APPROVAL')
            ->countAllResults();
    }

    protected function bookingContext(int $bookingId, array $fallback = []): ?array
    {
        if ($bookingId <= 0) {
            return null;
        }

        $row = $this->db->table('bookings b')
            ->select('b.*, l.name AS lab_name, l.room AS lab_room, l.pic_email, f.name_en AS faculty_name, f.is_fkmp')
            ->join('laboratories l', 'l.id = b.lab_id', 'left')
            ->join('faculties f', 'f.id = b.faculty_id', 'left')
            ->where('b.id', $bookingId)
            ->get()
            ->getRowArray();

        if (! $row) {
            $row = $fallback;
        }
        if (! $row) {
            return null;
        }

        $row['applicant_email'] = $this->bookingApplicantEmail($row);
        return $row;
    }

    protected function bookingApplicantEmail(array $booking): ?string
    {
        if (! empty($booking['user_id'])) {
            $email = $this->emailForUserId((int) $booking['user_id']);
            if ($email) {
                return $email;
            }
        }

        $applicant = $this->db->table('booking_applicants')
            ->select('email')
            ->where('booking_id', (int) ($booking['id'] ?? 0))
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        return $applicant['email'] ?? null;
    }

    protected function maintenanceContext(int $maintenanceId): ?array
    {
        $row = $this->db->table('maintenance_records mr')
            ->select('mr.*, a.name AS asset_name, l.name AS lab_name, l.pic_email')
            ->join('assets a', 'a.id = mr.asset_id', 'left')
            ->join('laboratories l', 'l.id = a.lab_id', 'left')
            ->where('mr.id', $maintenanceId)
            ->get()
            ->getRowArray();

        if (! $row) {
            return null;
        }

        $row['reporter_email'] = $this->emailForUserId((int) ($row['reported_by'] ?? 0));
        return $row;
    }

    protected function createUserNotifications(array $userIds, string $type, string $title, string $message, ?string $link = null, ?string $entityType = null, ?int $entityId = null): void
    {
        $rows = [];
        foreach (array_unique($userIds) as $userId) {
            if ($userId <= 0) {
                continue;
            }
            $rows[] = [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'is_read' => 0,
            ];
        }

        if ($rows !== []) {
            (new UserNotificationDispatcher($this->notificationModel))->dispatch($rows);
        }
    }

    protected function sendEmail(array $emails, string $subject, string $message, ?array $attachment = null, ?array $context = null): void
    {
        $recipients = array_values(array_unique(array_filter(array_map(static fn($email) => is_string($email) ? strtolower(trim($email)) : '', $emails))));
        if ($recipients === []) {
            return;
        }

        try {
            $email = service('email');
            $email->clear(true);
            $email->setTo($recipients);
            $email->setSubject($subject);
            $email->setMessage($message);
            if ($attachment && ! empty($attachment['content']) && ! empty($attachment['filename'])) {
                $email->attach(
                    $attachment['content'],
                    'attachment',
                    $attachment['filename'],
                    $attachment['mime'] ?? 'text/calendar'
                );
            }
            if (! $email->send()) {
                $debug = $this->emailDebugSummary($email);
                log_message(
                    'error',
                    'Notification email send failed to ' . implode(', ', $recipients) . ' for subject "' . $subject . '". ' . $debug
                );
                $this->logEmail($recipients, $subject, $this->emailFailurePreview($message, $debug), $attachment, $context);
                return;
            }

            $this->logEmail($recipients, $subject, $message, $attachment, $context);
        } catch (\Throwable $e) {
            log_message('error', 'Notification email error: ' . $e->getMessage());
            $this->logEmail($recipients, $subject, $this->emailFailurePreview($message, $e->getMessage()), $attachment, $context);
        }
    }

    protected function emailDebugSummary($email): string
    {
        try {
            $debug = strip_tags((string) $email->printDebugger(['headers']));
            $debug = preg_replace('/\s+/', ' ', $debug) ?: '';
            $debug = trim($debug);
            if ($debug !== '') {
                return strlen($debug) > 500 ? substr($debug, 0, 500) . '...' : $debug;
            }
        } catch (\Throwable $e) {
            return 'Email service returned false and debugger was unavailable: ' . $e->getMessage();
        }

        return 'Email service returned false. Check Email configuration if physical email delivery is required.';
    }

    protected function emailFailurePreview(string $message, string $reason): string
    {
        $reason = trim($reason);
        if (strlen($reason) > 240) {
            $reason = substr($reason, 0, 240) . '...';
        }

        $note = '<div style="font-family:Arial,sans-serif;font-size:13px;line-height:1.5;color:#92400e;background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;padding:10px 12px;margin:0 0 14px">';
        $note .= '<strong>Delivery not confirmed.</strong> The in-app notification was still created. Configure SMTP/mail settings only when real email delivery is required.';
        if ($reason !== '') {
            $note .= '<br><span>Reason: ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $note .= '</div>';

        return $note . $message;
    }

    protected function logEmail(array $recipients, string $subject, string $message, ?array $attachment = null, ?array $context = null): void
    {
        $rows = [];
        $now = date('Y-m-d H:i:s');

        foreach ($recipients as $recipient) {
            $recipient = strtolower(trim((string) $recipient));
            if ($recipient === '') {
                continue;
            }

            $userId = $this->findUserIdByEmail($recipient);
            $rows[] = [
                'user_id' => $userId > 0 ? $userId : null,
                'to_email' => $recipient,
                'subject' => $subject,
                'body' => $message,
                'notification_type' => $context['notification_type'] ?? null,
                'entity_type' => $context['entity_type'] ?? null,
                'entity_id' => isset($context['entity_id']) ? (int) $context['entity_id'] : null,
                'has_attachment' => $attachment ? 1 : 0,
                'attachment_name' => $attachment['filename'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        try {
            $this->emailLogModel->insertBatch($rows);
        } catch (\Throwable $e) {
            log_message('error', 'Notification email log error: ' . $e->getMessage());
        }
    }

    protected function emailTemplate(string $heading, array $paragraphs, ?string $actionUrl = null, ?string $actionText = null): string
    {
        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#1f2937">';
        $html .= '<h2 style="margin:0 0 16px;color:#1d4ed8">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h2>';
        foreach ($paragraphs as $paragraph) {
            if ($paragraph === null || trim((string) $paragraph) === '') {
                continue;
            }
            $html .= '<p style="margin:0 0 12px">' . nl2br(htmlspecialchars((string) $paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
        }
        if ($actionUrl && $actionText) {
            $html .= '<p style="margin:20px 0 0"><a href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 16px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600">' . htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }
        $html .= '</div>';
        return $html;
    }

    protected function bookingDescriptor(array $context): string
    {
        return ($context['lab_name'] ?? 'the selected laboratory') . ' on ' . ($context['date'] ?? '-') . ' (' . ($context['start_time'] ?? '-') . ' - ' . ($context['end_time'] ?? '-') . ')';
    }

    protected function bookingDetailBlock(array $context): string
    {
        return implode("\n", [
            'Laboratory: ' . ($context['lab_name'] ?? '-'),
            'Room: ' . ($context['lab_room'] ?? '-'),
            'Date: ' . ($context['date'] ?? '-'),
            'Time: ' . ($context['start_time'] ?? '-') . ' - ' . ($context['end_time'] ?? '-'),
            'Activity: ' . ($context['activity'] ?? '-'),
        ]);
    }

    protected function googleCalendarLink(array $context): string
    {
        $startAt = $this->bookingStartDateTime($context);
        $endAt = $this->bookingEndDateTime($context);
        if (! $startAt || ! $endAt) {
            return 'https://calendar.google.com/calendar/render?action=TEMPLATE';
        }

        $utcStart = $startAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $utcEnd = $endAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $query = http_build_query([
            'action' => 'TEMPLATE',
            'text' => 'FKMP Smart Lab Booking - ' . ($context['lab_name'] ?? 'Laboratory'),
            'dates' => $utcStart . '/' . $utcEnd,
            'details' => $this->bookingDetailBlock($context),
            'location' => trim(($context['lab_name'] ?? '') . (! empty($context['lab_room']) ? ' - Room ' . $context['lab_room'] : '')),
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://calendar.google.com/calendar/render?' . $query;
    }

    protected function calendarAttachment(array $context): ?array
    {
        $content = $this->calendarInviteContent($context);
        if ($content === null) {
            return null;
        }

        return [
            'content' => $content,
            'filename' => 'slams-booking-' . (int) ($context['id'] ?? 0) . '.ics',
            'mime' => 'text/calendar',
        ];
    }

    protected function calendarInviteContent(array $context): ?string
    {
        $startAt = $this->bookingStartDateTime($context);
        $endAt = $this->bookingEndDateTime($context);
        if (! $startAt || ! $endAt) {
            return null;
        }

        $uid = 'slams-booking-' . (int) ($context['id'] ?? 0) . '@fkmp-smart-lab';
        $summary = 'FKMP Smart Lab Booking - ' . ($context['lab_name'] ?? 'Laboratory');
        $location = trim(($context['lab_name'] ?? '') . (! empty($context['lab_room']) ? ' - Room ' . $context['lab_room'] : ''));
        $description = $this->bookingDetailBlock($context);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//FKMP Smart Lab//SLAMS//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $this->icsEscape($uid),
            'DTSTAMP:' . $now->format('Ymd\THis\Z'),
            'DTSTART:' . $startAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'DTEND:' . $endAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'SUMMARY:' . $this->icsEscape($summary),
            'DESCRIPTION:' . $this->icsEscape($description),
            'LOCATION:' . $this->icsEscape($location),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map([$this, 'icsFoldLine'], $lines)) . "\r\n";
    }

    protected function icsEscape(string $value): string
    {
        $value = str_replace(["\\", "\r\n", "\r", "\n", ';', ','], ["\\\\", "\\n", "\\n", "\\n", "\\;", "\\,"], $value);
        return trim($value);
    }

    protected function icsFoldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = str_split($line, 73);
        return array_shift($chunks) . "\r\n " . implode("\r\n ", $chunks);
    }

    protected function bookingStartDateTime(array $context): ?DateTimeImmutable
    {
        if (empty($context['date']) || empty($context['start_time'])) {
            return null;
        }
        return new DateTimeImmutable($context['date'] . ' ' . $context['start_time'], $this->timezone);
    }

    protected function bookingEndDateTime(array $context): ?DateTimeImmutable
    {
        if (empty($context['date']) || empty($context['end_time'])) {
            return null;
        }
        return new DateTimeImmutable($context['date'] . ' ' . $context['end_time'], $this->timezone);
    }

    protected function groupUserIds(string $groupName): array
    {
        try {
            if ($this->db->fieldExists('group', 'auth_groups_users')) {
                $rows = $this->db->table('auth_groups_users')
                    ->select('user_id')
                    ->where('group', $groupName)
                    ->get()
                    ->getResultArray();

                return array_map(static fn(array $row): int => (int) $row['user_id'], $rows);
            }

            if ($this->db->fieldExists('group_id', 'auth_groups_users') && $this->db->tableExists('auth_groups')) {
                $rows = $this->db->table('auth_groups_users agu')
                    ->select('agu.user_id')
                    ->join('auth_groups ag', 'ag.id = agu.group_id', 'inner')
                    ->where('ag.name', $groupName)
                    ->get()
                    ->getResultArray();

                return array_map(static fn(array $row): int => (int) $row['user_id'], $rows);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Notification group lookup error for ' . $groupName . ': ' . $e->getMessage());
        }

        return [];
    }

    protected function emailsForUserIds(array $userIds): array
    {
        $emails = [];
        foreach ($this->compactIds($userIds) as $userId) {
            $email = $this->emailForUserId($userId);
            if ($email) {
                $emails[] = $email;
            }
        }
        return array_values(array_unique($emails));
    }

    protected function emailForUserId(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }
        $row = $this->db->table('auth_identities')
            ->select('secret')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        return isset($row['secret']) ? strtolower(trim((string) $row['secret'])) : null;
    }

    protected function findUserIdByEmail(string $email): int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return 0;
        }
        $row = $this->db->table('auth_identities')
            ->select('user_id')
            ->where('type', 'email_password')
            ->where('LOWER(secret) =', $email)
            ->get()
            ->getRowArray();

        return (int) ($row['user_id'] ?? 0);
    }

    protected function compactIds(array $ids): array
    {
        return array_values(array_filter(array_map(static fn($id): int => (int) $id, $ids)));
    }

    protected function settingValue(string $key): ?string
    {
        try {
            $value = setting($key);
            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
