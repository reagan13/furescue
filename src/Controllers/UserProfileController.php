<?php

namespace App\Controllers;

use App\Entity\User;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Services\UserProfileUpload;
use PDO;

class UserProfileController extends AbstractController
{
    private UserRepository $users;
    private UserProfileUpload $photos;

    public function __construct(PDO $pdo, ?UserProfileUpload $photos = null)
    {
        parent::__construct($pdo);
        $this->users = new UserRepository($pdo);
        $this->photos = $photos ?? new UserProfileUpload();
    }

    public function upload(Request $req): void
    {
        $user = $this->requireEditableUser($req);
        if (!$user) {
            return;
        }
        if ($this->rejectedOversizedPost()) {
            return;
        }
        $file = $_FILES['file'] ?? null;
        if (!$file) {
            Response::error('VALIDATION_ERROR', 'A profile photo is required.', 400);
            return;
        }
        $err = UserProfileUpload::validate($file, $this->photos->requiresUploadedFile());
        if ($err !== null) {
            Response::error('VALIDATION_ERROR', $err, 400);
            return;
        }
        try {
            $url = $this->photos->store($file);
        } catch (\RuntimeException $e) {
            Response::serverError($e);
            return;
        }
        $previous = (string) ($user->profilePhotoUrl() ?? '');
        $this->users->update($user->id(), ['profile_photo_url' => $url]);
        if ($previous !== $url) {
            $this->photos->deleteOwned($previous);
        }
        $updated = $this->users->find($user->id());
        Response::success(['user' => $updated ? $updated->toArray() : null], 201);
    }

    public function delete(Request $req): void
    {
        $user = $this->requireEditableUser($req);
        if (!$user) {
            return;
        }
        $previous = (string) ($user->profilePhotoUrl() ?? '');
        $this->users->update($user->id(), ['profile_photo_url' => null]);
        $this->photos->deleteOwned($previous);
        $updated = $this->users->find($user->id());
        Response::success(['user' => $updated ? $updated->toArray() : null]);
    }

    private function requireEditableUser(Request $req): ?User
    {
        $id = (string) ($req->params['id'] ?? '');
        $user = $this->users->find($id);
        if (!$user) {
            Response::error('NOT_FOUND', 'User not found', 404);
            return null;
        }
        $isSelf = ($req->user['id'] ?? null) === $user->id();
        $isAdmin = ($req->user['role'] ?? '') === 'admin';
        if (!$isSelf && !$isAdmin) {
            Response::error('FORBIDDEN', 'Cannot update this user', 403);
            return null;
        }
        return $user;
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
