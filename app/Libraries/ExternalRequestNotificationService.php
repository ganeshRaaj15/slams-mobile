<?php

namespace App\Libraries;

use App\Models\EmailLogModel;
use App\Models\ExternalRequestModel;
use App\Models\NotificationModel;
use Config\Database;

class ExternalRequestNotificationService
{
    protected \CodeIgniter\Database\BaseConnection $db;
    protected ExternalRequestModel $requestModel;
    protected NotificationModel $notificationModel;
    protected EmailLogModel $emailLogModel;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->requestModel = new ExternalRequestModel();
        $this->notificationModel = new NotificationModel();
        $this->emailLogModel = new EmailLogModel();
    }

    public function notifySubmitted(int $requestId, bool $resubmitted = false): void
    {
        $context = $this->requestContext($requestId);
        if (! $context) {
            return;
        }

        $requesterLink = '/dashboard/external';
        $reviewLink = '/dashboard/external-requests/' . $requestId;
        $stage = (string) ($context['current_approval_stage'] ?? 'pic');
        $descriptor = $this->requestDescriptor($context);
        $requesterTitle = $resubmitted ? 'External Request Resubmitted' : 'External Request Submitted';
        $requesterMessage = $resubmitted
            ? 'Your external request for ' . $descriptor . ' was resubmitted and is now back in the approval flow.'
            : 'Your external request for ' . $descriptor . ' was submitted successfully and will be reviewed by the PIC first, then the Lab Manager.';

        $this->createNotifications($this->compactIds([(int) ($context['user_id'] ?? 0)]), $requesterTitle, $requesterMessage, $requesterLink, $requestId);
        $this->sendEmail(
            [$context['contact_email'] ?? null],
            'FKMP Smart Lab: External Request Submitted',
            $this->emailTemplate('External Request Submitted', [
                $requesterMessage,
                'You will receive a notification and email each time the PIC or Lab Manager updates the request, asks for extra information, rejects it, or approves it for scheduling.',
                $this->requestDetailBlock($context),
            ], site_url($requesterLink), 'Open External Request Dashboard'),
            ['entity_type' => 'external_request', 'entity_id' => $requestId, 'notification_type' => 'external_request']
        );

        if ($stage === 'manager') {
            $managerIds = $this->managerUserIds();
            $managerPendingCount = $this->pendingManagerCount();
            $managerMessage = 'An external request for ' . $descriptor . ' is waiting for Lab Manager approval. There are currently ' . $managerPendingCount . ' external request(s) requiring Lab Manager attention.';

            $this->createNotifications($managerIds, 'External Request Needs Lab Manager Approval', $managerMessage, $reviewLink, $requestId);
            $this->sendEmail(
                $this->managerEmails(),
                'FKMP Smart Lab: External Request Awaiting Lab Manager Approval',
                $this->emailTemplate('External Request Needs Your Approval', [
                    'An external request has passed PIC review and now needs Lab Manager approval.',
                    'There are currently ' . $managerPendingCount . ' external request(s) requiring your attention.',
                    $this->requestDetailBlock($context),
                ], site_url($reviewLink), 'Open External Request Queue'),
                ['entity_type' => 'external_request', 'entity_id' => $requestId, 'notification_type' => 'external_request']
            );

            return;
        }

        $picUserId = $this->picUserIdForLab((int) ($context['lab_id'] ?? 0));
        $picPendingCount = $this->pendingPicCountForLab((string) ($context['pic_email'] ?? ''));
        $picMessage = 'A new external request for ' . $descriptor . ' is waiting for your PIC approval. You currently have ' . $picPendingCount . ' external request(s) requiring PIC attention.';

        $this->createNotifications($this->compactIds([$picUserId]), $resubmitted ? 'External Request Resubmitted For PIC' : 'New External Request Received', $picMessage, $reviewLink, $requestId);
        $this->sendEmail(
            [$context['pic_email'] ?? null],
            'FKMP Smart Lab: External Request Awaiting PIC Approval',
            $this->emailTemplate('External Request Needs Your Approval', [
                'A new external request has been submitted and is waiting for your review as PIC.',
                'There are currently ' . $picPendingCount . ' external request(s) requiring your attention.',
                $this->requestDetailBlock($context),
            ], site_url($reviewLink), 'Open External Request Queue'),
            ['entity_type' => 'external_request', 'entity_id' => $requestId, 'notification_type' => 'external_request']
        );
    }

    public function notifyStatusUpdated(int $requestId, string $actorRole): void
    {
        $context = $this->requestContext($requestId);
        if (! $context) {
            return;
        }

        $status = (string) ($context['status'] ?? '');
        $descriptor = $this->requestDescriptor($context);
        $requesterLink = '/dashboard/external';
        $reviewLink = '/dashboard/external-requests/' . $requestId;
        $requesterIds = $this->compactIds([(int) ($context['user_id'] ?? 0)]);
        $requesterEmail = [$context['contact_email'] ?? null];
        $roleLabel = $actorRole === 'manager' ? 'Lab Manager' : 'PIC';
        $note = trim((string) ($actorRole === 'manager' ? ($context['manager_notes'] ?? '') : ($context['pic_notes'] ?? '')));

        if ($status === 'pending_manager_approval' && $actorRole === 'pic') {
            $requesterMessage = 'Your external request for ' . $descriptor . ' was approved by the PIC and is now waiting for Lab Manager approval.';
            $managerPendingCount = $this->pendingManagerCount();
            $managerMessage = 'An external request for ' . $descriptor . ' has been approved by the PIC and now needs Lab Manager approval. There are currently ' . $managerPendingCount . ' external request(s) requiring Lab Manager attention.';

            $this->createNotifications($requesterIds, 'External Request Pending Lab Manager Approval', $requesterMessage, $requesterLink, $requestId);
            $this->sendEmail(
                $requesterEmail,
                'FKMP Smart Lab: PIC Approved Your External Request',
                $this->emailTemplate('PIC Approved Your External Request', [
                    $requesterMessage,
                    $this->requestDetailBlock($context),
                ], site_url($requesterLink), 'Open External Request Dashboard'),
                ['entity_type' => 'external_request', 'entity_id' => $requestId, 'notification_type' => 'external_request']
            );

            $this->createNotifications($this->managerUserIds(), 'External Request Needs Lab Manager Approval', $managerMessage, $reviewLink, $requestId);
            $this->sendEmail(
                $this->managerEmails(),
                'FKMP Smart Lab: External Request Awaiting Lab Manager Approval',
                $this->emailTemplate('External Request Needs Your Approval', [
                    'A PIC-approved external request is now waiting for Lab Manager approval.',
                    'There are currently ' . $managerPendingCount . ' external request(s) requiring your attention.',
                    $this->requestDetailBlock($context),
                ], site_url($reviewLink), 'Open External Request Queue'),
                ['entity_type' => 'external_request', 'entity_id' => $requestId, 'notification_type' => 'external_request']
            );

            return;
        }

        if ($status === 'needs_information') {
            $message = 'Your external request for ' . $descriptor . ' needs additional information from the ' . $roleLabel . '.';
            $paragraphs = [$message];
            if ($note !== '') {
                $paragraphs[] = 'Notes from the ' . $roleLabel . ': ' . $note;
            }
            $paragraphs[] = 'Update the request details and resubmit it from your external request dashboard.';
            $paragraphs[] = $this->requestDetailBlock($context);

            $this->createNotifications($requesterIds, 'External Request Needs More Information', $message, $requesterLink, $requestId);
            $this->sendEmail(
                $requesterEmail,
                'FKMP Smart Lab: More Information Needed For External Request',
                $this->emailTemplate('More Information Needed', $paragraphs, site_url($requesterLink), 'Update External Request'),
                ['entity_type' => 'external_request', 'entity_id' => $requestId, 'notification_type' => 'external_request']
            );

            return;
        }

        if ($status === 'rejected') {
            $message = 'Your external request for ' . $descriptor . ' was rejected by the ' . $roleLabel . '.';
            $paragraphs = [$message];
            if ($note !== '') {
                $paragraphs[] = 'Notes from the ' . $roleLabel . ': ' . $note;
            }
            $paragraphs[] = $this->requestDetailBlock($context);

            $this->createNotifications($requesterIds, 'External Request Rejected', $message, $requesterLink, $requestId);
            $this->sendEmail(
                $requesterEmail,
                'FKMP Smart Lab: External Request Rejected',
                $this->emailTemplate('External Request Rejected', $paragraphs, site_url($requesterLink), 'Open External Request Dashboard'),
                ['entity_type' => 'external_request', 'entity_id' => $requestId, 'notification_type' => 'external_request']
            );

            return;
        }

        if ($status === 'approved_for_scheduling') {
            $message = 'Your external request for ' . $descriptor . ' has been approved for scheduling.';
            $paragraphs = [$message];
            if ($note !== '') {
                $paragraphs[] = 'Lab Manager notes: ' . $note;
            }
            $paragraphs[] = $this->requestDetailBlock($context);

            $this->createNotifications($requesterIds, 'External Request Approved', $message, $requesterLink, $requestId);
            $this->sendEmail(
                $requesterEmail,
                'FKMP Smart Lab: External Request Approved',
                $this->emailTemplate('External Request Approved', $paragraphs, site_url($requesterLink), 'Open External Request Dashboard'),
                ['entity_type' => 'external_request', 'entity_id' => $requestId, 'notification_type' => 'external_request']
            );
        }
    }

    protected function requestContext(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        return $this->db->table('external_requests er')
            ->select('er.*, l.name AS lab_name, l.pic_email')
            ->join('laboratories l', 'l.id = er.lab_id', 'left')
            ->where('er.id', $requestId)
            ->get()
            ->getRowArray();
    }

    protected function picUserIdForLab(int $labId): int
    {
        if ($labId <= 0) {
            return 0;
        }

        $row = $this->db->table('laboratories')
            ->select('pic_email')
            ->where('id', $labId)
            ->get()
            ->getRowArray();

        return $this->findUserIdByEmail((string) ($row['pic_email'] ?? ''));
    }

    protected function managerUserIds(): array
    {
        $reviewers = $this->db->table('auth_groups_users')
            ->select('user_id')
            ->where('group', 'manager')
            ->get()
            ->getResultArray();

        $userIds = [];
        foreach ($reviewers as $reviewer) {
            $reviewerId = (int) ($reviewer['user_id'] ?? 0);
            if ($reviewerId > 0) {
                $userIds[] = $reviewerId;
            }
        }

        return array_values(array_unique($userIds));
    }

    protected function pendingPicCountForLab(string $picEmail): int
    {
        $picEmail = strtolower(trim($picEmail));
        if ($picEmail === '') {
            return 0;
        }

        return (int) $this->db->table('external_requests er')
            ->join('laboratories l', 'l.id = er.lab_id')
            ->where('LOWER(TRIM(l.pic_email)) =', $picEmail)
            ->where('er.status', 'pending_pic_approval')
            ->countAllResults();
    }

    protected function pendingManagerCount(): int
    {
        return (int) $this->db->table('external_requests')
            ->where('status', 'pending_manager_approval')
            ->countAllResults();
    }

    protected function createNotifications(array $userIds, string $title, string $message, string $link, int $requestId): void
    {
        $rows = [];
        foreach ($this->compactIds($userIds) as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $rows[] = [
                'user_id' => $userId,
                'type' => 'external_request',
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'entity_type' => 'external_request',
                'entity_id' => $requestId,
                'is_read' => 0,
            ];
        }

        if ($rows !== []) {
            (new UserNotificationDispatcher($this->notificationModel))->dispatch($rows);
        }
    }

    protected function compactIds(array $userIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
    }

    protected function requestDescriptor(array $context): string
    {
        $descriptor = (string) ($context['lab_name'] ?? 'the requested laboratory');
        if (! empty($context['preferred_date'])) {
            $descriptor .= ' on ' . $context['preferred_date'];
        }

        if (! empty($context['preferred_start_time']) && ! empty($context['preferred_end_time'])) {
            $descriptor .= ' (' . substr((string) $context['preferred_start_time'], 0, 5) . ' - ' . substr((string) $context['preferred_end_time'], 0, 5) . ')';
        }

        return $descriptor;
    }

    protected function requestDetailBlock(array $context): string
    {
        $lines = [
            'Laboratory: ' . (($context['lab_name'] ?? '') !== '' ? $context['lab_name'] : '-'),
            'Requester: ' . (($context['contact_name'] ?? '') !== '' ? $context['contact_name'] : '-'),
            'Organization: ' . (($context['organization_name'] ?? '') !== '' ? $context['organization_name'] : '-'),
            'Preferred Date: ' . (($context['preferred_date'] ?? '') !== '' ? $context['preferred_date'] : '-'),
            'Participants: ' . (string) ($context['participant_count'] ?? 0),
            'Purpose: ' . (($context['purpose'] ?? '') !== '' ? $context['purpose'] : '-'),
        ];

        return implode('<br>', array_map('esc', $lines));
    }

    protected function managerEmails(): array
    {
        return $this->emailsForUserIds($this->managerUserIds());
    }

    protected function emailsForUserIds(array $userIds): array
    {
        $emails = [];
        foreach ($this->compactIds($userIds) as $userId) {
            $email = $this->emailForUserId($userId);
            if ($email !== null) {
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

        $identity = $this->db->table('auth_identities')
            ->select('secret')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        $email = strtolower(trim((string) ($identity['secret'] ?? '')));
        return $email !== '' ? $email : null;
    }

    protected function findUserIdByEmail(string $email): int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return 0;
        }

        $identity = $this->db->table('auth_identities')
            ->select('user_id')
            ->where('type', 'email_password')
            ->where('LOWER(secret) =', $email)
            ->get()
            ->getRowArray();

        return (int) ($identity['user_id'] ?? 0);
    }

    protected function sendEmail(array $emails, string $subject, string $message, ?array $context = null): void
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
            $email->send();
        } catch (\Throwable $e) {
            log_message('error', 'External request email error: ' . $e->getMessage());
        }

        $rows = [];
        foreach ($recipients as $recipient) {
            $rows[] = [
                'user_id' => $this->findUserIdByEmail($recipient) ?: null,
                'to_email' => $recipient,
                'subject' => $subject,
                'body' => $message,
                'notification_type' => $context['notification_type'] ?? 'external_request',
                'entity_type' => $context['entity_type'] ?? 'external_request',
                'entity_id' => $context['entity_id'] ?? null,
                'has_attachment' => 0,
                'attachment_name' => null,
            ];
        }

        try {
            $this->emailLogModel->insertBatch($rows);
        } catch (\Throwable $e) {
            log_message('error', 'External request email log error: ' . $e->getMessage());
        }
    }

    protected function emailTemplate(string $heading, array $paragraphs, ?string $actionUrl = null, ?string $actionText = null): string
    {
        $paragraphHtml = '';
        foreach ($paragraphs as $paragraph) {
            $paragraphHtml .= '<p style="margin:0 0 12px 0;color:#334155;line-height:1.6;">' . $paragraph . '</p>';
        }

        $actionHtml = '';
        if ($actionUrl !== null && $actionText !== null) {
            $actionHtml = '<p style="margin:24px 0 0 0;"><a href="' . esc($actionUrl) . '" style="display:inline-block;padding:12px 18px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:8px;">' . esc($actionText) . '</a></p>';
        }

        return '<div style="font-family:Arial,sans-serif;background:#f8fafc;padding:24px;">'
            . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0;">'
            . '<h2 style="margin:0 0 18px 0;color:#0f172a;">' . esc($heading) . '</h2>'
            . $paragraphHtml
            . $actionHtml
            . '</div></div>';
    }
}
