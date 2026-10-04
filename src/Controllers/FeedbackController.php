<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use PDO;

class FeedbackController extends AbstractController
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    public function create(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('message')->string('message', 2000);
        $v->optional('page_url')->string('page_url', 400);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        $name = trim((string) ($req->user['full_name'] ?? $req->user['email'] ?? 'A user'));
        $page = trim((string) ($req->body['page_url'] ?? ''));
        $text = trim((string) $req->body['message']);
        $note = $name . ' reported a problem' . ($page !== '' ? ' on ' . $page : '') . ': ' . $text;
        $this->notifyRole('admin', 'user_feedback', $note, 'feedback', $req->user['id'] ?? null);

        Response::success(['ok' => true], 201);
    }
}
