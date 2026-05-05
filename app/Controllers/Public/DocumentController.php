<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use App\Libraries\BookingDocumentLocator;
use CodeIgniter\Exceptions\PageNotFoundException;

class DocumentController extends BaseController
{
    public function viewPdf($filename)
    {
        helper('auth');
        $filename = basename((string) $filename);
        if ($filename === '' || ! preg_match('/^[A-Za-z0-9._-]+\.pdf$/i', $filename)) {
            throw new PageNotFoundException('Invalid filename');
        }
        
        // Check if user is logged in
        if (!auth()->loggedIn()) {
            if ($this->isNativeApiRequest()) {
                return $this->response
                    ->setStatusCode(401)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Unauthenticated.',
                    ]);
            }
            return redirect()->to('/login');
        }
        
        $user = auth()->user();
        
        $bookingModel = new \App\Models\BookingModel();

        $builder = $bookingModel
            ->select('bookings.id, bookings.user_id, laboratories.pic_email')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where('bookings.pdf_path', '/uploads/pdfs/' . $filename);

        if ($user->inGroup('pic')) {
            $builder->where('LOWER(TRIM(laboratories.pic_email)) =', strtolower(trim((string) $user->email)));
        } elseif (! $user->inGroup('admin') && ! $user->inGroup('manager')) {
            $builder->where('bookings.user_id', auth()->id());
        }

        if (! $builder->first()) {
            if ($this->isNativeApiRequest()) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Access denied.',
                    ]);
            }
            return redirect()->back()->with('error', 'Access denied.');
        }
        
        $documentLocator = new BookingDocumentLocator();
        $filePath = $documentLocator->resolvePdfPath($filename);

        if ($filePath === null) {
            throw new PageNotFoundException('File not found');
        }
        
        // Serve the PDF
        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setBody(file_get_contents($filePath));
    }

    protected function isNativeApiRequest(): bool
    {
        return str_starts_with(trim((string) $this->request->getPath(), '/'), 'api/native/');
    }
}
