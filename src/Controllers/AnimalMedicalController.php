<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\AnimalAccess;

class AnimalMedicalController extends AbstractController
{
    public function show(Request $req): void
    {
        $animal = $this->repo('animals')->find($req->params['id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        if (!(new AnimalAccess($this->pdo))->canReadMedical(
            $req->user,
            $req->permissions,
            (string) $req->params['id'],
            $animal['case_id'] ?? null
        )) {
            Response::error('FORBIDDEN', 'Not allowed to read this medical record', 403);
            return;
        }
        $record = $this->repo('animal_medical_records')->findBy('animal_id', $req->params['id']);
        Response::success(['medical' => $record]);
    }

    public function upsert(Request $req): void
    {
        $animal = $this->repo('animals')->find($req->params['id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        $allowed = [
            'medical_history_notes', 'vaccination_status', 'vaccination_details',
            'vaccination_records', 'vaccine_protocols', 'last_checkup_date',
            'deworming_status', 'neutered', 'weight_kg', 'temperature_c',
        ];
        $v = new \App\Validation\Validator($req->body);
        if (array_key_exists('medical_history_notes', $req->body)) {
            $v->optional('medical_history_notes')->string('medical_history_notes', 4000);
        }
        if (array_key_exists('vaccination_status', $req->body)) {
            $v->optional('vaccination_status')->in('vaccination_status', ['none', 'partial', 'complete']);
        }
        if (array_key_exists('last_checkup_date', $req->body)) {
            $v->optional('last_checkup_date')->string('last_checkup_date', 32);
        }
        if (array_key_exists('deworming_status', $req->body)) {
            $v->optional('deworming_status')->in('deworming_status', ['unknown', 'up_to_date', 'overdue']);
        }
        if (array_key_exists('neutered', $req->body)) {
            $neutered = $req->body['neutered'];
            $neuteredOk = is_bool($neutered)
                || in_array($neutered, [0, 1, '0', '1', 'true', 'false', 'yes', 'no', 'unknown'], true);
            if (!$neuteredOk) {
                Response::error('VALIDATION_ERROR', 'neutered must be a boolean', 400);
                return;
            }
        }
        if (array_key_exists('weight_kg', $req->body)) {
            $v->optional('weight_kg')->numeric('weight_kg');
        }
        if (array_key_exists('temperature_c', $req->body)) {
            $v->optional('temperature_c')->numeric('temperature_c');
        }
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $data = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $req->body)) {
                $val = $req->body[$f];
                if (in_array($f, ['vaccination_details', 'vaccination_records', 'vaccine_protocols'], true) && is_array($val)) {
                    $val = json_encode($val);
                }
                $data[$f] = $val;
            }
        }

        // Vaccination records are stored exactly as the admin entered them
        // (type, date given, next schedule, status) — no engine recomputation.

        if (empty($data)) {
            Response::error('VALIDATION_ERROR', 'No fields provided', 400);
            return;
        }

        $repo = $this->repo('animal_medical_records');
        $existing = $repo->findBy('animal_id', $req->params['id']);
        if ($existing) {
            $repo->update($existing['id'], array_merge($data, ['updated_by' => $req->user['id']]));
            $record = $repo->find($existing['id']);
        } else {
            $id = $repo->create(array_merge($data, [
                'id' => Database::uuidV4(),
                'animal_id' => $req->params['id'],
                'updated_by' => $req->user['id'],
            ]));
            $record = $repo->find($id);
        }
        Response::success(['medical' => $record]);
    }
}
