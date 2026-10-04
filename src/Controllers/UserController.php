<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Services\NotificationService;
use App\Services\UserAdminException;
use App\Services\UserAdminService;
use App\Services\UserProfileUpload;
use PDO;

class UserController extends AbstractController
{
    private UserRepository $users;
    private UserAdminService $admin;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->users = new UserRepository($pdo);
        $this->admin = new UserAdminService($pdo, $this->users);
    }

    public function me(Request $req): void
    {
        $user = $req->user;
        if (($user['role'] ?? '') === 'rescuer') {
            $duty = $this->repo('rescuer_duty_status')->findBy('user_id', $user['id']);
            $user['duty_status'] = is_array($duty)
                ? (string) ($duty['status'] ?? 'off_duty')
                : 'off_duty';
        }
        Response::success(['user' => $user]);
    }

    public function index(Request $req): void
    {
        if ($this->rejectBadQuery($req, [
            'role' => ['resident', 'rescuer', 'admin'],
            'account_status' => ['active', 'pending', 'rejected', 'suspended'],
        ])) {
            return;
        }
        if (($req->query['role'] ?? null) === 'rescuer') {
            $this->indexRescuers($req);
            return;
        }

        $filters = [];
        if (!empty($req->query['role'])) {
            $filters['role'] = $req->query['role'];
        }
        if (!empty($req->query['account_status'])) {
            $filters['account_status'] = $req->query['account_status'];
        }
        if (!empty($req->query['q'])) {
            $filters['q'] = trim((string) $req->query['q']);
        }
        $result = $this->users->paginate($this->page($req), $this->perPage($req), $filters);
        $clean = array_map(fn($u) => $u->toArray(), $result['items']);
        Response::paginated($clean, $this->meta($result['page'], $result['per_page'], $result['total']));
    }

    private function indexRescuers(Request $req): void
    {
        $where = ["u.role = 'rescuer'"];
        $params = [];
        if (!empty($req->query['account_status'])) {
            $where[] = 'u.account_status = ?';
            $params[] = $req->query['account_status'];
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $page = $this->page($req);
        $perPage = $this->perPage($req);
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM users u {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT u.*, COALESCE(d.status, 'off_duty') AS duty_status, d.updated_at AS duty_updated_at
             FROM users u
             LEFT JOIN rescuer_duty_status d ON d.user_id = u.id
             {$whereSql}
             ORDER BY u.created_at DESC
             LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
        );
        $stmt->execute($params);
        $clean = array_map(function ($u) {
            unset($u['password_hash']);
            return $u;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));

        Response::paginated($clean, $this->meta($page, $perPage, $total));
    }

    public function show(Request $req): void
    {
        $user = $this->users->find($req->params['id']);
        if (!$user) {
            Response::error('NOT_FOUND', 'User not found', 404);
            return;
        }
        $isSelf = ($req->user['id'] ?? null) === $user->id();
        if (!$isSelf && !in_array('users.read', $req->permissions, true)) {
            Response::error('FORBIDDEN', 'Cannot view this user', 403);
            return;
        }
        Response::success(['user' => $user->toArray()]);
    }

    public function create(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('full_name')->string('full_name', 150)
            ->required('email')->email()
            ->required('password')->minLen('password', 8)
            ->optional('role')->in('role', ['resident', 'rescuer', 'admin'])
            ->optional('account_status')->in('account_status', ['active', 'pending', 'rejected', 'suspended'])
            ->optional('phone_number')->string('phone_number', 20)
            ->optional('address')->string('address', 1000);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        try {
            $user = $this->admin->create($req->body);
        } catch (UserAdminException $e) {
            Response::error($e->errorCode(), $e->publicMessage(), $e->httpStatus());
            return;
        }

        Response::success(['user' => $user->toArray()], 201);
    }

    public function destroy(Request $req): void
    {
        $user = $this->users->find($req->params['id']);
        if (!$user) {
            Response::error('NOT_FOUND', 'User not found', 404);
            return;
        }

        try {
            $this->admin->delete($user, $req->user);
        } catch (UserAdminException $e) {
            Response::error($e->errorCode(), $e->publicMessage(), $e->httpStatus());
            return;
        }

        Response::success(['deleted' => true, 'id' => $user->id()]);
    }

    public function update(Request $req): void
    {
        $id = $req->params['id'];
        $user = $this->users->find($id);
        if (!$user) {
            Response::error('NOT_FOUND', 'User not found', 404);
            return;
        }

        $isSelf = $req->user['id'] === $id;
        $isAdmin = ($req->user['role'] ?? '') === 'admin';
        if (!$isSelf && !$isAdmin) {
            Response::error('FORBIDDEN', 'Cannot update this user', 403);
            return;
        }

        $privileged = array_intersect_key($req->body, array_flip(['role', 'account_status']));
        if ($privileged !== [] && (!$isAdmin || $isSelf)) {
            Response::error('FORBIDDEN', 'Cannot change role or account status', 403);
            return;
        }

        $allowed = ['full_name', 'phone_number', 'address', 'profile_photo_url'];
        if ($isAdmin && !$isSelf) {
            $allowed = array_merge($allowed, ['account_status', 'role']);
        }
        $data = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $req->body)) {
                $data[$field] = $req->body[$field];
            }
        }
        $v = new \App\Validation\Validator($data);
        if (array_key_exists('full_name', $data)) {
            $v->optional('full_name')->string('full_name', 150);
        }
        if (array_key_exists('phone_number', $data)) {
            $v->optional('phone_number')->string('phone_number', 20);
        }
        if (array_key_exists('address', $data)) {
            $v->optional('address')->string('address', 1000);
        }
        if (array_key_exists('profile_photo_url', $data)) {
            $rawPhoto = $data['profile_photo_url'];
            if ($rawPhoto === null || (is_string($rawPhoto) && trim($rawPhoto) === '')) {
                $data['profile_photo_url'] = null;
            } else {
                $v->optional('profile_photo_url')->string('profile_photo_url', 2000);
                if (is_string($rawPhoto) && !UserProfileUpload::isAllowedUrl($rawPhoto)) {
                    Response::error('VALIDATION_ERROR', 'profile_photo_url must be an http(s) URL or an uploaded profile photo', 400);
                    return;
                }
            }
        }
        if (array_key_exists('role', $data)) {
            $v->optional('role')->in('role', ['resident', 'rescuer', 'admin']);
        }
        if (array_key_exists('account_status', $data)) {
            $v->optional('account_status')->in('account_status', ['active', 'pending', 'rejected', 'suspended']);
        }
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        try {
            $data = $this->admin->prepareUpdate($user, $data, $req->body, $isAdmin, $isSelf);
        } catch (UserAdminException $e) {
            Response::error($e->errorCode(), $e->publicMessage(), $e->httpStatus());
            return;
        }
        if ($data === []) {
            Response::error('VALIDATION_ERROR', 'No updatable fields provided', 400);
            return;
        }
        $previousPhoto = (string) ($user->profilePhotoUrl() ?? '');
        $this->users->update($id, $data);
        if (array_key_exists('profile_photo_url', $data)) {
            $nextPhoto = (string) ($data['profile_photo_url'] ?? '');
            if ($previousPhoto !== $nextPhoto) {
                (new UserProfileUpload())->deleteOwned($previousPhoto);
            }
        }
        $updated = $this->users->find($id);
        if (!$updated) {
            Response::error('NOT_FOUND', 'User not found', 404);
            return;
        }
        Response::success(['user' => $updated->toArray()]);
    }

    public function approveRescuer(Request $req): void
    {
        $this->resolveRescuer($req, 'approved');
    }

    public function toggleDuty(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('status')->in('status', ['on_duty', 'off_duty']);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $user = $this->users->find($req->params['id']);
        if (!$user || $user->role() !== 'rescuer') {
            Response::error('NOT_FOUND', 'Rescuer not found', 404);
            return;
        }

        $callerId = (string) ($req->user['id'] ?? '');
        $callerRole = (string) ($req->user['role'] ?? '');
        $canToggleSelf = $callerId !== '' && $callerId === $user->id() && $callerRole === 'rescuer';
        if (!$canToggleSelf) {
            Response::error('FORBIDDEN', 'Cannot change duty status for this rescuer', 403);
            return;
        }

        $dutyRepo = $this->repo('rescuer_duty_status');
        $existing = $dutyRepo->findBy('user_id', $user->id());
        if ($existing) {
            $dutyRepo->update($existing['id'], ['status' => $req->body['status']]);
        } else {
            $dutyRepo->create([
                'id' => Database::uuidV4(),
                'user_id' => $user->id(),
                'status' => $req->body['status'],
            ]);
        }
        Response::success(['duty_status' => $req->body['status']]);
    }

    public function rejectRescuer(Request $req): void
    {
        $this->resolveRescuer($req, 'rejected');
    }

    private function resolveRescuer(Request $req, string $decision): void
    {
        $id = $req->params['id'];
        $user = $this->users->find($id);
        if (!$user || $user->role() !== 'rescuer') {
            Response::error('NOT_FOUND', 'Rescuer not found', 404);
            return;
        }

        $approvalRepo = $this->repo('rescuer_approvals', ['id','user_id','reviewed_by','decision','remarks','reviewed_at']);
        $payload = [
            'user_id' => $id,
            'reviewed_by' => $req->user['id'],
            'decision' => $decision,
            'remarks' => $req->body['remarks'] ?? null,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ];
        $existing = $approvalRepo->findBy('user_id', $id);
        if ($existing) {
            $approvalRepo->update($existing['id'], $payload);
        } else {
            $approvalRepo->create(array_merge(['id' => Database::uuidV4()], $payload));
        }
        $this->users->update($id, ['account_status' => $decision === 'approved' ? 'active' : 'rejected']);

        $notif = new NotificationService($this->pdo);
        $notif->notify(
            $id,
            'rescuer_' . $decision,
            'Your rescuer application was ' . $decision . '.',
            'user',
            $id
        );

        Response::success(['status' => $decision]);
    }
}
