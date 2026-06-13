<?php

namespace App\Libraries;

use App\Models\BookingModel;
use App\Models\LabReservationModel;
use App\Models\SettingsModel;

class BookingSlotService
{
    protected BookingModel $bookingModel;
    protected SettingsModel $settingsModel;
    protected LabReservationModel $reservationModel;

    public function __construct(
        ?BookingModel $bookingModel = null,
        ?SettingsModel $settingsModel = null,
        ?LabReservationModel $reservationModel = null
    )
    {
        $this->bookingModel = $bookingModel ?? new BookingModel();
        $this->settingsModel = $settingsModel ?? new SettingsModel();
        $this->reservationModel = $reservationModel ?? new LabReservationModel();
    }

    public function getDefinitions(): array
    {
        $slotsJson = $this->settingsModel->get('system', 'booking_slots');
        $slots = [];

        if (is_string($slotsJson) && $slotsJson !== '') {
            $decoded = json_decode($slotsJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $slot) {
                    $start = $this->normalizeTime((string) ($slot['start'] ?? ''));
                    $end = $this->normalizeTime((string) ($slot['end'] ?? ''));
                    if ($start !== '' && $end !== '' && $start < $end) {
                        $slots[] = [
                            'label' => (string) ($slot['label'] ?? ($start . ' - ' . $end)),
                            'start' => $start,
                            'end' => $end,
                        ];
                    }
                }
            }
        }

        if ($slots !== []) {
            usort($slots, static function (array $a, array $b): int {
                if ($a['start'] === $b['start']) {
                    return strcmp($a['end'], $b['end']);
                }

                return strcmp($a['start'], $b['start']);
            });

            return $slots;
        }

        return [
            ['label' => '08:00 - 10:00', 'start' => '08:00:00', 'end' => '10:00:00'],
            ['label' => '10:00 - 12:00', 'start' => '10:00:00', 'end' => '12:00:00'],
            ['label' => '13:00 - 15:00', 'start' => '13:00:00', 'end' => '15:00:00'],
            ['label' => '15:00 - 17:00', 'start' => '15:00:00', 'end' => '17:00:00'],
        ];
    }

    public function normalizeTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) === 1) {
            return $time;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time . ':00';
        }

        return $time;
    }

    public function findMatchingDefinition(string $startTime, string $endTime): ?array
    {
        $start = $this->normalizeTime($startTime);
        $end = $this->normalizeTime($endTime);

        foreach ($this->getDefinitions() as $slot) {
            if (
                $this->normalizeTime((string) ($slot['start'] ?? '')) === $start
                && $this->normalizeTime((string) ($slot['end'] ?? '')) === $end
            ) {
                return $slot;
            }
        }

        return null;
    }

    public function daySlotsForLab(int $labId, string $date): array
    {
        $today = date('Y-m-d');
        $now = date('H:i:s');
        $slots = [];

        foreach ($this->getDefinitions() as $slot) {
            $availability = $this->slotAvailabilityForLab(
                $labId,
                $date,
                (string) ($slot['start'] ?? ''),
                (string) ($slot['end'] ?? ''),
                true
            );

            if ($date < $today || ($date === $today && (string) ($slot['end'] ?? '') <= $now)) {
                $availability = [
                    'can_book' => false,
                    'reason' => 'Slot is already in the past.',
                ];
            }

            $slots[] = [
                'label' => (string) ($slot['label'] ?? ''),
                'start' => substr((string) ($slot['start'] ?? ''), 0, 5),
                'end' => substr((string) ($slot['end'] ?? ''), 0, 5),
                'can_book' => (bool) ($availability['can_book'] ?? false),
                'reason' => $availability['reason'] ?? null,
                'assets' => [],
            ];
        }

        return $slots;
    }

    public function slotAvailabilityForLab(
        int $labId,
        string $date,
        string $startTime,
        string $endTime,
        bool $requireMatchingDefinition = true,
        ?int $ignoreBookingId = null
    ): array {
        if ($labId <= 0 || trim($date) === '') {
            return [
                'can_book' => false,
                'reason' => 'Please choose a laboratory and date first.',
            ];
        }

        $start = $this->normalizeTime($startTime);
        $end = $this->normalizeTime($endTime);

        if ($start === '' || $end === '') {
            return [
                'can_book' => false,
                'reason' => 'Please choose a booking slot.',
            ];
        }

        if ($start >= $end) {
            return [
                'can_book' => false,
                'reason' => 'Selected slot is invalid.',
            ];
        }

        if ($requireMatchingDefinition && $this->findMatchingDefinition($start, $end) === null) {
            return [
                'can_book' => false,
                'reason' => 'Selected slot is not part of the configured booking schedule.',
            ];
        }

        $today = date('Y-m-d');
        $now = date('H:i:s');
        if ($date < $today || ($date === $today && $end <= $now)) {
            return [
                'can_book' => false,
                'reason' => 'Slot is already in the past.',
            ];
        }

        if ($this->hasBlockingReservation($labId, $date, $start, $end, $ignoreBookingId)) {
            return [
                'can_book' => false,
                'reason' => 'Laboratory is reserved for this slot.',
            ];
        }

        return [
            'can_book' => true,
            'reason' => null,
        ];
    }

    public function hasBlockingReservation(
        int $labId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $ignoreReservationId = null
    ): bool {
        if ($labId <= 0 || trim($date) === '') {
            return false;
        }

        $start = $this->normalizeTime($startTime);
        $end = $this->normalizeTime($endTime);
        if ($start === '' || $end === '' || $start >= $end) {
            return false;
        }

        return $this->reservationModel->overlaps(
            $labId,
            $date . ' ' . $start,
            $date . ' ' . $end,
            (int) ($ignoreReservationId ?? 0)
        );
    }
}
