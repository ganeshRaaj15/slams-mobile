<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\MaintenanceRecordModel;

class TechnicianDashboard extends BaseController
{
    public function index()
    {
        helper('auth');

        if (! auth()->loggedIn() || ! auth()->user()->inGroup('technician')) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }

        $user = auth()->user();
        $maintenanceModel = new MaintenanceRecordModel();
        $openStatuses = $maintenanceModel->openStatuses();

        $stats = [
            'open_total' => (new MaintenanceRecordModel())->whereIn('status', $openStatuses)->countAllResults(),
            'assigned_to_me' => (new MaintenanceRecordModel())->where('assigned_technician_id', $user->id)->whereIn('status', $openStatuses)->countAllResults(),
            'awaiting_test' => (new MaintenanceRecordModel())->where('status', 'testing')->countAllResults(),
            'completed_this_month' => (new MaintenanceRecordModel())->where('status', 'completed')->where('completed_at >=', date('Y-m-01 00:00:00'))->countAllResults(),
        ];

        $recentRecords = $maintenanceModel->withRelations()
            ->orderBy('maintenance_records.created_at', 'DESC')
            ->findAll(8);

        $user->role = 'Technician';

        return view('dashboard/technician/index', [
            'user' => $user,
            'roleLabel' => 'Technician',
            'page' => 'Technician Dashboard',
            'stats' => $stats,
            'recentRecords' => $recentRecords,
            'statusLabels' => $maintenanceModel->workflowLabels(),
        ]);
    }
}
