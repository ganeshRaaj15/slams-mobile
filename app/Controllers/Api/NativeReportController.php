<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\ReportSnapshotBuilder;
use CodeIgniter\Shield\Entities\User;
use Dompdf\Dompdf;

class NativeReportController extends BaseController
{
    protected ReportSnapshotBuilder $builder;

    public function __construct()
    {
        helper('auth');
        $this->builder = new ReportSnapshotBuilder();
    }

    public function show()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        try {
            $report = $this->builder->build($user);
        } catch (\RuntimeException $e) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'report' => $report,
            'exports' => [
                'pdf_url' => base_url('/api/native/reports/export/pdf'),
                'csv_url' => base_url('/api/native/reports/export/csv'),
            ],
        ]);
    }

    public function downloadPdf()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $data = $this->builder->build($user);
        $dompdf = new Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml(view('reports/summary_pdf', $data));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'slams-report-' . $data['role'] . '-' . date('Ymd_His') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    public function downloadCsv()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $data = $this->builder->build($user);
        $filename = 'slams-report-' . $data['role'] . '-' . date('Ymd_His') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($this->builder->buildCsv($data));
    }

    protected function authorizedUser()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        if (! $user->inGroup('pic') && ! $user->inGroup('manager') && ! $user->inGroup('admin')) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'You do not have access to reports.',
                ]);
        }

        return $user;
    }
}
