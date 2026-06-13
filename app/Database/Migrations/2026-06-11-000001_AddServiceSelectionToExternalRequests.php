<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddServiceSelectionToExternalRequests extends Migration
{
    private string $serviceIndex = 'idx_external_requests_service_id';
    private string $serviceForeignKey = 'external_requests_service_id_fk';

    public function up()
    {
        if (! $this->db->tableExists('external_requests')) {
            return;
        }

        if (! $this->db->fieldExists('service_id', 'external_requests')) {
            $this->forge->addColumn('external_requests', [
                'service_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'lab_id',
                ],
            ]);
        }

        if (! $this->db->fieldExists('selected_assets', 'external_requests')) {
            $this->forge->addColumn('external_requests', [
                'selected_assets' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'service_id',
                ],
            ]);
        }

        if ($this->db->tableExists('lab_services') && ! $this->indexExists('external_requests', $this->serviceIndex)) {
            $this->db->query("ALTER TABLE `external_requests` ADD INDEX `{$this->serviceIndex}` (`service_id`)");
        }

        if (
            $this->db->tableExists('lab_services')
            && $this->db->fieldExists('service_id', 'external_requests')
            && ! $this->foreignKeyExists('external_requests', $this->serviceForeignKey)
        ) {
            $this->db->query("
                ALTER TABLE `external_requests`
                ADD CONSTRAINT `{$this->serviceForeignKey}`
                FOREIGN KEY (`service_id`) REFERENCES `lab_services`(`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE
            ");
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('external_requests')) {
            return;
        }

        if ($this->foreignKeyExists('external_requests', $this->serviceForeignKey)) {
            $this->db->query("ALTER TABLE `external_requests` DROP FOREIGN KEY `{$this->serviceForeignKey}`");
        }

        if ($this->indexExists('external_requests', $this->serviceIndex)) {
            $this->db->query("ALTER TABLE `external_requests` DROP INDEX `{$this->serviceIndex}`");
        }

        $dropColumns = [];
        if ($this->db->fieldExists('selected_assets', 'external_requests')) {
            $dropColumns[] = 'selected_assets';
        }
        if ($this->db->fieldExists('service_id', 'external_requests')) {
            $dropColumns[] = 'service_id';
        }

        if ($dropColumns !== []) {
            $this->forge->dropColumn('external_requests', $dropColumns);
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
