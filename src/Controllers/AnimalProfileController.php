<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\AnimalRepository;
use App\Services\AnimalAssetUpload;
use PDO;

class AnimalProfileController extends AbstractController
{
    private AnimalRepository $animals;
    private AnimalAssetUpload $assets;

    public function __construct(PDO $pdo, ?AnimalAssetUpload $assets = null)
    {
        parent::__construct($pdo);
        $this->animals = new AnimalRepository($pdo);
        $this->assets = $assets ?? new AnimalAssetUpload();
    }

    public function uploadModel3d(Request $req): void
    {
        $animal = $this->requireAnimal($req);
        if (!$animal) {
            return;
        }
        if ($this->rejectedOversizedPost()) {
            return;
        }
        $file = $_FILES['file'] ?? null;
        if (!$file) {
            Response::error('VALIDATION_ERROR', 'A 3D model file is required.', 400);
            return;
        }
        $err = AnimalAssetUpload::validate($file, AnimalAssetUpload::modelRules(), AnimalAssetUpload::MODEL_MAX_BYTES);
        if ($err !== null) {
            Response::error('VALIDATION_ERROR', $err, 400);
            return;
        }
        try {
            $url = $this->assets->store($file);
        } catch (\RuntimeException $e) {
            Response::serverError($e);
            return;
        }
        $previous = (string) ($animal->model3dUrl() ?? '');
        $this->animals->update($animal->id(), ['model_3d_url' => $url]);
        if ($previous !== $url) {
            $this->assets->deleteOwned($previous);
        }
        $updated = $this->animals->find($animal->id());
        Response::success(['animal' => $updated ? $updated->toArray() : null], 201);
    }

    public function deleteModel3d(Request $req): void
    {
        $animal = $this->requireAnimal($req);
        if (!$animal) {
            return;
        }
        $previous = (string) ($animal->model3dUrl() ?? '');
        $this->animals->update($animal->id(), ['model_3d_url' => null]);
        $this->assets->deleteOwned($previous);
        $updated = $this->animals->find($animal->id());
        Response::success(['animal' => $updated ? $updated->toArray() : null]);
    }

    public function uploadPhoto360(Request $req): void
    {
        $animal = $this->requireAnimal($req);
        if (!$animal) {
            return;
        }
        if ($this->rejectedOversizedPost()) {
            return;
        }
        $files = AnimalAssetUpload::normalizeFiles($_FILES['photos'] ?? null);
        $countErr = AnimalAssetUpload::validatePhotoCount(count($files));
        if ($countErr !== null) {
            Response::error('VALIDATION_ERROR', $countErr, 400);
            return;
        }
        foreach ($files as $file) {
            $err = AnimalAssetUpload::validate($file, AnimalAssetUpload::photoRules(), AnimalAssetUpload::PHOTO_MAX_BYTES);
            if ($err !== null) {
                Response::error('VALIDATION_ERROR', $err, 400);
                return;
            }
        }
        $urls = [];
        try {
            foreach ($files as $file) {
                $urls[] = $this->assets->store($file);
            }
        } catch (\RuntimeException $e) {
            $this->assets->deleteOwnedList($urls);
            Response::serverError($e);
            return;
        }
        $previous = AnimalAssetUpload::decodeUrlList($animal->photo360Set());
        $this->animals->update($animal->id(), [
            'photo_360_set' => json_encode($urls, JSON_UNESCAPED_SLASHES),
        ]);
        $this->assets->deleteOwnedList($previous);
        $updated = $this->animals->find($animal->id());
        Response::success(['animal' => $updated ? $updated->toArray() : null], 201);
    }

    public function deletePhoto360(Request $req): void
    {
        $animal = $this->requireAnimal($req);
        if (!$animal) {
            return;
        }
        $previous = AnimalAssetUpload::decodeUrlList($animal->photo360Set());
        $this->animals->update($animal->id(), ['photo_360_set' => null]);
        $this->assets->deleteOwnedList($previous);
        $updated = $this->animals->find($animal->id());
        Response::success(['animal' => $updated ? $updated->toArray() : null]);
    }

    private function requireAnimal(Request $req): ?\App\Entity\Animal
    {
        $animal = $this->animals->findActive((string) ($req->params['id'] ?? ''));
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return null;
        }
        return $animal;
    }

    private function rejectedOversizedPost(): bool
    {
        if (!empty($_FILES)) {
            return false;
        }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) <= 0) {
            return false;
        }
        Response::error('VALIDATION_ERROR', 'Upload is too large.', 400);
        return true;
    }
}
