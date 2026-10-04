<?php

namespace App\Http\Routes;

use App\Controllers\ElearningController;
use App\Http\Request;
use App\Http\Router;
use App\Middleware\PermissionMiddleware;

class ElearningRoutes
{
    public static function register(Router $router, array $d): void
    {
        $pdo = $d['pdo'];
        $authMw = $d['authMw'];

        $router->add('GET', '/api/v1/elearning/modules', fn(Request $r) => (new ElearningController($pdo))->modules($r), [$authMw]);
        $router->add('GET', '/api/v1/elearning/modules/{id}', fn(Request $r) => (new ElearningController($pdo))->module($r), [$authMw]);
        $router->add('POST', '/api/v1/elearning/modules', fn(Request $r) => (new ElearningController($pdo))->createModule($r), [$authMw, new PermissionMiddleware('elearning.write')]);
        $router->add('PATCH', '/api/v1/elearning/modules/{id}', fn(Request $r) => (new ElearningController($pdo))->updateModule($r), [$authMw, new PermissionMiddleware('elearning.write')]);
        $router->add('GET', '/api/v1/elearning/progress', fn(Request $r) => (new ElearningController($pdo))->progress($r), [$authMw]);
        $router->add('POST', '/api/v1/elearning/progress', fn(Request $r) => (new ElearningController($pdo))->upsertProgress($r), [$authMw]);
        $router->add('POST', '/api/v1/elearning/quiz', fn(Request $r) => (new ElearningController($pdo))->submitQuiz($r), [$authMw]);
    }
}
