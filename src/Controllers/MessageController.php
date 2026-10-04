<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\NotificationService;

class MessageController extends AbstractController
{
    public function composeTargets(Request $req): void
    {
        $me = (string) $req->user['id'];
        $receiver = $this->staffReceiver($me);
        if ($receiver === null) {
            Response::error('NO_STAFF', 'No City Veterinary Office account is available to message yet.', 422);
            return;
        }

        $topics = [];
        $reportStmt = $this->pdo->prepare(
            'SELECT id, animal_description, created_at FROM reports WHERE resident_id = ? ORDER BY created_at DESC LIMIT 50'
        );
        $reportStmt->execute([$me]);
        foreach ($reportStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $label = trim((string) ($row['animal_description'] ?? ''));
            $topics[] = [
                'related_type' => 'report',
                'related_id' => $row['id'],
                'label' => 'Report · ' . ($label !== '' ? mb_substr($label, 0, 80) : 'your report'),
            ];
        }

        $adoptStmt = $this->pdo->prepare(
            'SELECT a.id, an.name FROM adoptions a
             LEFT JOIN animals an ON an.id = a.animal_id
             WHERE a.applicant_id = ? AND a.status <> \'cancelled\'
             ORDER BY a.created_at DESC LIMIT 50'
        );
        $adoptStmt->execute([$me]);
        foreach ($adoptStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $topics[] = [
                'related_type' => 'adoption',
                'related_id' => $row['id'],
                'label' => 'Adoption · ' . ($name !== '' ? $name : 'your application'),
            ];
        }

        if (in_array('cases.read', $req->permissions, true) || in_array('cases.respond', $req->permissions, true)) {
            $caseStmt = $this->pdo->prepare(
                'SELECT id FROM cases WHERE assigned_rescuer_id = ? ORDER BY created_at DESC LIMIT 50'
            );
            $caseStmt->execute([$me]);
            foreach ($caseStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $topics[] = [
                    'related_type' => 'case',
                    'related_id' => $row['id'],
                    'label' => 'Case · assigned to you',
                ];
            }
        }

        Response::success([
            'receiver' => $receiver,
            'topics' => $topics,
        ]);
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
            if (!$isAssignee && !$canRead) {
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

    /**
     * @return array{id:string,full_name:string}|null
     */
    private function staffReceiver(string $exceptUserId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, full_name FROM users
             WHERE role = 'admin' AND account_status = 'active' AND id <> ?
             ORDER BY created_at ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$exceptUserId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'full_name' => (string) ($row['full_name'] ?? 'City Veterinary Office'),
        ];
    }
}
