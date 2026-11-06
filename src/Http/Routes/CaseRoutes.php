<?php

namespace App\Http\Routes;

use App\Controllers\CaseController;
use App\Http\Request;
use App\Http\Router;
use App\Middleware\PermissionMiddleware;

class CaseRoutes
{
    public static function register(Router $router, array $d): void
    {
        $pdo = $d['pdo'];
        $authMw = $d['authMw'];

        $router->add('GET', '/api/v1/cases', fn(Request $r) => (new CaseController($pdo))->index($r), [$authMw]);
        $router->add('GET', '/api/v1/cases/{id}', fn(Request $r) => (new CaseController($pdo))->show($r), [$authMw]);
        $router->add('GET', '/api/v1/cases/{id}/activity', fn(Request $r) => (new CaseController($pdo))->activity($r), [$authMw]);
        $router->add('POST', '/api/v1/cases/{id}/assign', fn(Request $r) => (new CaseController($pdo))->assign($r), [$authMw, new PermissionMiddleware('cases.assign')]);
        $router->add('POST', '/api/v1/cases/{id}/accept', fn(Request $r) => (new CaseController($pdo))->accept($r), [$authMw, new PermissionMiddleware('cases.respond')]);
        $router->add('POST', '/api/v1/cases/{id}/decline', fn(Request $r) => (new CaseController($pdo))->decline($r), [$authMw, new PermissionMiddleware('cases.respond')]);
        $router->add('POST', '/api/v1/cases/{id}/proof', fn(Request $r) => (new CaseController($pdo))->proof($r), [$authMw, new PermissionMiddleware('cases.proof')]);
        $router->add('POST', '/api/v1/cases/{id}/vaccinations', fn(Request $r) => (new CaseController($pdo))->addVaccination($r), [$authMw, new PermissionMiddleware('cases.respond')]);
        $router->add('POST', '/api/v1/cases/{id}/heart-rate', fn(Request $r) => (new CaseController($pdo))->addHeartRate($r), [$authMw, new PermissionMiddleware('cases.respond')]);
        $router->add('POST', '/api/v1/cases/{id}/resolve', fn(Request $r) => (new CaseController($pdo))->resolve($r), [$authMw, new PermissionMiddleware('cases.status_change')]);
        $router->add('PATCH', '/api/v1/cases/{id}/status', fn(Request $r) => (new CaseController($pdo))->updateStatus($r), [$authMw, new PermissionMiddleware('cases.status_change')]);
    }
}
