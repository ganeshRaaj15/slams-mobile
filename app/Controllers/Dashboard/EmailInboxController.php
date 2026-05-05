<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\EmailLogModel;

class EmailInboxController extends BaseController
{
    protected EmailLogModel $emailLogModel;

    public function __construct()
    {
        helper('auth');
        $this->emailLogModel = new EmailLogModel();
    }

    public function index()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        $userEmail = strtolower(trim((string) $user->email));
        $query = $this->emailLogModel
            ->groupStart()
            ->where('user_id', $user->id)
            ->orWhere('LOWER(to_email) =', $userEmail)
            ->groupEnd()
            ->orderBy('created_at', 'DESC');

        $emails = $query->paginate(12);

        return view('dashboard/emails/index', [
            'title' => 'Email Inbox | FKMP Smart Lab',
            'page' => 'Email Inbox',
            'layout' => $this->resolveLayout($user),
            'user' => $user,
            'emails' => $emails,
            'pager' => $this->emailLogModel->pager,
        ]);
    }

    public function show(int $id)
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        $userEmail = strtolower(trim((string) $user->email));
        $email = $this->emailLogModel
            ->where('id', $id)
            ->groupStart()
            ->where('user_id', $user->id)
            ->orWhere('LOWER(to_email) =', $userEmail)
            ->groupEnd()
            ->first();

        if (! $email) {
            return redirect()->to('/dashboard/emails')->with('error', 'Email not found.');
        }

        return view('dashboard/emails/show', [
            'title' => 'Email Preview | FKMP Smart Lab',
            'page' => 'Email Preview',
            'layout' => $this->resolveLayout($user),
            'user' => $user,
            'email' => $email,
        ]);
    }

    protected function resolveLayout($user): string
    {
        if ($user->inGroup('admin')) {
            return 'layouts/main_admin';
        }

        if ($user->inGroup('technician')) {
            return 'layouts/main_technician';
        }

        return 'layouts/main_user';
    }
}
