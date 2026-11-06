<?php

namespace App\Http\Routes;

use App\Controllers\AnimalController;
use App\Controllers\AnimalMedicalController;
use App\Controllers\AnimalProfileController;
use App\Controllers\DocumentsController;
use App\Controllers\HealthController;
use App\Controllers\VitalsController;
use App\Http\Request;
use App\Http\Router;
use App\Middleware\PermissionMiddleware;

class AnimalRoutes
{
    public static function register(Router $router, array $d): void
    {
        $pdo = $d['pdo'];
        $authMw = $d['authMw'];

        $router->add('GET', '/api/v1/animals', fn(Request $r) => (new AnimalController($pdo))->index($r), [$authMw]);
        $router->add('GET', '/api/v1/animals/{id}', fn(Request $r) => (new AnimalController($pdo))->show($r), [$authMw]);
        $router->add('POST', '/api/v1/animals', fn(Request $r) => (new AnimalController($pdo))->create($r), [$authMw, new PermissionMiddleware('animals.write')]);
        $router->add('PATCH', '/api/v1/animals/{id}', fn(Request $r) => (new AnimalController($pdo))->update($r), [$authMw, new PermissionMiddleware('animals.write')]);
        $router->add('DELETE', '/api/v1/animals/{id}', fn(Request $r) => (new AnimalController($pdo))->delete($r), [$authMw, new PermissionMiddleware('animals.write')]);
        $router->add('POST', '/api/v1/animals/{id}/field-status', fn(Request $r) => (new AnimalController($pdo))->logFieldStatus($r), [$authMw, new PermissionMiddleware('animals.field_status.write')]);
        $router->add('GET', '/api/v1/animals/{id}/field-status', fn(Request $r) => (new AnimalController($pdo))->fieldStatusHistory($r), [$authMw]);

        $router->add('GET', '/api/v1/animals/{id}/medical', fn(Request $r) => (new AnimalMedicalController($pdo))->show($r), [$authMw]);
        $router->add('PUT', '/api/v1/animals/{id}/medical', fn(Request $r) => (new AnimalMedicalController($pdo))->upsert($r), [$authMw, new PermissionMiddleware('animals.medical.write')]);
        $router->add('GET', '/api/v1/animals/{id}/health-record', fn(Request $r) => (new HealthController($pdo))->record($r), [$authMw, new PermissionMiddleware('animals.medical.read')]);

        $router->add('POST', '/api/v1/vitals', fn(Request $r) => (new VitalsController($pdo))->ingest($r));
        $router->add('GET', '/api/v1/animals/{id}/vitals', fn(Request $r) => (new VitalsController($pdo))->list($r), [$authMw]);
        $router->add('POST', '/api/v1/animals/{id}/vitals', fn(Request $r) => (new VitalsController($pdo))->create($r), [$authMw, new PermissionMiddleware('animals.vitals.read')]);

        $write = [$authMw, new PermissionMiddleware('animals.write')];
        $router->add('POST', '/api/v1/animals/{id}/model-3d', fn(Request $r) => (new AnimalProfileController($pdo))->uploadModel3d($r), $write);
        $router->add('DELETE', '/api/v1/animals/{id}/model-3d', fn(Request $r) => (new AnimalProfileController($pdo))->deleteModel3d($r), $write);
        $router->add('POST', '/api/v1/animals/{id}/photo-360', fn(Request $r) => (new AnimalProfileController($pdo))->uploadPhoto360($r), $write);
        $router->add('DELETE', '/api/v1/animals/{id}/photo-360', fn(Request $r) => (new AnimalProfileController($pdo))->deletePhoto360($r), $write);

        $router->add('POST', '/api/v1/animals/{id}/documents', fn(Request $r) => (new DocumentsController($pdo))->create($r), [$authMw, new PermissionMiddleware('animals.documents.upload')]);
        $router->add('PATCH', '/api/v1/documents/{id}', fn(Request $r) => (new DocumentsController($pdo))->update($r), [$authMw, new PermissionMiddleware('animals.documents.upload')]);
        $router->add('DELETE', '/api/v1/documents/{id}', fn(Request $r) => (new DocumentsController($pdo))->delete($r), [$authMw, new PermissionMiddleware('animals.documents.delete')]);
    }
}
