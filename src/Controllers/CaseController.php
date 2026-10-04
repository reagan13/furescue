<?php

namespace App\Controllers;

use App\Database;
use App\Entity\RescueCase;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\CaseRepository;
use App\Repositories\UserRepository;
use App\Services\CaseProofUpload;
use App\Services\CaseWorkflowService;
use App\Services\NotificationService;
use PDO;

class CaseController extends AbstractController
{
    private CaseRepository $cases;
    private UserRepository $users;
    private CaseWorkflowService $workflow;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->cases = new CaseRepository($pdo);
        $this->users = new UserRepository($pdo);
        $this->workflow = new CaseWorkflowService($pdo);
    }

    public function index(Request $req): void
    {
        if ($this->rejectBadQuery($req, [
            'status' => ['open', 'assigned', 'in_progress', 'resolved'],
        ])) {
            return;
        }
        if (!empty($req->query['assigned_rescuer_id'])) {
            $idV = new \App\Validation\Validator($req->query);
            $idV->optional('assigned_rescuer_id')->uuid('assigned_rescuer_id');
            if (!$idV->passes()) {
                Response::error('VALIDATION_ERROR', $idV->firstError(), 400);
                return;
            }
        }
        $where = [];
        $params = [];
        if (!in_array('cases.assign', $req->permissions, true)) {
            $where[] = 'c.assigned_rescuer_id = ?';
            $params[] = $req->user['id'];
        }
        if (!empty($req->query['status'])) {
            $where[] = 'c.status = ?';
            $params[] = $req->query['status'];
        }
        if (!empty($req->query['assigned_rescuer_id'])) {
            $where[] = 'c.assigned_rescuer_id = ?';
            $params[] = $req->query['assigned_rescuer_id'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $page = $this->page($req);
        $perPage = $this->perPage($req);

        $totalStmt = $this->pdo->prepare("SELECT COUNT(*) FROM cases c {$whereSql}");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT c.*, r.animal_description, r.address_text, u.full_name AS assigned_rescuer_name
             FROM cases c
             LEFT JOIN reports r ON r.id = c.report_id
             LEFT JOIN users u ON u.id = c.assigned_rescuer_id
             {$whereSql}
             ORDER BY c.updated_at DESC
             LIMIT " . (int) $perPage . " OFFSET " . (int) (($page - 1) * $perPage)
        );
        $stmt->execute($params);
        Response::paginated($stmt->fetchAll(\PDO::FETCH_ASSOC), $this->meta($page, $perPage, $total));
    }

    public function show(Request $req): void
    {
        $case = $this->cases->find($req->params['id']);
        if (!$case) {
            Response::error('NOT_FOUND', 'Case not found', 404);
            return;
        }
        if (!$this->canAccessCase($req, $case)) {
            Response::error('FORBIDDEN', 'Not your case', 403);
            return;
        }
        $payload = $case->toArray();
        $payload['animal_id'] = $this->lookupAnimalId($case->id());
        Response::success(['case' => $payload]);
    }

    public function assign(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('rescuer_id')->uuid('rescuer_id');
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $case = $this->cases->find($req->params['id']);
        if (!$case) {
            Response::error('NOT_FOUND', 'Case not found', 404);
            return;
        }
        if (!in_array($case->status(), ['open', 'assigned', 'in_progress'], true)) {
            Response::error('INVALID_STATUS', 'Case can only be assigned when open, assigned, or in progress', 422);
            return;
        }

        $rescuer = $this->users->find($req->body['rescuer_id']);
        if (!$rescuer || $rescuer->role() !== 'rescuer' || $rescuer->accountStatus() !== 'active') {
            Response::error('INVALID_RESCUER', 'Rescuer is not available', 422);
            return;
        }
        $duty = $this->repo('rescuer_duty_status')->findBy('user_id', $rescuer->id());
        if (!$duty || $duty['status'] !== 'on_duty') {
            Response::error('RESCUER_OFF_DUTY', 'Rescuer is not on duty', 422);
            return;
        }

        $this->cases->update($case->id(), [
            'assigned_rescuer_id' => $rescuer->id(),
            'assigned_by' => $req->user['id'],
            'status' => 'assigned',
        ]);
        $this->logActivity($case->id(), 'assigned', 'Assigned to rescuer ' . $rescuer->id(), $req->user['id'], $req->user['role']);

        $notif = new NotificationService($this->pdo);
        $notif->notify($rescuer->id(), 'case_assigned', 'A new case was assigned to you.', 'case', $case->id());
        Response::success(['case' => $this->cases->find($case->id())->toArray()]);
    }

    public function accept(Request $req): void
    {
        $this->respondWorkflow(fn () => $this->workflow->accept($req->params['id'], $req->user));
    }

    public function decline(Request $req): void
    {
        $this->respondWorkflow(fn () => $this->workflow->decline($req->params['id'], $req->user));
    }

    public function proof(Request $req): void
    {
        $files = CaseProofUpload::collect($_FILES);
        if ($files === [] && !empty($req->body['url'])) {
            Response::error('VALIDATION_ERROR', 'Proof must be uploaded as multipart files, not a URL.', 400);
            return;
        }
        $this->respondWorkflow(fn () => $this->workflow->addProof($req->params['id'], $req->user, $files));
    }

    public function resolve(Request $req): void
    {
        $this->respondWorkflow(fn () => $this->workflow->resolve($req->params['id'], $req->user));
    }

    public function updateStatus(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('status')->in('status', ['assigned', 'in_progress', 'resolved']);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $case = $this->cases->find($req->params['id']);
        if (!$case) {
            Response::error('NOT_FOUND', 'Case not found', 404);
            return;
        }
        if (!$this->canAccessCase($req, $case)) {
            Response::error('FORBIDDEN', 'Not your case', 403);
            return;
        }
        $this->respondWorkflow(fn () => $this->workflow->rejectStatusPatch($req->body['status']));
    }

    public function activity(Request $req): void
    {
        $case = $this->cases->find($req->params['id']);
        if (!$case) {
            Response::error('NOT_FOUND', 'Case not found', 404);
            return;
        }
        if (!$this->canAccessCase($req, $case)) {
            Response::error('FORBIDDEN', 'Not your case', 403);
            return;
        }
        $logRepo = $this->repo('case_activity_log', ['id','case_id','actor_id','actor_role','action','notes','created_at']);
        $rows = $logRepo->all(['case_id' => $req->params['id']], 'created_at', 'ASC');
        Response::success(['activity' => $rows]);
    }

    private function canAccessCase(Request $req, RescueCase $case): bool
    {
        if (($req->user['role'] ?? null) === 'admin' || in_array('cases.assign', $req->permissions, true)) {
            return true;
        }
        return $case->assignedRescuerId() !== null && $case->assignedRescuerId() === ($req->user['id'] ?? null);
    }

    private function lookupAnimalId(string $caseId): ?string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM animals WHERE case_id = ? LIMIT 1');
            $stmt->execute([$caseId]);
            $id = $stmt->fetchColumn();
            return $id !== false && $id !== null ? (string) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function respondWorkflow(callable $fn): void
    {
        try {
            Response::success($fn());
        } catch (\App\Services\CaseWorkflowException $e) {
            Response::error($e->errorCode(), $e->publicMessage(), $e->httpStatus());
        }
    }

    private function logActivity(string $caseId, string $action, ?string $notes, string $actorId, string $actorRole): void
    {
        $this->repo('case_activity_log')->create([
            'id' => Database::uuidV4(),
            'case_id' => $caseId,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'notes' => $notes,
        ]);
    }
}
