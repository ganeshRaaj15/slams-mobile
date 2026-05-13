<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DeleteOrphanedApprovedExternalRequests extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('external_requests') || ! $this->db->fieldExists('booking_id', 'external_requests')) {
            return;
        }

        $orphanIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $this->db->table('external_requests er')
                ->select('er.id')
                ->join('bookings b', 'b.id = er.booking_id', 'left')
                ->where('er.status', 'approved_for_scheduling')
                ->groupStart()
                    ->where('er.booking_id', null)
                    ->orWhere('b.id', null)
                ->groupEnd()
                ->get()
                ->getResultArray()
        );

        if ($orphanIds === []) {
            return;
        }

        if ($this->db->tableExists('notifications')) {
            $this->db->table('notifications')
                ->where('entity_type', 'external_request')
                ->whereIn('entity_id', $orphanIds)
                ->delete();
        }

        if ($this->db->tableExists('email_logs')) {
            $this->db->table('email_logs')
                ->where('entity_type', 'external_request')
                ->whereIn('entity_id', $orphanIds)
                ->delete();
        }

        $this->db->table('external_requests')
            ->whereIn('id', $orphanIds)
            ->delete();
    }

    public function down()
    {
        // Destructive cleanup migration; deleted records are not restored automatically.
    }
}
