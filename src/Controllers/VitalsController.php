<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\AnimalAccess;

class VitalsController extends AbstractController
{
    public function ingest(Request $req): void
    {
        $key = (string) Database::env('DEVICE_API_KEY', '');
        if ($key === '' || $req->header('x-device-key') !== $key) {
            Response::error('UNAUTHENTICATED', 'Invalid device key', 401);
            return;
        }
        $v = new \App\Validation\Validator($req->body);
        $v->required('animal_id')->uuid('animal_id')
            ->required('heart_rate_bpm')->numeric('heart_rate_bpm')->min('heart_rate_bpm', 1);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $animal = $this->repo('animals')->find($req->body['animal_id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        $id = $this->repo('vitals_log')->create([
            'id' => Database::uuidV4(),
            'animal_id' => $req->body['animal_id'],
            'heart_rate_bpm' => (int) $req->body['heart_rate_bpm'],
            'source' => $req->body['source'] ?? 'iot_sensor',
        ]);
        Response::success(['vital' => $this->repo('vitals_log')->find($id)], 201);
    }

    public function create(Request $req): void
    {
        $animal = $this->repo('animals')->find($req->params['id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        if (!(new AnimalAccess($this->pdo))->canWriteVitals(
            $req->user,
            $req->permissions,
            (string) $req->params['id'],
            $animal['case_id'] ?? null
        )) {
            Response::error('FORBIDDEN', 'Not allowed to record vitals for this animal', 403);
            return;
        }
        $v = new \App\Validation\Validator($req->body);
        $v->required('heart_rate_bpm')->numeric('heart_rate_bpm')->min('heart_rate_bpm', 1)
            ->optional('respiratory_rate_bpm')->numeric('respiratory_rate_bpm');
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $data = [
            'id' => Database::uuidV4(),
            'animal_id' => $req->params['id'],
            'heart_rate_bpm' => (int) $req->body['heart_rate_bpm'],
            'source' => 'manual',
        ];
        if (array_key_exists('respiratory_rate_bpm', $req->body) && $req->body['respiratory_rate_bpm'] !== null && $req->body['respiratory_rate_bpm'] !== '') {
            $data['respiratory_rate_bpm'] = (int) $req->body['respiratory_rate_bpm'];
        }
        $id = $this->repo('vitals_log')->create($data);
        Response::success(['vital' => $this->repo('vitals_log')->find($id)], 201);
    }

    public function list(Request $req): void
    {
        if ($this->rejectBadQuery($req)) {
            return;
        }
        $animal = $this->repo('animals')->find($req->params['id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        $access = new AnimalAccess($this->pdo);
        if (!$access->canReadMedical($req->user, $req->permissions, (string) $req->params['id'], $animal['case_id'] ?? null)
            && !$access->canWriteVitals($req->user, $req->permissions, (string) $req->params['id'], $animal['case_id'] ?? null)) {
            Response::error('FORBIDDEN', 'Not allowed to read vitals for this animal', 403);
            return;
        }
        $result = $this->repo('vitals_log')->paginate(
            $this->page($req), $this->perPage($req), ['animal_id' => $req->params['id']]
        );
        Response::paginated($result['items'], $this->meta($result['page'], $result['per_page'], $result['total']));
    }
}
