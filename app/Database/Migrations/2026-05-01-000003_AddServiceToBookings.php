<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddServiceToBookings extends Migration
{
    private string $serviceIndex = 'idx_bookings_service_id';
    private string $serviceForeignKey = 'bookings_service_id_fk';

    public function up()
    {
        if (! $this->db->tableExists('bookings') || ! $this->db->tableExists('lab_services')) {
            return;
        }

        if (! $this->db->fieldExists('service_id', 'bookings')) {
            $this->forge->addColumn('bookings', [
                'service_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'lab_id',
                ],
            ]);
        }

        if (! $this->indexExists('bookings', $this->serviceIndex)) {
            $this->db->query("ALTER TABLE `bookings` ADD INDEX `{$this->serviceIndex}` (`service_id`)");
        }

        if (! $this->foreignKeyExists('bookings', $this->serviceForeignKey)) {
            $this->db->query("
                ALTER TABLE `bookings`
                ADD CONSTRAINT `{$this->serviceForeignKey}`
                FOREIGN KEY (`service_id`) REFERENCES `lab_services`(`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE
            ");
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('bookings')) {
            return;
        }

        if ($this->foreignKeyExists('bookings', $this->serviceForeignKey)) {
            $this->db->query("ALTER TABLE `bookings` DROP FOREIGN KEY `{$this->serviceForeignKey}`");
        }

        if ($this->indexExists('bookings', $this->serviceIndex)) {
            $this->db->query("ALTER TABLE `bookings` DROP INDEX `{$this->serviceIndex}`");
        }

        if ($this->db->fieldExists('service_id', 'bookings')) {
            $this->forge->dropColumn('bookings', 'service_id');
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
