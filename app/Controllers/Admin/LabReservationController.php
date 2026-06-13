<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LabReservationModel;
use App\Models\LaboratoryModel;
use CodeIgniter\Shield\Entities\User;

class LabReservationController extends BaseController
{
    protected LabReservationModel $reservationModel;
    protected LaboratoryModel $labModel;

    public function __construct()
    {
        helper('auth');

        if (! auth()->loggedIn() || (! auth()->user()->inGroup('admin') && ! auth()->user()->inGroup('pic'))) {
            redirect()->to('/')->with('error', 'You are not authorized to access this page.')->send();
            exit;
        }

        $this->reservationModel = new LabReservationModel();
        $this->labModel = new LaboratoryModel();
    }

    public function index()
    {
        $user = auth()->user();
        $filters = [
            'lab_id' => max((int) $this->request->getGet('lab_id'), 0),
            'status' => trim((string) $this->request->getGet('status')),
            'q' => trim((string) $this->request->getGet('q')),
        ];
        if (! in_array($filters['status'], ['active', 'cancelled'], true)) {
            $filters['status'] = '';
        }

        $labIds = $this->manageableLabIds($user);
        $builder = $this->reservationModel
            ->select('lab_reservations.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = lab_reservations.lab_id', 'left');

        if ($labIds === []) {
            $builder->where('1 = 0');
        } else {
            $builder->whereIn('lab_reservations.lab_id', $labIds);
        }

        if ($filters['lab_id'] > 0) {
            $builder->where('lab_reservations.lab_id', $filters['lab_id']);
        }
        if ($filters['status'] !== '') {
            $builder->where('lab_reservations.status', $filters['status']);
        }
        if ($filters['q'] !== '') {
            $builder->groupStart()
                ->like('lab_reservations.title', $filters['q'])
                ->orLike('lab_reservations.notes', $filters['q'])
                ->orLike('laboratories.name', $filters['q'])
                ->groupEnd();
        }

        $reservations = $builder
            ->orderBy('lab_reservations.start_at', 'DESC')
            ->findAll();

        return view('admin/reservations/index', [
            'reservations' => $reservations,
            'labs' => $this->manageableLabs($user),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('admin/reservations/form', [
            'mode' => 'create',
            'reservation' => null,
            'labs' => $this->manageableLabs(auth()->user()),
        ]);
    }

    public function edit(int $id)
    {
        $reservation = $this->reservationWithLab($id);
        if (! $reservation) {
            return redirect()->to('/admin/reservations')->with('error', 'Reservation not found.');
        }
        if (! $this->canManageLabId((int) ($reservation['lab_id'] ?? 0))) {
            return redirect()->to('/admin/reservations')->with('error', 'You are not allowed to edit this reservation.');
        }

        return view('admin/reservations/form', [
            'mode' => 'edit',
            'reservation' => $reservation,
            'labs' => $this->manageableLabs(auth()->user()),
        ]);
    }

    public function store()
    {
        $payload = $this->collectPayload();
        if ($error = $this->validatePayload($payload)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $payload['created_by'] = auth()->id();
        $this->reservationModel->insert($payload);

        return redirect()->to('/admin/reservations')->with('message', 'Lab reservation created successfully.');
    }

    public function update(int $id)
    {
        $reservation = $this->reservationModel->find($id);
        if (! $reservation) {
            return redirect()->to('/admin/reservations')->with('error', 'Reservation not found.');
        }
        if (! $this->canManageLabId((int) ($reservation['lab_id'] ?? 0))) {
            return redirect()->to('/admin/reservations')->with('error', 'You are not allowed to update this reservation.');
        }

        $payload = $this->collectPayload();
        if ($error = $this->validatePayload($payload, $id)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $this->reservationModel->update($id, $payload);

        return redirect()->to('/admin/reservations')->with('message', 'Lab reservation updated successfully.');
    }

    public function delete(int $id)
    {
        $reservation = $this->reservationModel->find($id);
        if (! $reservation) {
            return redirect()->to('/admin/reservations')->with('error', 'Reservation not found.');
        }
        if (! $this->canManageLabId((int) ($reservation['lab_id'] ?? 0))) {
            return redirect()->to('/admin/reservations')->with('error', 'You are not allowed to delete this reservation.');
        }

        $this->reservationModel->delete($id);

        return redirect()->to('/admin/reservations')->with('message', 'Lab reservation deleted successfully.');
    }

    protected function collectPayload(): array
    {
        $startDate = trim((string) $this->request->getPost('start_date'));
        $startTime = trim((string) $this->request->getPost('start_time'));
        $endDate = trim((string) $this->request->getPost('end_date'));
        $endTime = trim((string) $this->request->getPost('end_time'));

        return [
            'lab_id' => (int) $this->request->getPost('lab_id'),
            'title' => trim((string) $this->request->getPost('title')),
            'reservation_type' => trim((string) $this->request->getPost('reservation_type')) ?: 'reservation',
            'start_at' => $startDate !== '' && $startTime !== '' ? $startDate . ' ' . $startTime . ':00' : '',
            'end_at' => $endDate !== '' && $endTime !== '' ? $endDate . ' ' . $endTime . ':00' : '',
            'notes' => trim((string) $this->request->getPost('notes')),
            'status' => $this->request->getPost('status') === 'cancelled' ? 'cancelled' : 'active',
        ];
    }

    protected function validatePayload(array $payload, int $ignoreId = 0): ?string
    {
        if (! $this->canManageLabId((int) ($payload['lab_id'] ?? 0))) {
            return 'You can only manage reservations for your assigned laboratories.';
        }
        if (trim((string) ($payload['title'] ?? '')) === '') {
            return 'Reservation title is required.';
        }
        if (! in_array((string) ($payload['reservation_type'] ?? ''), ['reservation', 'walk_in', 'class', 'event', 'maintenance'], true)) {
            return 'Choose a valid reservation type.';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($payload['start_at'] ?? ''))) {
            return 'Start date and time are required.';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($payload['end_at'] ?? ''))) {
            return 'End date and time are required.';
        }
        if ((string) $payload['end_at'] <= (string) $payload['start_at']) {
            return 'Reservation end time must be later than the start time.';
        }
        if (($payload['status'] ?? 'active') === 'active' && $this->reservationModel->overlaps((int) $payload['lab_id'], (string) $payload['start_at'], (string) $payload['end_at'], $ignoreId)) {
            return 'This reservation overlaps another active full-lab reservation.';
        }

        return null;
    }

    protected function reservationWithLab(int $id): ?array
    {
        $reservation = $this->reservationModel
            ->select('lab_reservations.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = lab_reservations.lab_id', 'left')
            ->where('lab_reservations.id', $id)
            ->first();

        return is_array($reservation) ? $reservation : null;
    }

    protected function manageableLabIds(?User $user = null): array
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        if ($user->inGroup('admin')) {
            return array_map(static fn(array $lab): int => (int) $lab['id'], $this->labModel->findAll());
        }

        return array_map(
            static fn(array $lab): int => (int) $lab['id'],
            $this->labModel
                ->where('LOWER(TRIM(pic_email)) =', strtolower(trim((string) $user->email)))
                ->findAll()
        );
    }

    protected function manageableLabs(?User $user = null): array
    {
        $labIds = $this->manageableLabIds($user);
        if ($labIds === []) {
            return [];
        }

        return $this->labModel->whereIn('id', $labIds)->orderBy('name', 'ASC')->findAll();
    }

    protected function canManageLabId(int $labId): bool
    {
        return $labId > 0 && in_array($labId, $this->manageableLabIds(auth()->user()), true);
    }
}
