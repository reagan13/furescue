<?php

namespace App\Http\Routes;

use App\Controllers\MessageController;
use App\Http\Request;
use App\Http\Router;
use App\Middleware\PermissionMiddleware;

class MessageRoutes
{
    public static function register(Router $router, array $d): void
    {
        $pdo = $d['pdo'];
        $authMw = $d['authMw'];

        $router->add('POST', '/api/v1/messages', fn(Request $r) => (new MessageController($pdo))->send($r), [$authMw, new PermissionMiddleware('messages.send')]);
        $router->add('GET', '/api/v1/messages/compose-targets', fn(Request $r) => (new MessageController($pdo))->composeTargets($r), [$authMw, new PermissionMiddleware('messages.send')]);
        $router->add('GET', '/api/v1/messages/threads', fn(Request $r) => (new MessageController($pdo))->threads($r), [$authMw, new PermissionMiddleware('messages.read')]);
        $router->add('GET', '/api/v1/messages', fn(Request $r) => (new MessageController($pdo))->thread($r), [$authMw, new PermissionMiddleware('messages.read')]);
        $router->add('PATCH', '/api/v1/messages/{id}/read', fn(Request $r) => (new MessageController($pdo))->markRead($r), [$authMw, new PermissionMiddleware('messages.mark_read')]);
    }
}
