<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\MessageAudience;
use App\Services\NotificationService;

class MessageController extends AbstractController
{
    public function composeTargets(Request $req): void
    {
        $pack = (new MessageAudience($this->pdo))->compose($req->user);
        if ($pack['receiver'] === null) {
            Response::error('NO_STAFF', 'No City Veterinary Office account is available to message yet.', 422);
            return;
        }
        Response::success($pack);
    }

    public function threads(Request $req): void
    {
        $me = $req->user['id'];
        $sql = "SELECT m.related_type, m.related_id,
                       (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) AS other_user_id,
                       u.full_name AS other_user_name,
                       m.message_text AS last_message,
                       m.sent_at AS last_sent_at
                FROM messages m
                JOIN (
                    SELECT related_type, related_id, MAX(sent_at) AS max_sent
                    FROM messages
                    WHERE sender_id = ? OR receiver_id = ?
                    GROUP BY related_type, related_id
                ) l ON l.related_type = m.related_type
                   AND l.related_id = m.related_id
                   AND m.sent_at = l.max_sent
                JOIN users u ON u.id = (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
                WHERE m.sender_id = ? OR m.receiver_id = ?
                ORDER BY m.sent_at DESC
                LIMIT 100";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$me, $me, $me, $me, $me, $me]);
        $threads = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $key = $row['related_type'] . '|' . $row['related_id'];
            if (isset($threads[$key])) {
                continue;
            }
            $threads[$key] = $row;
        }
        $threads = array_values($threads);

        $unreadStmt = $this->pdo->prepare(
            "SELECT related_type, related_id, COUNT(*) AS unread_count
             FROM messages
             WHERE receiver_id = ? AND read_at IS NULL
             GROUP BY related_type, related_id"
        );
        $unreadStmt->execute([$me]);
        $unread = [];
        foreach ($unreadStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $unread[$row['related_type'] . '|' . $row['related_id']] = (int) $row['unread_count'];
        }

        foreach ($threads as &$thread) {
            $key = $thread['related_type'] . '|' . $thread['related_id'];
            $thread['unread_count'] = $unread[$key] ?? 0;
        }
        unset($thread);
        Response::success(['threads' => array_values($threads)]);
    }

    public function send(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('receiver_id')->uuid('receiver_id')
            ->required('related_type')->in('related_type', ['report','case','adoption'])
            ->required('related_id')->uuid('related_id')
            ->required('message_text')->string('message_text', 4000);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        if ($req->body['receiver_id'] === $req->user['id']) {
            Response::error('INVALID_RECEIVER', 'Cannot message yourself', 422);
            return;
        }
        $receiver = $this->repo('users')->find($req->body['receiver_id']);
        if (!$receiver) {
            Response::error('NOT_FOUND', 'Receiver not found', 404);
            return;
        }
        if (!$this->assertRelatedAccess($req, $req->body['related_type'], $req->body['related_id'])) {
            return;
        }
        if (!(new MessageAudience($this->pdo))->allows(
            $req->user,
            (string) $req->body['related_type'],
            (string) $req->body['related_id'],
            (string) $req->body['receiver_id']
        )) {
            Response::error('INVALID_RECEIVER', 'You can only message the people tied to this record.', 422);
            return;
        }
        $id = $this->repo('messages')->create([
            'id' => Database::uuidV4(),
            'sender_id' => $req->user['id'],
            'receiver_id' => $req->body['receiver_id'],
            'related_type' => $req->body['related_type'],
            'related_id' => $req->body['related_id'],
            'message_text' => $req->body['message_text'],
        ]);

        $notif = new NotificationService($this->pdo);
        $notif->notify($req->body['receiver_id'], 'new_message', 'You have a new message.', $req->body['related_type'], $req->body['related_id']);
        Response::success(['message' => $this->repo('messages')->find($id)], 201);
    }

    public function thread(Request $req): void
    {
        $v = new \App\Validation\Validator($req->query);
        $v->required('related_type')->in('related_type', ['report','case','adoption'])
            ->required('related_id')->uuid('related_id');
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        if (!$this->assertRelatedAccess($req, $req->query['related_type'], $req->query['related_id'])) {
            return;
        }
        $rows = $this->repo('messages')->all(
            ['related_type' => $req->query['related_type'], 'related_id' => $req->query['related_id']],
            'sent_at', 'ASC'
        );
        Response::success(['messages' => $rows]);
    }

    public function markRead(Request $req): void
    {
        $repo = $this->repo('messages');
        $msg = $repo->find($req->params['id']);
        if (!$msg) {
            Response::error('NOT_FOUND', 'Message not found', 404);
            return;
        }
        if ($msg['receiver_id'] !== $req->user['id']) {
            Response::error('FORBIDDEN', 'Not your message', 403);
            return;
        }
        $repo->update($msg['id'], ['read_at' => date('Y-m-d H:i:s')]);
        Response::success(['message' => $repo->find($msg['id'])]);
    }

    private function assertRelatedAccess(Request $req, string $type, string $id): bool
    {
        if ($type === 'report') {
            $report = $this->repo('reports')->find($id);
            if (!$report) {
                Response::error('NOT_FOUND', 'Related report not found', 404);
                return false;
            }
            $isOwner = ($report['resident_id'] ?? null) === ($req->user['id'] ?? null);
            if (!$isOwner && !in_array('reports.read', $req->permissions, true)) {
                Response::error('FORBIDDEN', 'Not allowed to message about this report', 403);
                return false;
            }
            return true;
        }
        if ($type === 'case') {
            $case = $this->repo('cases')->find($id);
            if (!$case) {
                Response::error('NOT_FOUND', 'Related case not found', 404);
                return false;
            }
            $isAssignee = ($case['assigned_rescuer_id'] ?? null) === ($req->user['id'] ?? null);
            $canRead = in_array('cases.read', $req->permissions, true)
                || in_array('cases.assign', $req->permissions, true);
            $report = $this->repo('reports')->find((string) ($case['report_id'] ?? ''));
            $isResident = ($report['resident_id'] ?? null) === ($req->user['id'] ?? null);
            if (!$isAssignee && !$canRead && !$isResident) {
                Response::error('FORBIDDEN', 'Not allowed to message about this case', 403);
                return false;
            }
            return true;
        }
        $adoption = $this->repo('adoptions')->find($id);
        if (!$adoption) {
            Response::error('NOT_FOUND', 'Related adoption not found', 404);
            return false;
        }
        $isApplicant = ($adoption['applicant_id'] ?? null) === ($req->user['id'] ?? null);
        if (!$isApplicant && !in_array('adoptions.read', $req->permissions, true)) {
            Response::error('FORBIDDEN', 'Not allowed to message about this adoption', 403);
            return false;
        }
        return true;
    }
}
