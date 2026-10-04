<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class UserProfileApiTest extends ApiTestCase
{
    private string $uploadRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'furescue-api-profile-' . bin2hex(random_bytes(4));
        mkdir($this->uploadRoot, 0755, true);
        $_ENV['UPLOAD_PUBLIC_ROOT'] = $this->uploadRoot;
        $_ENV['APP_TESTING'] = '1';
    }

    protected function tearDown(): void
    {
        $_FILES = [];
        unset($_SERVER['CONTENT_LENGTH']);
        $this->removeDir($this->uploadRoot);
        unset($_ENV['UPLOAD_PUBLIC_ROOT']);
        parent::tearDown();
    }

    public function testProfilePhotoRequiresAuth(): void
    {
        $this->assertError($this->post('/api/v1/users/' . $this->uuid() . '/profile-photo'), 'UNAUTHENTICATED', 401);
    }

    public function testProfilePhotoRequiresAFile(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->post('/api/v1/users/' . $resident['id'] . '/profile-photo', [], $this->actingAs($resident)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testProfilePhotoRejectsOversizedEmptyBody(): void
    {
        $resident = $this->seedResident();
        $_SERVER['CONTENT_LENGTH'] = (string) (6 * 1024 * 1024);
        $this->assertError(
            $this->post('/api/v1/users/' . $resident['id'] . '/profile-photo', [], $this->actingAs($resident)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testResidentCannotUploadForSomeoneElse(): void
    {
        $resident = $this->seedResident();
        $other = $this->seedResident();
        $this->assertError(
            $this->postFile('/api/v1/users/' . $other['id'] . '/profile-photo', $this->pngFile(), $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testUnknownUserPhotoIsNotFound(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->postFile('/api/v1/users/' . $this->uuid() . '/profile-photo', $this->pngFile(), $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testProfilePhotoStoreFailureStaysGeneric(): void
    {
        $resident = $this->seedResident();
        $file = $this->pngFile();
        unlink($file['tmp_name']);
        $response = $this->postFile(
            '/api/v1/users/' . $resident['id'] . '/profile-photo',
            $file,
            $this->actingAs($resident)
        );
        $this->assertError($response, 'SERVER_ERROR', 500);
        $message = (string) ($response['body']['error']['message'] ?? '');
        $this->assertSame('Something went wrong. Try again.', $message);
        $this->assertStringNotContainsString('D:\\', $message);
        $this->assertStringNotContainsString('.php:', $message);
    }

    public function testResidentCanUploadOwnProfilePhoto(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk(
            $this->postFile('/api/v1/users/' . $resident['id'] . '/profile-photo', $this->pngFile(), $this->actingAs($resident)),
            201
        );
        $url = $data['user']['profile_photo_url'] ?? '';
        $this->assertStringStartsWith('/uploads/users/', $url);
        $this->assertFileExists($this->uploadRoot . $url);

        $me = $this->assertOk($this->get('/api/v1/users/me', [], $this->actingAs($resident)));
        $this->assertSame($url, $me['user']['profile_photo_url']);
    }

    public function testAdminCanUploadPhotoForAnotherUser(): void
    {
        $admin = $this->seedAdmin();
        $resident = $this->seedResident();
        $data = $this->assertOk(
            $this->postFile('/api/v1/users/' . $resident['id'] . '/profile-photo', $this->pngFile(), $this->actingAs($admin)),
            201
        );
        $this->assertStringStartsWith('/uploads/users/', $data['user']['profile_photo_url']);
    }

    public function testUploadRejectsFakeJpegExe(): void
    {
        $resident = $this->seedResident();
        $tmp = $this->uploadRoot . DIRECTORY_SEPARATOR . 'sneaky.jpg';
        file_put_contents($tmp, "MZ\x90\x00fake-exe");
        $this->assertError($this->postFile('/api/v1/users/' . $resident['id'] . '/profile-photo', [
            'name' => 'face.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testUploadReplacesPreviousOwnedPhoto(): void
    {
        $resident = $this->seedResident();
        $token = $this->actingAs($resident);
        $first = $this->assertOk(
            $this->postFile('/api/v1/users/' . $resident['id'] . '/profile-photo', $this->pngFile('one.png'), $token),
            201
        );
        $firstUrl = $first['user']['profile_photo_url'];
        $this->assertFileExists($this->uploadRoot . $firstUrl);

        $second = $this->assertOk(
            $this->postFile('/api/v1/users/' . $resident['id'] . '/profile-photo', $this->pngFile('two.png'), $token),
            201
        );
        $secondUrl = $second['user']['profile_photo_url'];
        $this->assertNotSame($firstUrl, $secondUrl);
        $this->assertFileExists($this->uploadRoot . $secondUrl);
        $this->assertFileDoesNotExist($this->uploadRoot . $firstUrl);
    }

    public function testResidentCanRemoveOwnProfilePhoto(): void
    {
        $resident = $this->seedResident();
        $token = $this->actingAs($resident);
        $uploaded = $this->assertOk(
            $this->postFile('/api/v1/users/' . $resident['id'] . '/profile-photo', $this->pngFile(), $token),
            201
        );
        $url = $uploaded['user']['profile_photo_url'];
        $data = $this->assertOk($this->delete('/api/v1/users/' . $resident['id'] . '/profile-photo', $token));
        $this->assertNull($data['user']['profile_photo_url']);
        $this->assertFileDoesNotExist($this->uploadRoot . $url);
    }

    public function testRemovePhotoWhenNoneIsOk(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->delete(
            '/api/v1/users/' . $resident['id'] . '/profile-photo',
            $this->actingAs($resident)
        ));
        $this->assertNull($data['user']['profile_photo_url']);
    }

    public function testResidentCannotRemoveSomeoneElsesPhoto(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $this->assertError(
            $this->delete('/api/v1/users/' . $owner['id'] . '/profile-photo', $this->actingAs($other)),
            'FORBIDDEN',
            403
        );
    }

    private function postFile(string $path, array $file, string $token): array
    {
        $_FILES = ['file' => $file];
        try {
            return $this->post($path, [], $token);
        } finally {
            $_FILES = [];
        }
    }

    private function pngFile(string $name = 'avatar.png'): array
    {
        $tmp = $this->uploadRoot . DIRECTORY_SEPARATOR . $name . '-' . bin2hex(random_bytes(3));
        file_put_contents($tmp, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true));
        return [
            'name' => $name,
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
