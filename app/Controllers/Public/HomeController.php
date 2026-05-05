<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use App\Models\LaboratoryModel;
use App\Models\BookingModel;

class HomeController extends BaseController
{
    /**
     * Landing Page / Homepage
     * - If user is logged in, redirect to relevant dashboard
     * - Otherwise, show public homepage
     */
    public function index()
    {
        helper('auth');

        // Auto-redirect logged-in users to their respective dashboards
        if (auth()->loggedIn()) {
            $user = auth()->user();

            if ($user->inGroup('admin'))     return redirect()->to('/dashboard/admin');
            if ($user->inGroup('manager'))   return redirect()->to('/dashboard/manager');
            if ($user->inGroup('pic'))       return redirect()->to('/dashboard/pic');
            if ($user->inGroup('technician')) return redirect()->to('/dashboard/technician');
            if ($user->inGroup('external'))  return redirect()->to('/dashboard/external');
            if ($user->inGroup('staff'))     return redirect()->to('/dashboard/student');
            if ($user->inGroup('student'))   return redirect()->to('/dashboard/student');
        }

        // -------------------------------------------------------------
        // PUBLIC HOMEPAGE DATA
        // -------------------------------------------------------------
        $labModel = new LaboratoryModel();
        $bookingModel = new BookingModel();

        $labs = $labModel->orderBy('name', 'ASC')->findAll();

        $stats = [
            'lab_count'     => count($labs),
            'total_bookings'=> $bookingModel->whereIn('status', BookingModel::CORE_STATUSES)->countAllResults(),
            'approved'      => $bookingModel->where('status', 'APPROVED')->countAllResults(),
        ];

        return view('public/home/index', [
            'labs'  => $labs,
            'stats' => $stats,
        ]);
    }


    /**
     * Contact Page
     */
    public function contact()
    {
        return view('public/contact');
    }
}
