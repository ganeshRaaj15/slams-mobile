<?php

namespace App\Libraries;

use App\Models\BookingApplicantModel;
use App\Models\BookingModel;

class ExternalRequestBookingService
{
    protected BookingModel $bookingModel;
    protected BookingApplicantModel $bookingApplicantModel;
    protected BookingSlotService $slotService;

    public function __construct(
        ?BookingModel $bookingModel = null,
        ?BookingApplicantModel $bookingApplicantModel = null,
        ?BookingSlotService $slotService = null
    ) {
        $this->bookingModel = $bookingModel ?? new BookingModel();
        $this->bookingApplicantModel = $bookingApplicantModel ?? new BookingApplicantModel();
        $this->slotService = $slotService ?? new BookingSlotService($this->bookingModel);
    }

    public function createApprovedBooking(array $requestRecord): int
    {
        $existingBookingId = (int) ($requestRecord['booking_id'] ?? 0);
        if ($existingBookingId > 0 && $this->bookingModel->find($existingBookingId)) {
            return $existingBookingId;
        }

        $labId = (int) ($requestRecord['lab_id'] ?? 0);
        $date = trim((string) ($requestRecord['preferred_date'] ?? ''));
        $startTime = $this->slotService->normalizeTime((string) ($requestRecord['preferred_start_time'] ?? ''));
        $endTime = $this->slotService->normalizeTime((string) ($requestRecord['preferred_end_time'] ?? ''));

        if ($labId <= 0 || $date === '' || $startTime === '' || $endTime === '' || $startTime >= $endTime) {
            throw new \DomainException('This external request does not have a valid slot to reserve.');
        }

        $availability = $this->slotService->slotAvailabilityForLab($labId, $date, $startTime, $endTime, false);
        if (! ($availability['can_book'] ?? false)) {
            throw new \DomainException((string) ($availability['reason'] ?? 'This slot is no longer available.'));
        }

        $bookingId = (int) $this->bookingModel->insert([
            'user_id' => (int) ($requestRecord['user_id'] ?? 0) ?: null,
            'lab_id' => $labId,
            'service_id' => null,
            'user_type' => 'EXTERNAL',
            'faculty_id' => null,
            'approval_flow' => ApprovalFlowResolver::TWO_STAGE_APPROVAL,
            'approved_by_pic' => 1,
            'approved_by_manager' => 1,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'activity' => trim((string) ($requestRecord['purpose'] ?? '')) ?: 'External laboratory request',
            'supervisor_name' => null,
            'supervisor_email' => null,
            'supervisor_phone' => null,
            'pdf_path' => null,
            'status' => 'APPROVED',
        ], true);

        if ($bookingId <= 0) {
            throw new \RuntimeException('Could not create the booking record for this approved external request.');
        }

        $this->bookingApplicantModel->insert([
            'booking_id' => $bookingId,
            'name' => trim((string) ($requestRecord['contact_name'] ?? '')) ?: 'External Requester',
            'matric_id' => 'EXTERNAL',
            'email' => trim((string) ($requestRecord['contact_email'] ?? '')),
            'phone' => trim((string) ($requestRecord['contact_phone'] ?? '')),
            'faculty' => trim((string) ($requestRecord['organization_name'] ?? '')) ?: 'External Organization',
        ]);

        return $bookingId;
    }
}
