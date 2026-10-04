<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PDO;

class LifecycleApiTest extends ApiTestCase
{
    public function testReportToAdoptionWalk(): void
    {
        $resident = $this->seedResident();
        $admin = $this->seedAdmin();
        $rescuer = $this->seedRescuer();
        $residentToken = $this->actingAs($resident);
        $adminToken = $this->actingAs($admin);
        $rescuerToken = $this->actingAs($rescuer);

        $report = $this->assertOk($this->post('/api/v1/reports', [
            'animal_description' => 'Injured aspin near the Mati boulevard.',
            'latitude' => self::MATI_LAT,
            'longitude' => self::MATI_LNG,
            'address_text' => 'Mati City boulevard',
            'photo_urls' => ['/uploads/reports/lifecycle.png'],
        ], $residentToken), 201);
        $reportId = $report['report']['id'];

        $verified = $this->assertOk($this->post(
            '/api/v1/reports/' . $reportId . '/verify',
            [],
            $adminToken
        ));
        $caseId = $verified['case_id'];
        $this->assertNotEmpty($caseId);
        $open = $this->assertOk($this->get('/api/v1/cases/' . $caseId, [], $adminToken));
        $this->assertSame('open', $open['case']['status']);

        $duty = $this->assertOk($this->patch(
            '/api/v1/rescuers/' . $rescuer['id'] . '/duty',
            ['status' => 'on_duty'],
            $rescuerToken
        ));
        $this->assertSame('on_duty', $duty['duty_status']);

        $assigned = $this->assertOk($this->post(
            '/api/v1/cases/' . $caseId . '/assign',
            ['rescuer_id' => $rescuer['id']],
            $adminToken
        ));
        $this->assertSame('assigned', $assigned['case']['status']);

        $accepted = $this->assertOk($this->post(
            '/api/v1/cases/' . $caseId . '/accept',
            [],
            $rescuerToken
        ));
        $this->assertSame('in_progress', $accepted['case']['status']);

        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'furescue-lifecycle-' . bin2hex(random_bytes(4));
        mkdir($root, 0755, true);
        $_ENV['UPLOAD_PUBLIC_ROOT'] = $root;
        $tmp = $root . DIRECTORY_SEPARATOR . 'proof.png';
        file_put_contents($tmp, (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        ));
        $_FILES = [
            'files' => [
                'name' => 'proof.png',
                'type' => 'image/png',
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmp),
            ],
        ];
        try {
            $proof = $this->assertOk($this->post(
                '/api/v1/cases/' . $caseId . '/proof',
                [],
                $rescuerToken
            ));
            $this->assertNotEmpty($proof['proof']);
        } finally {
            $_FILES = [];
            unset($_ENV['UPLOAD_PUBLIC_ROOT']);
            $this->removeDir($root);
        }

        $this->assertError($this->post(
            '/api/v1/cases/' . $caseId . '/resolve',
            [],
            $rescuerToken
        ), 'FORBIDDEN', 403);

        $resolved = $this->assertOk($this->post(
            '/api/v1/cases/' . $caseId . '/resolve',
            [],
            $adminToken
        ));
        $this->assertSame('resolved', $resolved['case']['status']);

        $animal = $this->assertOk($this->post('/api/v1/animals', [
            'name' => 'Brownie',
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'male',
            'case_id' => $caseId,
            'description' => 'Aspin rescued from the boulevard report.',
        ], $adminToken), 201);
        $animalId = $animal['animal']['id'];
        $this->assertSame($caseId, $animal['animal']['case_id']);

        $this->assertError($this->post('/api/v1/adoption-listings', [
            'animal_id' => $animalId,
        ], $residentToken), 'NOT_HEALTH_READY', 409);

        $this->assertOk($this->put('/api/v1/animals/' . $animalId . '/medical', [
            'vaccination_records' => [
                ['vaccine' => 'Rabies', 'date' => '2026-01-15', 'status' => 'complete'],
            ],
            'weight_kg' => 12.5,
        ], $adminToken));
        $vital = $this->assertOk($this->post(
            '/api/v1/animals/' . $animalId . '/vitals',
            ['heart_rate_bpm' => 90],
            $adminToken
        ), 201);
        $this->assertSame(90, (int) $vital['vital']['heart_rate_bpm']);

        $listed = $this->assertOk($this->patch(
            '/api/v1/animals/' . $animalId,
            ['adoption_status' => 'available'],
            $adminToken
        ));
        $this->assertSame('available', $listed['animal']['adoption_status']);

        $available = $this->assertOk($this->get(
            '/api/v1/animals',
            ['adoption_status' => 'available'],
            $adminToken
        ));
        $ids = array_column($available, 'id');
        $this->assertContains($animalId, $ids);

        $application = $this->assertOk($this->post('/api/v1/adoptions', [
            'animal_id' => $animalId,
            'message' => 'I can give Brownie a quiet home.',
        ], $residentToken), 201);
        $adoptionId = $application['adoption']['id'];

        $approved = $this->assertOk($this->post(
            '/api/v1/adoptions/' . $adoptionId . '/approve',
            [],
            $adminToken
        ));
        $this->assertSame('approved', $approved['adoption']['status']);

        $completed = $this->assertOk($this->post(
            '/api/v1/adoptions/' . $adoptionId . '/complete',
            [],
            $adminToken
        ));
        $this->assertSame('completed', $completed['adoption']['status']);

        $reporterTypes = $this->notificationTypes($resident['id']);
        $this->assertContains('report_verified', $reporterTypes);
        $this->assertContains('case_resolved', $reporterTypes);
        $this->assertContains('adoption_approved', $reporterTypes);
        $this->assertContains('case_assigned', $this->notificationTypes($rescuer['id']));
    }

    private function notificationTypes(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT type FROM notifications WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
