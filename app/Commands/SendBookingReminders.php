<?php

namespace App\Commands;

use App\Libraries\NotificationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SendBookingReminders extends BaseCommand
{
    protected $group = 'SLAMS';
    protected $name = 'slams:send-booking-reminders';
    protected $description = 'Send reminder notifications and emails for approved bookings happening soon.';
    protected $usage = 'slams:send-booking-reminders [hoursAhead]';

    public function run(array $params)
    {
        $hoursAhead = max((int) ($params[0] ?? 24), 1);
        $sent = 0;

        try {
            $service = new NotificationService();
            $sent = $service->sendUpcomingBookingReminders($hoursAhead);
            CLI::write('Booking reminder notifications sent: ' . $sent, 'green');
        } catch (\Throwable $e) {
            log_message('error', 'Booking reminder command failed: ' . $e->getMessage());
            CLI::error('Booking reminder command failed. Check writable/logs for details.');
        }
    }
}
