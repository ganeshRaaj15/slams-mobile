<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\LaboratoryModel;
use App\Models\LabReservationModel;
use CodeIgniter\Shield\Entities\User;

class NativeReservationController extends BaseController
{
    private LabReservationModel $model;
    private LaboratoryModel $labModel;

    public function __construct()
    {
        helper('auth');
        $this->model    = new LabReservationModel();
        $this->labModel = new LaboratoryModel();
    }

    // -------------------------------------------------------------------------
    // GET /api/native/reservations
    // -------------------------------------------------------------------------
    public function index()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $this->forbidden();
        }

        $isAdmin   = $user->inGroup('admin');
        $labId     = (int) $this->request->getGet('lab_id');
        $type      = trim((string) $this->request->getGet('type'));
        $allowedLabIds = $isAdmin ? [] : $this->picLabIds($user);

        if (! $isAdmin && $allowedLabIds === []) {
            return $this->response->setJSON([
                'status'       => 'success',
                'reservations' => [],
                'labs'         => [],
            ]);
        }

        $builder = $this->model
            ->select('lab_reservations.*, laboratories.name AS lab_name')
            ->join('laboratories', 'laboratories.id = lab_reservations.lab_id', 'left')
            ->orderBy('lab_reservations.created_at', 'DESC');

        if (! $isAdmin) {
            $builder->whereIn('lab_reservations.lab_id', $allowedLabIds);
        }
        if ($labId > 0) {
            if (! $isAdmin && ! in_array($labId, $allowedLabIds, true)) {
                $labId = 0;
            }
            if ($labId > 0) {
                $builder->where('lab_reservations.lab_id', $labId);
            }
        }
        if (in_array($type, ['manual', 'class'], true)) {
            $builder->where('lab_reservations.type', $type);
        }

        $reservations = $builder->findAll();

        $labsQuery = $this->labModel->orderBy('name', 'ASC');
        if (! $isAdmin && $allowedLabIds !== []) {
            $labsQuery->whereIn('id', $allowedLabIds);
        }
        $labs = $isAdmin ? $this->labModel->orderBy('name', 'ASC')->findAll() : $labsQuery->findAll();

        return $this->response->setJSON([
            'status'       => 'success',
            'reservations' => array_map(fn(array $r): array => $this->serialize($r), $reservations),
            'labs'         => array_map(fn(array $l): array => [
                'id'   => (int) $l['id'],
                'name' => (string) ($l['name'] ?? ''),
            ], $labs),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/native/reservations/:id
    // -------------------------------------------------------------------------
    public function show(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $this->forbidden();
        }

        $record = $this->findAccessible($id, $user);
        if (! $record) {
            return $this->notFound();
        }

        $labsQuery = $this->labModel->orderBy('name', 'ASC');
        if (! $user->inGroup('admin')) {
            $labIds = $this->picLabIds($user);
            if ($labIds !== []) {
                $labsQuery->whereIn('id', $labIds);
            }
        }

        return $this->response->setJSON([
            'status'      => 'success',
            'reservation' => $this->serialize($record),
            'labs'        => array_map(fn(array $l): array => [
                'id'   => (int) $l['id'],
                'name' => (string) ($l['name'] ?? ''),
            ], $labsQuery->findAll()),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/native/reservations
    // -------------------------------------------------------------------------
    public function store()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $this->forbidden();
        }

        $allowedLabIds = $user->inGroup('admin') ? null : $this->picLabIds($user);
        $payload = $this->buildPayload($allowedLabIds);
        if (is_string($payload)) {
            return $this->unprocessable($payload);
        }

        $payload['created_by'] = $user->id;
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $this->model->insert($payload);
        $newId = $this->model->getInsertID();

        return $this->response->setJSON([
            'status'         => 'success',
            'message'        => 'Reservation created successfully.',
            'reservation_id' => (int) $newId,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/native/reservations/:id
    // -------------------------------------------------------------------------
    public function update(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $this->forbidden();
        }

        if (! $this->findAccessible($id, $user)) {
            return $this->notFound();
        }

        $allowedLabIds = $user->inGroup('admin') ? null : $this->picLabIds($user);
        $payload = $this->buildPayload($allowedLabIds);
        if (is_string($payload)) {
            return $this->unprocessable($payload);
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');
        $this->model->update($id, $payload);

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Reservation updated successfully.',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/native/reservations/:id/delete
    // -------------------------------------------------------------------------
    public function delete(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $this->forbidden();
        }

        if (! $this->findAccessible($id, $user)) {
            return $this->notFound();
        }

        $this->model->delete($id);

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Reservation deleted.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authorizedUser(): ?User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }
        return ($user->inGroup('pic') || $user->inGroup('admin')) ? $user : null;
    }

    private function picLabIds(User $user): array
    {
        $email = strtolower(trim((string) $user->email));
        return array_column(
            $this->labModel->where('LOWER(TRIM(pic_email)) =', $email)->findAll(),
            'id'
        );
    }

    private function findAccessible(int $id, User $user): ?array
    {
        $record = $this->model
            ->select('lab_reservations.*, laboratories.name AS lab_name')
            ->join('laboratories', 'laboratories.id = lab_reservations.lab_id', 'left')
            ->where('lab_reservations.id', $id)
            ->first();

        if (! $record) {
            return null;
        }
        if ($user->inGroup('admin')) {
            return $record;
        }
        $allowed = $this->picLabIds($user);
        return in_array((int) $record['lab_id'], $allowed, true) ? $record : null;
    }

    /** @return array<string,mixed>|string */
    private function buildPayload(?array $allowedLabIds): array|string
    {
        $recurrence = $this->request->getPost('recurrence');
        if (! in_array($recurrence, ['none', 'weekly'], true)) {
            return 'Invalid recurrence type.';
        }

        $labId = (int) $this->request->getPost('lab_id');
        if ($labId <= 0) {
            return 'Please select a laboratory.';
        }
        if ($allowedLabIds !== null && ! in_array($labId, $allowedLabIds, true)) {
            return 'You can only manage reservations for your assigned laboratory.';
        }

        $type = $this->request->getPost('type');
        if (! in_array($type, ['manual', 'class'], true)) {
            return 'Invalid reservation type.';
        }

        $title = trim((string) $this->request->getPost('title'));
        if ($title === '') {
            return 'Please provide a title.';
        }

        $start = $this->normalizeTime((string) $this->request->getPost('start_time'));
        $end   = $this->normalizeTime((string) $this->request->getPost('end_time'));
        if (! $start || ! $end || $start >= $end) {
            return 'Invalid time range. End time must be after start time.';
        }

        $date       = null;
        $dow        = null;
        $validFrom  = null;
        $validUntil = null;

        if ($recurrence === 'none') {
            $date = trim((string) $this->request->getPost('date'));
            if (! $this->isValidDate($date)) {
                return 'Please provide a valid date (YYYY-MM-DD).';
            }
        } else {
            $dow = (int) $this->request->getPost('day_of_week');
            if ($dow < 0 || $dow > 6) {
                return 'Please select a valid day of the week.';
            }
            $validFrom  = trim((string) $this->request->getPost('valid_from'))  ?: null;
            $validUntil = trim((string) $this->request->getPost('valid_until')) ?: null;
            if ($validFrom && ! $this->isValidDate($validFrom)) {
                return 'Invalid valid-from date.';
            }
            if ($validUntil && ! $this->isValidDate($validUntil)) {
                return 'Invalid valid-until date.';
            }
            if ($validFrom && $validUntil && $validFrom >= $validUntil) {
                return 'Valid-from must be before valid-until.';
            }
        }

        return [
            'lab_id'      => $labId,
            'type'        => $type,
            'title'       => $title,
            'recurrence'  => $recurrence,
            'date'        => $date,
            'day_of_week' => $dow,
            'start_time'  => $start,
            'end_time'    => $end,
            'valid_from'  => $validFrom,
            'valid_until' => $validUntil,
            'notes'       => trim((string) $this->request->getPost('notes')) ?: null,
        ];
    }

    private function serialize(array $r): array
    {
        return [
            'id'          => (int) $r['id'],
            'lab_id'      => (int) $r['lab_id'],
            'lab_name'    => (string) ($r['lab_name'] ?? ''),
            'type'        => (string) ($r['type'] ?? 'manual'),
            'title'       => (string) ($r['title'] ?? ''),
            'recurrence'  => (string) ($r['recurrence'] ?? 'none'),
            'date'        => (string) ($r['date'] ?? ''),
            'day_of_week' => isset($r['day_of_week']) && $r['day_of_week'] !== null ? (int) $r['day_of_week'] : null,
            'start_time'  => substr((string) ($r['start_time'] ?? ''), 0, 5),
            'end_time'    => substr((string) ($r['end_time'] ?? ''), 0, 5),
            'valid_from'  => (string) ($r['valid_from'] ?? ''),
            'valid_until' => (string) ($r['valid_until'] ?? ''),
            'notes'       => (string) ($r['notes'] ?? ''),
            'created_at'  => (string) ($r['created_at'] ?? ''),
        ];
    }

    private function normalizeTime(string $t): ?string
    {
        $t = trim($t);
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $t)) {
            return substr($t, 0, 5) . ':00';
        }
        return null;
    }

    private function isValidDate(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    }

    private function forbidden()
    {
        return $this->response->setStatusCode(403)->setJSON([
            'status'  => 'error',
            'message' => 'Reservations management is limited to PIC and admin users.',
        ]);
    }

    private function notFound()
    {
        return $this->response->setStatusCode(404)->setJSON([
            'status'  => 'error',
            'message' => 'Reservation not found.',
        ]);
    }

    private function unprocessable(string $message)
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'  => 'error',
            'message' => $message,
        ]);
    }
}
