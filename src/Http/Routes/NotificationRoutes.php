<?php

namespace App\Http\Routes;

use App\Controllers\FeedbackController;
use App\Controllers\NotificationController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Middleware\PermissionMiddleware;

class NotificationRoutes
{
    public static function register(Router $router, array $d): void
    {
        $pdo = $d['pdo'];
        $authMw = $d['authMw'];

        $eventSourceAuth = static function (Request $r) use ($authMw): ?Response {
            if ($r->header('authorization') === null && isset($r->query['access_token'])) {
                $token = (string) $r->query['access_token'];
                if (preg_match('/^[A-Za-z0-9\-_.]+$/', $token)) {
                    $r->headers['authorization'] = 'Bearer ' . $token;
                }
            }
            return $authMw($r);
        };

        $router->add('POST', '/api/v1/feedback', fn(Request $r) => (new FeedbackController($pdo))->create($r), [$authMw]);
        $router->add('GET', '/api/v1/notifications', fn(Request $r) => (new NotificationController($pdo))->index($r), [$authMw]);
        $router->add('GET', '/api/v1/notifications/stream', fn(Request $r) => (new NotificationController($pdo))->stream($r), [$eventSourceAuth]);
        $router->add('PATCH', '/api/v1/notifications/{id}/read', fn(Request $r) => (new NotificationController($pdo))->markRead($r), [$authMw]);
        $router->add('POST', '/api/v1/notifications/read-all', fn(Request $r) => (new NotificationController($pdo))->markAllRead($r), [$authMw]);
        $router->add('GET', '/api/v1/notifications/unread-count', fn(Request $r) => (new NotificationController($pdo))->unreadCount($r), [$authMw]);
        $router->add('POST', '/api/v1/admin/notifications', fn(Request $r) => (new NotificationController($pdo))->broadcast($r), [$authMw, new PermissionMiddleware('notifications.broadcast')]);
        $router->add('POST', '/api/v1/admin/notifications/broadcast', fn(Request $r) => (new NotificationController($pdo))->broadcast($r), [$authMw, new PermissionMiddleware('notifications.broadcast')]);
        $router->add('GET', '/api/v1/admin/notifications/recent', fn(Request $r) => (new NotificationController($pdo))->recent($r), [$authMw, new PermissionMiddleware('notifications.broadcast')]);
        $router->add('DELETE', '/api/v1/admin/notifications/{id}', fn(Request $r) => (new NotificationController($pdo))->delete($r), [$authMw, new PermissionMiddleware('notifications.delete')]);
    }
}
