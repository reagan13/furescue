<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class UsersApiTest extends ApiTestCase
{
    public function testResidentCannotListUsers(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->get('/api/v1/users', [], $this->actingAs($resident)), 'FORBIDDEN', 403);
    }

    public function testAdminCanListUsersWithMeta(): void
    {
        $admin = $this->seedAdmin();
        $this->seedResident();
        $response = $this->get('/api/v1/users', [], $this->actingAs($admin));
        $this->assertOk($response);
        $this->assertArrayHasKey('meta', $response['body']);
        $this->assertGreaterThanOrEqual(2, $response['body']['meta']['total']);
    }

    public function testListUsersRejectsUnknownRole(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/users', ['role' => 'wizard'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListUsersRejectsUnknownAccountStatus(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/users', ['account_status' => 'deleted'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListUsersRejectsPageZero(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->get('/api/v1/users', ['page' => '0'], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testListUsersRejectsNegativePage(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->get('/api/v1/users', ['page' => '-1'], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testListUsersRejectsPerPageOverMax(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->get('/api/v1/users', ['per_page' => '101'], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testListUsersRejectsNonNumericPage(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->get('/api/v1/users', ['page' => 'foo'], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testAdminCanFilterRescuers(): void
    {
        $admin = $this->seedAdmin();
        $this->seedRescuer();
        $response = $this->get('/api/v1/users', ['role' => 'rescuer'], $this->actingAs($admin));
        $this->assertOk($response);
        $this->assertNotEmpty($response['body']['data']);
    }

    public function testResidentCanOpenOwnProfile(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->get('/api/v1/users/' . $resident['id'], [], $this->actingAs($resident)));
        $this->assertSame($resident['id'], $data['user']['id']);
    }

    public function testResidentCannotOpenAnotherProfile(): void
    {
        $resident = $this->seedResident();
        $other = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/users/' . $other['id'], [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testAdminCanOpenAnotherProfile(): void
    {
        $admin = $this->seedAdmin();
        $other = $this->seedResident();
        $data = $this->assertOk($this->get('/api/v1/users/' . $other['id'], [], $this->actingAs($admin)));
        $this->assertSame($other['id'], $data['user']['id']);
    }

    public function testShowUserNotFound(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/users/' . $this->uuid(), [], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testResidentCanEditOwnName(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['full_name' => 'Maria Santos'],
            $this->actingAs($resident)
        ));
        $this->assertSame('Maria Santos', $data['user']['full_name']);
        $this->assertArrayHasKey('profile_photo_url', $data['user']);
    }

    public function testResidentCanSetOwnProfilePhotoUrl(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['profile_photo_url' => 'https://lh3.googleusercontent.com/a/demo-photo'],
            $this->actingAs($resident)
        ));
        $this->assertSame('https://lh3.googleusercontent.com/a/demo-photo', $data['user']['profile_photo_url']);
    }

    public function testResidentCanClearOwnProfilePhotoUrl(): void
    {
        $resident = $this->seedResident(['profile_photo_url' => 'https://i.pravatar.cc/64?img=12']);
        $data = $this->assertOk($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['profile_photo_url' => ''],
            $this->actingAs($resident)
        ));
        $this->assertNull($data['user']['profile_photo_url']);
    }

    public function testPatchRejectsJavascriptProfilePhotoUrl(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['profile_photo_url' => 'javascript:alert(1)'],
            $this->actingAs($resident)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRejectsDataUriProfilePhotoUrl(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['profile_photo_url' => 'data:image/png;base64,aaa'],
            $this->actingAs($resident)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRejectsNumericProfilePhotoUrl(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['profile_photo_url' => 99],
            $this->actingAs($resident)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testShowIncludesProfilePhotoUrl(): void
    {
        $resident = $this->seedResident(['profile_photo_url' => 'https://example.com/me.png']);
        $data = $this->assertOk($this->get('/api/v1/users/' . $resident['id'], [], $this->actingAs($resident)));
        $this->assertSame('https://example.com/me.png', $data['user']['profile_photo_url']);
    }

    public function testResidentCannotPromoteSelfToAdmin(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['role' => 'admin'],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->patch(
            '/api/v1/users/' . $admin['id'],
            ['role' => 'resident'],
            $this->actingAs($admin)
        ), 'FORBIDDEN', 403);
    }

    public function testResidentCannotPatchSomeoneElse(): void
    {
        $resident = $this->seedResident();
        $other = $this->seedResident();
        $this->assertError($this->patch(
            '/api/v1/users/' . $other['id'],
            ['full_name' => 'Hijacked'],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testAdminCanChangeAnotherUsersRole(): void
    {
        $admin = $this->seedAdmin();
        $resident = $this->seedResident();
        $data = $this->assertOk($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['role' => 'rescuer', 'account_status' => 'pending'],
            $this->actingAs($admin)
        ));
        $this->assertSame('rescuer', $data['user']['role']);
        $this->assertSame('pending', $data['user']['account_status']);
    }

    public function testPatchRejectsEmptyBody(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->patch('/api/v1/users/' . $resident['id'], [], $this->actingAs($resident)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testPatchRejectsNumericName(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['full_name' => 99],
            $this->actingAs($resident)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRejectsOverMaxName(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->patch(
            '/api/v1/users/' . $resident['id'],
            ['full_name' => str_repeat('Z', 151)],
            $this->actingAs($resident)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRejectsInvalidAccountStatus(): void
    {
        $admin = $this->seedAdmin();
        $other = $this->seedResident();
        $this->assertError($this->patch(
            '/api/v1/users/' . $other['id'],
            ['account_status' => 'deleted'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchUnknownUserIsNotFound(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->patch('/api/v1/users/' . $this->uuid(), ['full_name' => 'X'], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testMeIncludesDutyStatusForRescuer(): void
    {
        $rescuer = $this->seedRescuer();
        $this->assertOk($this->patch(
            '/api/v1/rescuers/' . $rescuer['id'] . '/duty',
            ['status' => 'on_duty'],
            $this->actingAs($rescuer)
        ));
        $data = $this->assertOk($this->get('/api/v1/users/me', [], $this->actingAs($rescuer)));
        $this->assertSame('on_duty', $data['user']['duty_status']);
    }

    public function testMeOmitsDutyStatusForResident(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->get('/api/v1/users/me', [], $this->actingAs($resident)));
        $this->assertArrayNotHasKey('duty_status', $data['user']);
    }

    public function testRescuerCanToggleOwnDuty(): void
    {
        $rescuer = $this->seedRescuer();
        $data = $this->assertOk($this->patch(
            '/api/v1/rescuers/' . $rescuer['id'] . '/duty',
            ['status' => 'on_duty'],
            $this->actingAs($rescuer)
        ));
        $this->assertSame('on_duty', $data['duty_status']);
    }

    public function testRescuerCannotToggleSomeoneElsesDuty(): void
    {
        $rescuer = $this->seedRescuer();
        $other = $this->seedRescuer();
        $this->assertError($this->patch(
            '/api/v1/rescuers/' . $other['id'] . '/duty',
            ['status' => 'on_duty'],
            $this->actingAs($rescuer)
        ), 'FORBIDDEN', 403);
    }

    public function testPendingRescuerCannotToggleDuty(): void
    {
        $pending = $this->seedRescuer(['account_status' => 'pending']);
        $response = $this->patch(
            '/api/v1/rescuers/' . $pending['id'] . '/duty',
            ['status' => 'on_duty'],
            $this->actingAs($pending)
        );
        $this->assertError($response, 'ACCOUNT_PENDING', 403);
        $message = (string) ($response['body']['error']['message'] ?? '');
        $this->assertSame('This account is not active.', $message);
        $this->assertStringNotContainsString('status:', $message);
    }

    public function testDutyRejectsEmptyBody(): void
    {
        $rescuer = $this->seedRescuer();
        $this->assertError(
            $this->patch('/api/v1/rescuers/' . $rescuer['id'] . '/duty', [], $this->actingAs($rescuer)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testDutyRejectsInvalidStatus(): void
    {
        $rescuer = $this->seedRescuer();
        $this->assertError($this->patch(
            '/api/v1/rescuers/' . $rescuer['id'] . '/duty',
            ['status' => 'vacation'],
            $this->actingAs($rescuer)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testAdminApprovesThenRejectsSameRescuer(): void
    {
        $admin = $this->seedAdmin();
        $rescuer = $this->seedRescuer(['account_status' => 'pending']);
        $token = $this->actingAs($admin);
        $this->assertOk($this->post('/api/v1/admin/rescuers/' . $rescuer['id'] . '/approve', [], $token));
        $this->assertOk($this->post('/api/v1/admin/rescuers/' . $rescuer['id'] . '/reject', [], $token));
        $row = $this->pdo->query("SELECT account_status FROM users WHERE id = '{$rescuer['id']}'")->fetch();
        $this->assertSame('rejected', $row['account_status']);
    }

    public function testAdminCannotRejectAResident(): void
    {
        $admin = $this->seedAdmin();
        $resident = $this->seedResident();
        $this->assertError(
            $this->post('/api/v1/admin/rescuers/' . $resident['id'] . '/reject', [], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testResidentCannotApproveRescuer(): void
    {
        $resident = $this->seedResident();
        $rescuer = $this->seedRescuer(['account_status' => 'pending']);
        $this->assertError(
            $this->post('/api/v1/admin/rescuers/' . $rescuer['id'] . '/approve', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testApproveUnknownRescuerIsNotFound(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->post('/api/v1/admin/rescuers/' . $this->uuid() . '/approve', [], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }
}
