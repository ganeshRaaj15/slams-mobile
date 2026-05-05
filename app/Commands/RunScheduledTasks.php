<?php

namespace App\Commands;

use App\Libraries\NotificationService;
use App\Libraries\MaintenanceForecastService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RunScheduledTasks extends BaseCommand
{
    protected $group = 'SLAMS';
    protected $name = 'slams:run-scheduled-tasks';
    protected $description = 'Run the scheduled SLAMS background tasks such as booking reminders.';

    public function run(array $params)
    {
        $hoursAhead = max((int) ($params[0] ?? 24), 1);
        $sent = 0;
        $dueSent = 0;
        $failed = false;

        try {
            $service = new NotificationService();
            $sent = $service->sendUpcomingBookingReminders($hoursAhead);
        } catch (\Throwable $e) {
            $failed = true;
            log_message('error', 'Scheduled task failed [booking reminders]: ' . $e->getMessage());
            CLI::error('Booking reminder task failed. Check writable/logs for details.');
        }

        try {
            $forecastService = new MaintenanceForecastService();
            $dueSent = $forecastService->sendUpcomingDueReminders(30);
        } catch (\Throwable $e) {
            $failed = true;
            log_message('error', 'Scheduled task failed [maintenance due reminders]: ' . $e->getMessage());
            CLI::error('Maintenance due reminder task failed. Check writable/logs for details.');
        }

        CLI::write($failed ? 'Scheduled tasks completed with warnings.' : 'Scheduled tasks completed.', $failed ? 'yellow' : 'green');
        CLI::write('Booking reminder notifications sent: ' . $sent, 'green');
        CLI::write('Maintenance due notifications sent: ' . $dueSent, 'green');
    }
}
