<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCancelledBookingStatusAndConflictIndex extends Migration
{
    private string $bookingConflictIndex = 'idx_bookings_lab_date_time_status';

    public function up()
    {
        if (! $this->db->tableExists('bookings')) {
            return;
        }

        $this->db->query("ALTER TABLE `bookings` MODIFY `status` ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING'");

        if (! $this->indexExists('bookings', $this->bookingConflictIndex)) {
            $this->db->query("ALTER TABLE `bookings` ADD INDEX `{$this->bookingConflictIndex}` (`lab_id`, `date`, `start_time`, `end_time`, `status`)");
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('bookings')) {
            return;
        }

        if ($this->indexExists('bookings', $this->bookingConflictIndex)) {
            $this->db->query("ALTER TABLE `bookings` DROP INDEX `{$this->bookingConflictIndex}`");
        }

        $this->db->query("UPDATE `bookings` SET `status` = 'REJECTED' WHERE `status` = 'CANCELLED'");
        $this->db->query("ALTER TABLE `bookings` MODIFY `status` ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING'");
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = $this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$index])->getResultArray();

        return $rows !== [];
    }
}
