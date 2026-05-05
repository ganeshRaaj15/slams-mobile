<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use App\Models\LaboratoryModel;
use App\Models\AssetModel;
use App\Models\FacultyModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class LaboratoryController extends BaseController
{
    protected $laboratories;
    protected $assets;
    protected $faculties;

    public function __construct()
    {
        $this->laboratories = new LaboratoryModel();
        $this->assets       = new AssetModel();
        $this->faculties    = new FacultyModel();
        helper('auth');
    }

    /**
     * List all laboratories (public).
     */
    public function index()
    {
        $search = $this->request->getGet('q');

        if ($search) {
            $labs = $this->laboratories
                ->like('name', $search)
                ->orderBy('name', 'ASC')
                ->findAll();
        } else {
            $labs = $this->laboratories
                ->orderBy('name', 'ASC')
                ->findAll();
        }

        // Enrich PIC details from user profiles (match by PIC email)
        $picEmails = [];
        foreach ($labs as $lab) {
            if (!empty($lab['pic_email'])) {
                $picEmails[] = strtolower(trim($lab['pic_email']));
            }
        }
        $picEmails = array_values(array_unique($picEmails));

        if (!empty($picEmails)) {
            $db = \Config\Database::connect();
            $identityRows = $db->table('auth_identities')
                ->select('user_id, secret')
                ->where('type', 'email_password')
                ->whereIn('LOWER(secret)', $picEmails)
                ->get()
                ->getResultArray();

            $emailToUserId = [];
            foreach ($identityRows as $row) {
                $emailToUserId[strtolower(trim((string) $row['secret']))] = (int)$row['user_id'];
            }

            $userIds = array_values(array_unique(array_values($emailToUserId)));
            $profileMap = [];
            if (!empty($userIds)) {
                $profiles = $db->table('users')
                    ->select('id, full_name, phone, profile_photo')
                    ->whereIn('id', $userIds)
                    ->get()
                    ->getResultArray();

                foreach ($profiles as $profile) {
                    $profileMap[(int)$profile['id']] = $profile;
                }
            }

            foreach ($labs as &$lab) {
                $email = strtolower(trim((string)($lab['pic_email'] ?? '')));
                if ($email === '' || !isset($emailToUserId[$email])) {
                    continue;
                }
                $userId = $emailToUserId[$email];
                $profile = $profileMap[$userId] ?? null;
                if (!$profile) {
                    continue;
                }
                if (!empty($profile['full_name'])) {
                    $lab['pic_name'] = $profile['full_name'];
                }
                if (!empty($profile['phone'])) {
                    $lab['pic_phone'] = $profile['phone'];
                }
                if (!empty($profile['profile_photo'])) {
                    $lab['pic_image'] = $profile['profile_photo'];
                }
            }
            unset($lab);
        }

        return view('public/laboratories/index', [
            'labs'   => $labs,
            'search' => $search,
        ]);
    }

    /**
     * Show a single laboratory with equipment + booking interface.
     */
    public function show($id = null)
    {
        $id = (int) $id;

        $lab = $this->laboratories->find($id);
        if (! $lab) {
            throw PageNotFoundException::forPageNotFound('Laboratory not found.');
        }

        $assets    = $this->assets->where('lab_id', $id)->findAll();
        $services  = $this->servicesForLab($id);
        $faculties = $this->faculties->getAllForDropdown();
        $userProfile = null;

        // Determine booking mode:
        //  - 'uthm'     logged-in, NOT in 'external' group (students/staff)
        //  - 'external' logged-in external user
        //  - 'guest'    not logged in
        $bookingMode = 'guest';

        if (function_exists('auth') && auth()->loggedIn()) {
            $user = auth()->user();

            if ($user->inGroup('external')) {
                $bookingMode = 'external';
            } else {
                $bookingMode = 'uthm';
            }

            $db = \Config\Database::connect();
            $userProfile = $db->table('users')
                ->select('id, full_name, phone, faculty_id, profile_photo')
                ->where('id', $user->id)
                ->get()
                ->getRowArray();
        }

        if (!empty($lab['pic_email'])) {
            $db = \Config\Database::connect();
            $picEmail = strtolower(trim((string) $lab['pic_email']));
            $picIdentity = $db->table('auth_identities')
                ->where('type', 'email_password')
                ->where('LOWER(secret) =', $picEmail)
                ->get()
                ->getRowArray();

            if ($picIdentity) {
                $picProfile = $db->table('users')
                    ->select('full_name, phone, profile_photo')
                    ->where('id', $picIdentity['user_id'])
                    ->get()
                    ->getRowArray();

                if (!empty($picProfile['full_name'])) {
                    $lab['pic_name'] = $picProfile['full_name'];
                }
                if (!empty($picProfile['phone'])) {
                    $lab['pic_phone'] = $picProfile['phone'];
                }
                if (!empty($picProfile['profile_photo'])) {
                    $lab['pic_image'] = $picProfile['profile_photo'];
                }
            }
        }

        return view('public/laboratories/show', [
            'lab'         => $lab,
            'assets'      => $assets,
            'services'    => $services,
            'faculties'   => $faculties,
            'bookingMode' => $bookingMode,
            'userProfile' => $userProfile,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function servicesForLab(int $labId): array
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('lab_services')) {
            return [];
        }

        return $db->table('lab_services ls')
            ->select("
                ls.id,
                ls.field_name,
                ls.service_name,
                ls.acceptance_criteria,
                ls.calibration_status,
                GROUP_CONCAT(
                    DISTINCT NULLIF(TRIM(sem.equipment_model), '')
                    ORDER BY sem.sort_order ASC
                    SEPARATOR ' | '
                ) AS equipment_models
            ", false)
            ->join('service_equipment_models sem', 'sem.lab_service_id = ls.id', 'left')
            ->where('ls.laboratory_id', $labId)
            ->where('ls.is_active', 1)
            ->groupBy('ls.id')
            ->orderBy('ls.service_name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
