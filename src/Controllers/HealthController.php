<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\HealthActivityService;
use App\Services\HealthRecordService;
use App\Services\HealthRosterService;

class HealthController extends AbstractController
{
    public function records(Request $req): void
    {
        Response::success(['records' => (new HealthRosterService($this->pdo))->list()]);
    }

    public function record(Request $req): void
    {
        $record = (new HealthRecordService($this->pdo))->find((string) ($req->params['id'] ?? ''));
        if ($record === null) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        Response::success(['record' => $record]);
    }

    public function activity(Request $req): void
    {
        Response::success(['daily' => (new HealthActivityService($this->pdo))->daily()]);
    }
}
