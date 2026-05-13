<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceExternalRequestApprovals extends Migration
{
    public function up()
    {
        $this->forge->addColumn('external_requests', [
            'current_approval_stage' => [
                'type' => 'ENUM',
                'constraint' => ['pic', 'manager', 'completed'],
                'default' => 'pic',
                'after' => 'status',
            ],
            'information_requested_by' => [
                'type' => 'ENUM',
                'constraint' => ['pic', 'manager'],
                'null' => true,
                'after' => 'current_approval_stage',
            ],
            'pic_approved' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'after' => 'information_requested_by',
            ],
            'pic_notes' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'pic_approved',
            ],
            'pic_reviewed_by' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'after' => 'pic_notes',
            ],
            'pic_reviewed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'pic_reviewed_by',
            ],
            'manager_approved' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'after' => 'pic_reviewed_at',
            ],
            'manager_notes' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'manager_approved',
            ],
            'manager_reviewed_by' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'after' => 'manager_notes',
            ],
            'manager_reviewed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'manager_reviewed_by',
            ],
        ]);

        $this->db->query("ALTER TABLE `external_requests` MODIFY `status` ENUM('submitted','under_review','pending_pic_approval','pending_manager_approval','needs_information','approved_for_scheduling','rejected','completed') NOT NULL DEFAULT 'submitted'");
        $this->db->query("UPDATE `external_requests` SET `status` = 'pending_pic_approval' WHERE `status` IN ('submitted', 'under_review')");
        $this->db->query("ALTER TABLE `external_requests` MODIFY `status` ENUM('pending_pic_approval','pending_manager_approval','needs_information','approved_for_scheduling','rejected','completed') NOT NULL DEFAULT 'pending_pic_approval'");
        $this->db->query("UPDATE `external_requests`
            SET
                `current_approval_stage` = CASE
                    WHEN `status` = 'pending_manager_approval' THEN 'manager'
                    WHEN `status` IN ('approved_for_scheduling', 'rejected', 'completed') THEN 'completed'
                    ELSE 'pic'
                END,
                `pic_approved` = CASE WHEN `status` IN ('approved_for_scheduling', 'completed') THEN 1 ELSE 0 END,
                `manager_approved` = CASE WHEN `status` IN ('approved_for_scheduling', 'completed') THEN 1 ELSE 0 END,
                `pic_notes` = `review_notes`,
                `manager_notes` = CASE WHEN `status` IN ('approved_for_scheduling', 'completed') THEN `review_notes` ELSE NULL END,
                `pic_reviewed_by` = CASE WHEN `reviewed_by` IS NOT NULL THEN `reviewed_by` ELSE NULL END,
                `pic_reviewed_at` = CASE WHEN `reviewed_at` IS NOT NULL THEN `reviewed_at` ELSE NULL END,
                `manager_reviewed_by` = CASE WHEN `status` IN ('approved_for_scheduling', 'completed') THEN `reviewed_by` ELSE NULL END,
                `manager_reviewed_at` = CASE WHEN `status` IN ('approved_for_scheduling', 'completed') THEN `reviewed_at` ELSE NULL END,
                `information_requested_by` = CASE WHEN `status` = 'needs_information' THEN 'pic' ELSE NULL END");

        $this->db->query('ALTER TABLE `external_requests` ADD CONSTRAINT `external_requests_pic_reviewed_by_foreign` FOREIGN KEY (`pic_reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE SET NULL');
        $this->db->query('ALTER TABLE `external_requests` ADD CONSTRAINT `external_requests_manager_reviewed_by_foreign` FOREIGN KEY (`manager_reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE SET NULL');
        $this->db->query('CREATE INDEX `external_requests_stage_idx` ON `external_requests` (`current_approval_stage`, `status`)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX `external_requests_stage_idx` ON `external_requests`');
        $this->db->query("ALTER TABLE `external_requests` MODIFY `status` ENUM('submitted','under_review','pending_pic_approval','pending_manager_approval','needs_information','approved_for_scheduling','rejected','completed') NOT NULL DEFAULT 'pending_pic_approval'");
        $this->db->query("UPDATE `external_requests`
            SET `status` = CASE
                WHEN `status` = 'pending_pic_approval' THEN 'submitted'
                WHEN `status` = 'pending_manager_approval' THEN 'under_review'
                ELSE `status`
            END");
        $this->db->query("ALTER TABLE `external_requests` MODIFY `status` ENUM('submitted','under_review','needs_information','approved_for_scheduling','rejected','completed') NOT NULL DEFAULT 'submitted'");

        $this->forge->dropForeignKey('external_requests', 'external_requests_pic_reviewed_by_foreign');
        $this->forge->dropForeignKey('external_requests', 'external_requests_manager_reviewed_by_foreign');

        $this->forge->dropColumn('external_requests', [
            'current_approval_stage',
            'information_requested_by',
            'pic_approved',
            'pic_notes',
            'pic_reviewed_by',
            'pic_reviewed_at',
            'manager_approved',
            'manager_notes',
            'manager_reviewed_by',
            'manager_reviewed_at',
        ]);
    }
}
