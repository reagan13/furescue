<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\NotificationService;

class NotificationController extends AbstractController
{
    public function index(Request $req): void
    {
        if ($this->rejectBadQuery($req)) {
            return;
        }
        $repo = $this->repo('notifications', ['id','user_id','type','message','related_type','related_id','is_read','created_at']);
        $filters = ['user_id' => $req->user['id']];
        if (isset($req->query['is_read'])) {
            $filters['is_read'] = $req->query['is_read'] === 'true' || $req->query['is_read'] === '1' ? 1 : 0;
        }
        $result = $repo->paginate($this->page($req), $this->perPage($req), $filters, 'created_at', 'DESC');
        Response::paginated($result['items'], $this->meta($result['page'], $result['per_page'], $result['total']));
    }

    public function markRead(Request $req): void
    {
        $repo = $this->repo('notifications');
        $note = $repo->find($req->params['id']);
        if (!$note) {
            Response::error('NOT_FOUND', 'Notification not found', 404);
            return;
        }
        if ($note['user_id'] !== $req->user['id']) {
            Response::error('FORBIDDEN', 'Not your notification', 403);
            return;
        }
        $repo->update($note['id'], ['is_read' => true]);
        Response::success(['notification' => $repo->find($note['id'])]);
    }

    public function markAllRead(Request $req): void
    {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$req->user['id']]);
        Response::success(['updated' => $stmt->rowCount()]);
    }

    public function broadcast(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('message')->string('message', 1000);
        $v->optional('type')->string('type', 50);
        $v->optional('targets');

        $type = $req->body['type'] ?? 'admin_announcement';
        $targets = $req->body['targets'] ?? ['role:admin'];
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        foreach ($targets as $target) {
            if (!is_string($target) || !preg_match('/^(role:(admin|rescuer|resident)|all|user:[0-9a-fA-F-]{36})$/', $target)) {
                Response::error('VALIDATION_ERROR', 'Invalid target: ' . $target, 400);
                return;
            }
        }

        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        $svc = new NotificationService($this->pdo);
        $sent = $svc->broadcast($req->body['message'], $type, $targets, $req->user['id']);
        Response::success(['sent' => $sent, 'type' => $type, 'targets' => $targets], 201);
    }

    public function unreadCount(Request $req): void
    {
        $svc = new NotificationService($this->pdo);
        Response::success(['count' => $svc->unreadCount($req->user['id'])]);
    }

    public function recent(Request $req): void
    {
        $svc = new NotificationService($this->pdo);
        Response::success(['broadcasts' => $svc->recentBroadcasts(20)]);
    }

    /**
     * php -S is single-threaded (PHP_CLI_SERVER_WORKERS is not supported on
     * Windows). A held-open SSE loop blocks every other page, API call, and
     * logout until the connection ends.
     */
    public static function holdConnectionOnSapi(string $sapi): bool
    {
        return $sapi !== 'cli-server';
    }

    public function stream(Request $req): void
    {
        if ($req->user === null) {
            Response::error('UNAUTHENTICATED', 'Missing bearer token', 401);
            return;
        }

        $userId = (string) $req->user['id'];
        $svc = new NotificationService($this->pdo);

        @set_time_limit(0);
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo "retry: 5000\n\n";
        @flush();

        try {
            $this->sseSend(['type' => 'sync', 'unread_count' => $svc->unreadCount($userId)]);
        } catch (\Throwable) {
            // First tick is best-effort so a DB hiccup cannot pin the worker.
        }

        if (!self::holdConnectionOnSapi(PHP_SAPI)) {
            echo "event: done\ndata: {}\n\n";
            @flush();
            return;
        }

        $cursor = $svc->dbNow();
        /** @var array<string, string> $seenIds notification id => created_at (same-second dedupe) */
        $seenIds = [];
        $tick = 0;
        $maxTicks = 60; // ~5 min per connection; EventSource reconnects automatically

        while (!connection_aborted() && $tick < $maxTicks) {
            try {
                $unread = $svc->unreadCount($userId);

                $fresh = $svc->latestSince($userId, $cursor);
                $maxSeenAt = $cursor;
                foreach ($fresh as $note) {
                    $stamp = (string) $note['created_at'];
                    if ($stamp > $maxSeenAt) {
                        $maxSeenAt = $stamp;
                    }
                    if (isset($seenIds[$note['id']])) {
                        continue;
                    }
                    $seenIds[$note['id']] = $stamp;
                    $this->sseSend([
                        'type' => 'notification',
                        'notification' => $note,
                        'unread_count' => $unread,
                    ]);
                }
                $cursor = $maxSeenAt;

                // Drop dedupe memory for rows that now fall behind the cursor.
                foreach ($seenIds as $id => $ts) {
                    if ($ts !== $cursor) {
                        unset($seenIds[$id]);
                    }
                }

                // Periodic badge sync + keep-alive.
                $this->sseSend(['type' => 'sync', 'unread_count' => $unread]);
            } catch (\Throwable) {
                // Never kill the stream on a transient DB hiccup; retry next tick.
            }

            echo ": keep-alive\n\n";
            @flush();
            $tick++;
            sleep(5);
        }
    }

    private function sseSend(array $payload): void
    {
        echo 'data: ' . json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n\n";
        @flush();
    }

    public function delete(Request $req): void
    {
        $repo = $this->repo('notifications');
        $note = $repo->find($req->params['id']);
        if (!$note) {
            Response::error('NOT_FOUND', 'Notification not found', 404);
            return;
        }
        if ($note['user_id'] !== $req->user['id'] && !in_array('notifications.delete', $req->permissions, true)) {
            Response::error('FORBIDDEN', 'Not allowed to delete this notification', 403);
            return;
        }
        if (($note['type'] ?? '') === 'admin_announcement' && in_array('notifications.delete', $req->permissions, true)) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM notifications WHERE type = 'admin_announcement' AND message = ?"
            );
            $stmt->execute([(string) ($note['message'] ?? '')]);
            Response::success(null, 204);
            return;
        }
        $repo->delete($note['id']);
        Response::success(null, 204);
    }
}
