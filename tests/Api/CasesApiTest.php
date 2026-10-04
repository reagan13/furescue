<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class CasesApiTest extends ApiTestCase
{
    public function testAdminCanListCasesWithMeta(): void
    {
        $admin = $this->seedAdmin();
        $this->seedCase();
        $response = $this->get('/api/v1/cases', [], $this->actingAs($admin));
        $this->assertOk($response);
        $this->assertArrayHasKey('meta', $response['body']);
        $this->assertGreaterThanOrEqual(1, $response['body']['meta']['total']);
    }

    public function testListCasesRejectsInvalidStatus(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/cases', ['status' => 'closed'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListCasesRejectsBadPage(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/cases', ['page' => 'foo'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListCasesRejectsInvalidRescuerFilter(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/cases', ['assigned_rescuer_id' => 'rescuer-1'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testRescuerOnlySeesAssignedCases(): void
    {
        $rescuer = $this->seedRescuer();
        $other = $this->seedRescuer();
        $this->seedCase(['assigned_rescuer_id' => $rescuer['id'], 'status' => 'assigned']);
        $this->seedCase(['assigned_rescuer_id' => $other['id'], 'status' => 'assigned']);
        $response = $this->get('/api/v1/cases', [], $this->actingAs($rescuer));
        $this->assertOk($response);
        $this->assertSame(1, $response['body']['meta']['total']);
    }

    public function testShowCaseNotFound(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->get('/api/v1/cases/' . $this->uuid(), [], $this->actingAs($admin)), 'NOT_FOUND', 404);
    }

    public function testRescuerCannotOpenSomeoneElsesCase(): void
    {
        $rescuer = $this->seedRescuer();
        $other = $this->seedRescuer();
        $case = $this->seedCase(['assigned_rescuer_id' => $other['id'], 'status' => 'assigned']);
        $this->assertError(
            $this->get('/api/v1/cases/' . $case['id'], [], $this->actingAs($rescuer)),
            'FORBIDDEN',
            403
        );
    }

    public function testAdminCanShowAnyCase(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase();
        $data = $this->assertOk($this->get('/api/v1/cases/' . $case['id'], [], $this->actingAs($admin)));
        $this->assertSame($case['id'], $data['case']['id']);
    }

    public function testAssignRejectsOffDutyRescuer(): void
    {
        $admin = $this->seedAdmin();
        $rescuer = $this->seedRescuer();
        $case = $this->seedCase(['status' => 'open']);
        $this->assertError($this->post(
            '/api/v1/cases/' . $case['id'] . '/assign',
            ['rescuer_id' => $rescuer['id']],
            $this->actingAs($admin)
        ), 'RESCUER_OFF_DUTY', 422);
    }

    public function testAssignRejectsPendingRescuer(): void
    {
        $admin = $this->seedAdmin();
        $pending = $this->seedRescuer(['account_status' => 'pending', 'on_duty' => true]);
        $case = $this->seedCase(['status' => 'open']);
        $this->assertError($this->post(
            '/api/v1/cases/' . $case['id'] . '/assign',
            ['rescuer_id' => $pending['id']],
            $this->actingAs($admin)
        ), 'INVALID_RESCUER', 422);
    }

    public function testAssignSucceedsForOnDutyRescuer(): void
    {
        $admin = $this->seedAdmin();
        $rescuer = $this->seedRescuer(['on_duty' => true]);
        $case = $this->seedCase(['status' => 'open']);
        $data = $this->assertOk($this->post(
            '/api/v1/cases/' . $case['id'] . '/assign',
            ['rescuer_id' => $rescuer['id']],
            $this->actingAs($admin)
        ));
        $this->assertSame('assigned', $data['case']['status']);
        $this->assertSame($rescuer['id'], $data['case']['assigned_rescuer_id']);
    }

    public function testAssignRejectsEmptyBody(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase(['status' => 'open']);
        $this->assertError(
            $this->post('/api/v1/cases/' . $case['id'] . '/assign', [], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testAssignRejectsInvalidUuid(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase(['status' => 'open']);
        $this->assertError($this->post(
            '/api/v1/cases/' . $case['id'] . '/assign',
            ['rescuer_id' => 'rescuer-1'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testAssignedRescuerCanAccept(): void
    {
        $rescuer = $this->seedRescuer(['on_duty' => true]);
        $case = $this->seedCase(['assigned_rescuer_id' => $rescuer['id'], 'status' => 'assigned']);
        $data = $this->assertOk($this->post('/api/v1/cases/' . $case['id'] . '/accept', [], $this->actingAs($rescuer)));
        $this->assertSame('in_progress', $data['case']['status']);
    }

    public function testDifferentRescuerCannotAccept(): void
    {
        $assigned = $this->seedRescuer(['on_duty' => true]);
        $other = $this->seedRescuer(['on_duty' => true]);
        $case = $this->seedCase(['assigned_rescuer_id' => $assigned['id'], 'status' => 'assigned']);
        $this->assertError(
            $this->post('/api/v1/cases/' . $case['id'] . '/accept', [], $this->actingAs($other)),
            'FORBIDDEN',
            403
        );
    }

    public function testRescuerCannotResolveUnassignedCase(): void
    {
        $rescuer = $this->seedRescuer();
        $case = $this->seedCase(['status' => 'open']);
        $this->assertError(
            $this->post('/api/v1/cases/' . $case['id'] . '/resolve', [], $this->actingAs($rescuer)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentResolveHidesPermissionSlug(): void
    {
        $resident = $this->seedResident();
        $case = $this->seedCase(['status' => 'in_progress']);
        $response = $this->post(
            '/api/v1/cases/' . $case['id'] . '/resolve',
            [],
            $this->actingAs($resident)
        );
        $this->assertError($response, 'FORBIDDEN', 403);
        $message = (string) ($response['body']['error']['message'] ?? '');
        $this->assertSame('You do not have permission to do that.', $message);
        $this->assertStringNotContainsString('cases.status_change', $message);
    }

    public function testAdminCannotResolveAlreadyResolvedCase(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase(['status' => 'resolved']);
        $this->assertError(
            $this->post('/api/v1/cases/' . $case['id'] . '/resolve', [], $this->actingAs($admin)),
            'INVALID_STATUS',
            422
        );
    }

    public function testResolveWithoutProofIsRejected(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase(['status' => 'in_progress']);
        $this->assertError(
            $this->post('/api/v1/cases/' . $case['id'] . '/resolve', [], $this->actingAs($admin)),
            'PROOF_REQUIRED',
            422
        );
    }

    public function testAdminCanResolveWhenProofIsOnFile(): void
    {
        $admin = $this->seedAdmin();
        $report = $this->seedReport([
            'photo_urls' => ['/uploads/report.jpg'],
        ]);
        $case = $this->seedCase([
            'report_id' => $report['id'],
            'status' => 'in_progress',
            'resolution_photos' => json_encode(['/uploads/proof.jpg']),
        ]);
        $data = $this->assertOk($this->post('/api/v1/cases/' . $case['id'] . '/resolve', [], $this->actingAs($admin)));
        $this->assertSame('resolved', $data['case']['status']);
    }

    public function testResolveWithoutAttachmentsIsRejected(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase([
            'status' => 'in_progress',
            'resolution_photos' => json_encode(['/uploads/proof.jpg']),
        ]);
        $this->assertError(
            $this->post('/api/v1/cases/' . $case['id'] . '/resolve', [], $this->actingAs($admin)),
            'ATTACHMENTS_REQUIRED',
            422
        );
    }

    public function testAdminCanUploadProofOnInProgressCase(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'furescue-case-proof-' . bin2hex(random_bytes(4));
        mkdir($root, 0755, true);
        $_ENV['UPLOAD_PUBLIC_ROOT'] = $root;
        $tmp = $root . DIRECTORY_SEPARATOR . 'proof.png';
        file_put_contents($tmp, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true));
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
            $admin = $this->seedAdmin();
            $case = $this->seedCase(['status' => 'in_progress']);
            $data = $this->assertOk($this->post(
                '/api/v1/cases/' . $case['id'] . '/proof',
                [],
                $this->actingAs($admin)
            ));
            $this->assertNotEmpty($data['proof']);
            $this->assertStringStartsWith('/uploads/cases/', $data['proof'][0]);
        } finally {
            $_FILES = [];
            unset($_ENV['UPLOAD_PUBLIC_ROOT']);
            $this->removeDir($root);
        }
    }

    public function testAdminCannotPatchStatusDirectly(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase(['status' => 'assigned']);
        $this->assertError($this->patch(
            '/api/v1/cases/' . $case['id'] . '/status',
            ['status' => 'in_progress'],
            $this->actingAs($admin)
        ), 'WORKFLOW_REQUIRED', 409);
    }

    public function testStatusPatchRejectsEmptyBody(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase();
        $this->assertError(
            $this->patch('/api/v1/cases/' . $case['id'] . '/status', [], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testStatusPatchRejectsUnknownEnum(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase();
        $this->assertError($this->patch(
            '/api/v1/cases/' . $case['id'] . '/status',
            ['status' => 'closed'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testResidentCannotAssign(): void
    {
        $resident = $this->seedResident();
        $case = $this->seedCase(['status' => 'open']);
        $this->assertError($this->post(
            '/api/v1/cases/' . $case['id'] . '/assign',
            ['rescuer_id' => $this->uuid()],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testActivityForbiddenForOtherRescuer(): void
    {
        $rescuer = $this->seedRescuer();
        $other = $this->seedRescuer();
        $case = $this->seedCase(['assigned_rescuer_id' => $other['id'], 'status' => 'assigned']);
        $this->assertError(
            $this->get('/api/v1/cases/' . $case['id'] . '/activity', [], $this->actingAs($rescuer)),
            'FORBIDDEN',
            403
        );
    }

    public function testProofUrlBodyIsRejected(): void
    {
        $rescuer = $this->seedRescuer();
        $case = $this->seedCase(['assigned_rescuer_id' => $rescuer['id'], 'status' => 'in_progress']);
        $this->assertError($this->post(
            '/api/v1/cases/' . $case['id'] . '/proof',
            ['url' => 'https://example.com/photo.jpg'],
            $this->actingAs($rescuer)
        ), 'VALIDATION_ERROR', 400);
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
