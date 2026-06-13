<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\LabReservationModel;
use App\Models\LaboratoryModel;
use CodeIgniter\Shield\Entities\User;

class NativeAdminLabReservationController extends BaseController
{
    protected LabReservationModel $reservationModel;
    protected LaboratoryModel $labModel;

    public function __construct()
    {
        helper('auth');
        $this->reservationModel = new LabReservationModel();
        $this->labModel = new LaboratoryModel();
    }

    public function index()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

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

        $reservations = $builder->orderBy('lab_reservations.start_at', 'DESC')->findAll();

        return $this->response->setJSON([
            'status' => 'success',
            'reservations' => array_map(fn(array $reservation): array => $this->serializeReservation($reservation), $reservations),
            'labs' => array_map(fn(array $lab): array => $this->serializeLabOption($lab), $this->manageableLabs($user)),
            'filters' => $filters,
            'permissions' => [
                'scoped_to_pic_labs' => $this->isPicUser($user),
            ],
        ]);
    }

    public function show(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $reservation = $this->reservationWithLab($id);
        if (! $reservation) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => 'Reservation not found.']);
        }
        if (! $this->canManageLabId((int) ($reservation['lab_id'] ?? 0), $user)) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'reservation' => $this->serializeReservation($reservation),
            'labs' => array_map(fn(array $lab): array => $this->serializeLabOption($lab), $this->manageableLabs($user)),
        ]);
    }

    public function store()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $payload = $this->collectPayload();
        if ($error = $this->validatePayload($payload, 0, $user)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $error]);
        }

        $payload['created_by'] = $user->id;
        $reservationId = (int) $this->reservationModel->insert($payload, true);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Lab reservation created successfully.',
            'reservation' => $this->serializeReservation($this->reservationWithLab($reservationId) ?? $this->reservationModel->find($reservationId) ?? []),
        ]);
    }

    public function update(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $reservation = $this->reservationModel->find($id);
        if (! $reservation) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => 'Reservation not found.']);
        }

        $payload = $this->collectPayload();
        if ($error = $this->validatePayload($payload, $id, $user, $reservation)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $error]);
        }

        $this->reservationModel->update($id, $payload);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Lab reservation updated successfully.',
            'reservation' => $this->serializeReservation($this->reservationWithLab($id) ?? $this->reservationModel->find($id) ?? []),
        ]);
    }

    public function delete(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $reservation = $this->reservationModel->find($id);
        if (! $reservation) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => 'Reservation not found.']);
        }
        if (! $this->canManageLabId((int) ($reservation['lab_id'] ?? 0), $user)) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $this->reservationModel->delete($id);

        return $this->response->setJSON(['status' => 'success', 'message' => 'Lab reservation deleted successfully.']);
    }

    protected function collectPayload(): array
    {
        $json = $this->request->getJSON(true);
        $data = is_array($json) && $json !== [] ? $json : ($this->request->getPost() ?: []);

        return [
            'lab_id' => (int) ($data['lab_id'] ?? 0),
            'title' => trim((string) ($data['title'] ?? '')),
            'reservation_type' => trim((string) ($data['reservation_type'] ?? '')) ?: 'reservation',
            'start_at' => trim((string) ($data['start_at'] ?? '')),
            'end_at' => trim((string) ($data['end_at'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'status' => (string) ($data['status'] ?? 'active') === 'cancelled' ? 'cancelled' : 'active',
        ];
    }

    protected function validatePayload(array $payload, int $ignoreId, User $user, ?array $existing = null): ?string
    {
        $labId = (int) ($payload['lab_id'] ?? 0);
        if (! $this->canManageLabId($labId, $user)) {
            return 'You can only manage reservations for laboratories assigned to you.';
        }
        if ($existing && ! $this->canManageLabId((int) ($existing['lab_id'] ?? 0), $user)) {
            return 'You are not allowed to update this reservation.';
        }
        if (trim((string) ($payload['title'] ?? '')) === '') {
            return 'Reservation title is required.';
        }
        if (! in_array((string) ($payload['reservation_type'] ?? ''), ['reservation', 'walk_in', 'class', 'event', 'maintenance'], true)) {
            return 'Choose a valid reservation type.';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($payload['start_at'] ?? ''))) {
            return 'Start date and time are required in YYYY-MM-DD HH:MM:SS format.';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($payload['end_at'] ?? ''))) {
            return 'End date and time are required in YYYY-MM-DD HH:MM:SS format.';
        }
        if ((string) $payload['end_at'] <= (string) $payload['start_at']) {
            return 'Reservation end time must be later than the start time.';
        }
        if (($payload['status'] ?? 'active') === 'active' && $this->reservationModel->overlaps($labId, (string) $payload['start_at'], (string) $payload['end_at'], $ignoreId)) {
            return 'This reservation overlaps another active full-lab reservation.';
        }

        return null;
    }

    protected function serializeReservation(array $reservation): array
    {
        return [
            'id' => (int) ($reservation['id'] ?? 0),
            'lab_id' => (int) ($reservation['lab_id'] ?? 0),
            'lab_name' => (string) ($reservation['lab_name'] ?? ''),
            'lab_room' => (string) ($reservation['lab_room'] ?? ''),
            'title' => (string) ($reservation['title'] ?? ''),
            'reservation_type' => (string) ($reservation['reservation_type'] ?? 'reservation'),
            'start_at' => (string) ($reservation['start_at'] ?? ''),
            'end_at' => (string) ($reservation['end_at'] ?? ''),
            'notes' => (string) ($reservation['notes'] ?? ''),
            'status' => (string) ($reservation['status'] ?? 'active'),
            'created_by' => isset($reservation['created_by']) ? (int) $reservation['created_by'] : null,
        ];
    }

    protected function serializeLabOption(array $lab): array
    {
        return [
            'id' => (int) $lab['id'],
            'name' => (string) ($lab['name'] ?? ''),
            'room' => (string) ($lab['room'] ?? ''),
            'label' => trim(((string) ($lab['name'] ?? '')) . ' - ' . ((string) ($lab['room'] ?? ''))),
        ];
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

    protected function authorizedUser()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'error', 'message' => 'Unauthenticated.']);
        }
        if (! $user->inGroup('admin') && ! $user->inGroup('pic')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        return $user;
    }

    protected function isPicUser(User $user): bool
    {
        return $user->inGroup('pic') && ! $user->inGroup('admin');
    }

    protected function manageableLabIds(User $user): array
    {
        if (! $this->isPicUser($user)) {
            return array_map(static fn(array $lab): int => (int) $lab['id'], $this->labModel->findAll());
        }

        return array_map(
            static fn(array $lab): int => (int) $lab['id'],
            $this->labModel
                ->where('LOWER(TRIM(pic_email)) =', strtolower(trim((string) $user->email)))
                ->findAll()
        );
    }

    protected function manageableLabs(User $user): array
    {
        $labIds = $this->manageableLabIds($user);
        if ($labIds === []) {
            return [];
        }

        return $this->labModel->whereIn('id', $labIds)->orderBy('name', 'ASC')->findAll();
    }

    protected function canManageLabId(int $labId, User $user): bool
    {
        return $labId > 0 && in_array($labId, $this->manageableLabIds($user), true);
    }
}
