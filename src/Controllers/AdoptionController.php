<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\NotificationService;

class AdoptionController extends AbstractController
{
    public function apply(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('animal_id')->uuid('animal_id')
            ->optional('message')->string('message', 1000);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $animal = $this->repo('animals')->find($req->body['animal_id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        if ($animal['adoption_status'] !== 'available') {
            Response::error('NOT_ADOPTABLE', 'Animal is not available for adoption', 409);
            return;
        }
        $existing = $this->repo('adoptions')->findByComposite(
            ['animal_id', 'applicant_id', 'status'],
            [$req->body['animal_id'], $req->user['id'], 'pending']
        );
        if ($existing) {
            Response::error('APPLICATION_EXISTS', 'A pending application already exists for this animal', 409);
            return;
        }
        $id = $this->repo('adoptions')->create([
            'id' => Database::uuidV4(),
            'animal_id' => $req->body['animal_id'],
            'applicant_id' => $req->user['id'],
            'message' => isset($req->body['message']) ? trim((string) $req->body['message']) : null,
            'status' => 'pending',
        ]);
        $this->notifyRole('admin', 'adoption_applied', 'A new adoption application was submitted.', 'adoption', $id);
        Response::success(['adoption' => $this->repo('adoptions')->find($id)], 201);
    }

    public function cancel(Request $req): void
    {
        $repo = $this->repo('adoptions');
        $adoption = $repo->find($req->params['id']);
        if (!$adoption) {
            Response::error('NOT_FOUND', 'Adoption not found', 404);
            return;
        }
        if ($adoption['applicant_id'] !== $req->user['id'] && !in_array('adoptions.read', $req->permissions, true)) {
            Response::error('FORBIDDEN', 'Not your application', 403);
            return;
        }
        if ($adoption['status'] !== 'pending') {
            Response::error('INVALID_STATE', 'Only pending applications can be cancelled', 409);
            return;
        }
        $repo->update($adoption['id'], ['status' => 'cancelled']);
        Response::success(['adoption' => $repo->find($adoption['id'])]);
    }

    public function index(Request $req): void
    {
        if ($this->rejectBadQuery($req, [
            'status' => ['pending', 'approved', 'rejected', 'completed', 'cancelled'],
        ])) {
            return;
        }
        $where = [];
        $params = [];
        if (!in_array('adoptions.read', $req->permissions, true)) {
            $where[] = 'a.applicant_id = ?';
            $params[] = $req->user['id'];
        }
        if (!empty($req->query['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $req->query['status'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $page = $this->page($req);
        $perPage = $this->perPage($req);

        $totalStmt = $this->pdo->prepare("SELECT COUNT(*) FROM adoptions a {$whereSql}");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.full_name AS applicant_name, an.name AS animal_name,
                    an.species, an.breed_type, an.photo_urls
             FROM adoptions a
             LEFT JOIN users u ON u.id = a.applicant_id
             LEFT JOIN animals an ON an.id = a.animal_id
             {$whereSql}
             ORDER BY a.created_at DESC
             LIMIT " . (int) $perPage . " OFFSET " . (int) (($page - 1) * $perPage)
        );
        $stmt->execute($params);
        Response::paginated($stmt->fetchAll(\PDO::FETCH_ASSOC), $this->meta($page, $perPage, $total));
    }

    public function show(Request $req): void
    {
        $adoption = $this->repo('adoptions')->find($req->params['id']);
        if (!$adoption) {
            Response::error('NOT_FOUND', 'Adoption not found', 404);
            return;
        }
        if ($adoption['applicant_id'] !== $req->user['id'] && !in_array('adoptions.read', $req->permissions, true)) {
            Response::error('FORBIDDEN', 'Not your application', 403);
            return;
        }
        Response::success(['adoption' => $adoption]);
    }

    public function review(Request $req, string $decision): void
    {
        $v = new \App\Validation\Validator($req->body);
        if ($decision === 'rejected') {
            $v->required('rejection_reason')->string('rejection_reason', 500);
        }
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $repo = $this->repo('adoptions');
        $adoption = $repo->find($req->params['id']);
        if (!$adoption) {
            Response::error('NOT_FOUND', 'Adoption not found', 404);
            return;
        }
        if ($adoption['status'] !== 'pending') {
            Response::error('ALREADY_REVIEWED', 'Application already reviewed', 409);
            return;
        }

        $data = [
            'status' => $decision === 'approved' ? 'approved' : 'rejected',
            'reviewed_by' => $req->user['id'],
            'reviewed_at' => date('Y-m-d H:i:s'),
        ];
        if ($decision === 'rejected') {
            $data['rejection_reason'] = $req->body['rejection_reason'];
        }
        $repo->update($adoption['id'], $data);

        if ($decision === 'approved') {
            $this->repo('animals')->update($adoption['animal_id'], ['adoption_status' => 'adopted']);
        }

        $notif = new NotificationService($this->pdo);
        $notif->notify($adoption['applicant_id'], 'adoption_' . $decision, 'Your adoption application was ' . $decision . '.', 'adoption', $adoption['id']);
        Response::success(['adoption' => $repo->find($adoption['id'])]);
    }

    public function complete(Request $req): void
    {
        $repo = $this->repo('adoptions');
        $adoption = $repo->find($req->params['id']);
        if (!$adoption) {
            Response::error('NOT_FOUND', 'Adoption not found', 404);
            return;
        }
        if ($adoption['status'] !== 'approved') {
            Response::error('INVALID_STATE', 'Adoption must be approved first', 409);
            return;
        }
        $repo->update($adoption['id'], ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]);
        $notif = new NotificationService($this->pdo);
        $notif->notify($adoption['applicant_id'], 'adoption_completed', 'Your adoption is complete. Thank you!', 'adoption', $adoption['id']);
        Response::success(['adoption' => $repo->find($adoption['id'])]);
    }
}
