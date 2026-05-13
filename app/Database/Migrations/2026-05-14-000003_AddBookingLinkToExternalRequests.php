<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBookingLinkToExternalRequests extends Migration
{
    private string $bookingIndex = 'idx_external_requests_booking_id';
    private string $bookingForeignKey = 'external_requests_booking_id_fk';

    public function up()
    {
        if (! $this->db->tableExists('external_requests') || ! $this->db->tableExists('bookings')) {
            return;
        }

        if (! $this->db->fieldExists('booking_id', 'external_requests')) {
            $this->forge->addColumn('external_requests', [
                'booking_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'manager_reviewed_at',
                ],
            ]);
        }

        if (! $this->indexExists('external_requests', $this->bookingIndex)) {
            $this->db->query("ALTER TABLE `external_requests` ADD INDEX `{$this->bookingIndex}` (`booking_id`)");
        }

        if (! $this->foreignKeyExists('external_requests', $this->bookingForeignKey)) {
            $this->db->query("
                ALTER TABLE `external_requests`
                ADD CONSTRAINT `{$this->bookingForeignKey}`
                FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE
            ");
        }

        $this->db->query("
            UPDATE `external_requests` er
            JOIN `bookings` b
              ON b.`user_type` = 'EXTERNAL'
             AND b.`lab_id` = er.`lab_id`
             AND b.`date` = er.`preferred_date`
             AND b.`start_time` = er.`preferred_start_time`
             AND b.`end_time` = er.`preferred_end_time`
             AND (b.`user_id` <=> er.`user_id`)
            SET er.`booking_id` = b.`id`
            WHERE er.`status` = 'approved_for_scheduling'
              AND er.`booking_id` IS NULL
        ");

        $this->db->query("
            INSERT INTO `bookings`
                (`user_id`, `lab_id`, `service_id`, `user_type`, `faculty_id`, `approval_flow`,
                 `approved_by_pic`, `approved_by_manager`, `date`, `start_time`, `end_time`,
                 `activity`, `supervisor_name`, `supervisor_email`, `supervisor_phone`, `pdf_path`,
                 `status`, `created_at`, `updated_at`)
            SELECT
                er.`user_id`,
                er.`lab_id`,
                NULL,
                'EXTERNAL',
                NULL,
                'FACULTY_APPROVAL',
                1,
                1,
                er.`preferred_date`,
                er.`preferred_start_time`,
                er.`preferred_end_time`,
                COALESCE(NULLIF(er.`purpose`, ''), 'External laboratory request'),
                NULL,
                NULL,
                NULL,
                NULL,
                'APPROVED',
                COALESCE(er.`manager_reviewed_at`, er.`reviewed_at`, er.`updated_at`, er.`created_at`, NOW()),
                COALESCE(er.`updated_at`, er.`created_at`, NOW())
            FROM `external_requests` er
            WHERE er.`status` = 'approved_for_scheduling'
              AND er.`booking_id` IS NULL
              AND er.`preferred_date` IS NOT NULL
              AND er.`preferred_start_time` IS NOT NULL
              AND er.`preferred_end_time` IS NOT NULL
        ");

        $this->db->query("
            UPDATE `external_requests` er
            JOIN `bookings` b
              ON b.`user_type` = 'EXTERNAL'
             AND b.`lab_id` = er.`lab_id`
             AND b.`date` = er.`preferred_date`
             AND b.`start_time` = er.`preferred_start_time`
             AND b.`end_time` = er.`preferred_end_time`
             AND (b.`user_id` <=> er.`user_id`)
            SET er.`booking_id` = b.`id`
            WHERE er.`status` = 'approved_for_scheduling'
              AND er.`booking_id` IS NULL
        ");

        if ($this->db->tableExists('booking_applicants')) {
            $this->db->query("
                INSERT INTO `booking_applicants`
                    (`booking_id`, `name`, `matric_id`, `email`, `phone`, `faculty`)
                SELECT
                    er.`booking_id`,
                    COALESCE(NULLIF(er.`contact_name`, ''), 'External Requester'),
                    'EXTERNAL',
                    COALESCE(er.`contact_email`, ''),
                    COALESCE(er.`contact_phone`, ''),
                    COALESCE(NULLIF(er.`organization_name`, ''), 'External Organization')
                FROM `external_requests` er
                LEFT JOIN `booking_applicants` ba ON ba.`booking_id` = er.`booking_id`
                WHERE er.`status` = 'approved_for_scheduling'
                  AND er.`booking_id` IS NOT NULL
                  AND ba.`id` IS NULL
            ");
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('external_requests')) {
            return;
        }

        if ($this->foreignKeyExists('external_requests', $this->bookingForeignKey)) {
            $this->db->query("ALTER TABLE `external_requests` DROP FOREIGN KEY `{$this->bookingForeignKey}`");
        }

        if ($this->indexExists('external_requests', $this->bookingIndex)) {
            $this->db->query("ALTER TABLE `external_requests` DROP INDEX `{$this->bookingIndex}`");
        }

        if ($this->db->fieldExists('booking_id', 'external_requests')) {
            $this->forge->dropColumn('external_requests', 'booking_id');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = $this->db->getDatabase();
        $row = $this->db->query(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        )->getRowArray();

        return $row !== null;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $database = $this->db->getDatabase();
        $row = $this->db->query(
            'SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ? LIMIT 1',
            [$database, $table, $constraintName, 'FOREIGN KEY']
        )->getRowArray();

        return $row !== null;
    }
}
