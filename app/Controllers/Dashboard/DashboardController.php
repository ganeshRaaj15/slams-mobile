<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    /**
     * Main /dashboard route
     * Redirects user to their appropriate dashboard.
     */
    public function index()
    {
        helper('auth');

        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();

        // Role routing
        if ($user->inGroup('admin')) {
            return redirect()->to('/dashboard/admin');
        }

        if ($user->inGroup('manager')) {
            return redirect()->to('/dashboard/manager');
        }

        if ($user->inGroup('pic')) {
            return redirect()->to('/dashboard/pic');
        }

        if ($user->inGroup('technician')) {
            return redirect()->to('/dashboard/technician');
        }

        if ($user->inGroup('external')) {
            return redirect()->to('/dashboard/external');
        }

        if ($user->inGroup('staff')) {
            return redirect()->to('/dashboard/student');
        }

        if ($user->inGroup('student')) {
            return redirect()->to('/dashboard/student');
        }

        // Catch-all fallback: deny access
        return redirect()->back()->with('error', 'Dashboard access denied.');
    }
}

