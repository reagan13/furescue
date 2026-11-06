<?php

namespace App\Http\Routes;

use App\Controllers\ReportController;
use App\Http\Request;
use App\Http\Router;
use App\Middleware\PermissionMiddleware;

class ReportRoutes
{
    public static function register(Router $router, array $d): void
    {
        $pdo = $d['pdo'];
        $dedup = $d['dedup'];
        $geo = $d['geo'];
        $authMw = $d['authMw'];

        $router->add('POST', '/api/v1/reports', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->create($r), [$authMw, new PermissionMiddleware('reports.create')]);
        $router->add('GET', '/api/v1/reports/me', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->mine($r), [$authMw]);
        $router->add('GET', '/api/v1/reports/map/heatmap', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->heatmap($r), [$authMw, new PermissionMiddleware('analytics.read')]);
        $router->add('POST', '/api/v1/reports/{id}/withdraw', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->withdraw($r), [$authMw]);
        $router->add('GET', '/api/v1/reports', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->index($r), [$authMw]);
        $router->add('GET', '/api/v1/reports/{id}', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->show($r), [$authMw]);
        $router->add('POST', '/api/v1/reports/{id}/media', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->uploadMedia($r), [$authMw]);
        $router->add('POST', '/api/v1/reports/{id}/verify', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->verify($r), [$authMw, new PermissionMiddleware('reports.verify')]);
        $router->add('POST', '/api/v1/reports/{id}/dismiss', fn(Request $r) => (new ReportController($pdo, $dedup, $geo))->dismiss($r), [$authMw, new PermissionMiddleware('reports.dismiss')]);
    }
}
