<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class AuthApiTest extends ApiTestCase
{
    public function testRegisterCreatesResidentAndIssuesTokens(): void
    {
        $data = $this->assertOk($this->post('/api/v1/auth/register', [
            'full_name' => 'Juan dela Cruz',
            'email' => 'juan-auth@test.local',
            'password' => self::PASSWORD,
        ]), 201);
        $this->assertSame('resident', $data['user']['role']);
        $this->assertSame('active', $data['user']['account_status']);
        $this->assertArrayHasKey('profile_photo_url', $data['user']);
        $this->assertNull($data['user']['profile_photo_url']);
        $this->assertNotEmpty($data['tokens']['access_token']);
        $this->assertNotEmpty($data['tokens']['refresh_token']);
    }

    public function testRegisterAsRescuerStaysPending(): void
    {
        $data = $this->assertOk($this->post('/api/v1/auth/register', [
            'full_name' => 'Pending Rescuer',
            'email' => 'pending-rescuer@test.local',
            'password' => self::PASSWORD,
            'role' => 'rescuer',
        ]), 201);
        $this->assertSame('rescuer', $data['user']['role']);
        $this->assertSame('pending', $data['user']['account_status']);
    }

    public function testRegisterAsAdminIsRejected(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', [
            'full_name' => 'Eve Admin',
            'email' => 'eve@test.local',
            'password' => self::PASSWORD,
            'role' => 'admin',
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testRegisterSameEmailTwiceConflicts(): void
    {
        $body = [
            'full_name' => 'Juan',
            'email' => 'dup@test.local',
            'password' => self::PASSWORD,
        ];
        $this->assertOk($this->post('/api/v1/auth/register', $body), 201);
        $this->assertError($this->post('/api/v1/auth/register', $body), 'EMAIL_TAKEN', 409);
    }

    public function testRegisterThreeCharacterPasswordFails(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', [
            'full_name' => 'Short Pass',
            'email' => 'short@test.local',
            'password' => 'abc',
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testRegisterRejectsEmptyBody(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', []), 'VALIDATION_ERROR', 400);
    }

    public function testRegisterRejectsWhitespaceName(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', [
            'full_name' => '   ',
            'email' => 'space@test.local',
            'password' => self::PASSWORD,
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testRegisterRejectsNumericName(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', [
            'full_name' => 12345,
            'email' => 'numname@test.local',
            'password' => self::PASSWORD,
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testRegisterRejectsOverMaxName(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', [
            'full_name' => str_repeat('A', 151),
            'email' => 'long@test.local',
            'password' => self::PASSWORD,
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testRegisterRejectsInvalidEmail(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', [
            'full_name' => 'Bad Email',
            'email' => 'not-an-email',
            'password' => self::PASSWORD,
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testRegisterRejectsMissingPassword(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', [
            'full_name' => 'No Password',
            'email' => 'nopass@test.local',
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testLoginHappyPath(): void
    {
        $user = $this->seedResident(['email' => 'login-ok@test.local']);
        $data = $this->assertOk($this->post('/api/v1/auth/login', [
            'email' => $user['email'],
            'password' => self::PASSWORD,
        ]));
        $this->assertNotEmpty($data['tokens']['access_token']);
    }

    public function testLoginWrongPassword(): void
    {
        $user = $this->seedResident(['email' => 'login-bad@test.local']);
        $this->assertError($this->post('/api/v1/auth/login', [
            'email' => $user['email'],
            'password' => 'wrong-password',
        ]), 'INVALID_CREDENTIALS', 401);
    }

    public function testLoginPendingRescuerIsBlocked(): void
    {
        $user = $this->seedRescuer([
            'email' => 'pending-login@test.local',
            'account_status' => 'pending',
        ]);
        $response = $this->post('/api/v1/auth/login', [
            'email' => $user['email'],
            'password' => self::PASSWORD,
        ]);
        $this->assertError($response, 'ACCOUNT_PENDING', 403);
        $message = (string) ($response['body']['error']['message'] ?? '');
        $this->assertSame('This account is not active.', $message);
        $this->assertStringNotContainsString('status:', $message);
    }

    public function testLoginRejectedAccountDoesNotLeakStatus(): void
    {
        $user = $this->seedRescuer([
            'email' => 'rejected-login@test.local',
            'account_status' => 'rejected',
        ]);
        $response = $this->post('/api/v1/auth/login', [
            'email' => $user['email'],
            'password' => self::PASSWORD,
        ]);
        $this->assertError($response, 'ACCOUNT_PENDING', 403);
        $message = (string) ($response['body']['error']['message'] ?? '');
        $this->assertSame('This account is not active.', $message);
        $this->assertStringNotContainsString('status:', $message);
        $this->assertStringNotContainsString('rejected', $message);
    }

    public function testLoginEmptyBodyFails(): void
    {
        $this->assertError($this->post('/api/v1/auth/login', []), 'VALIDATION_ERROR', 400);
    }

    public function testLoginRejectsNumericPassword(): void
    {
        $this->assertError($this->post('/api/v1/auth/login', [
            'email' => 'a@b.com',
            'password' => 12345678,
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testRefreshExchangesRefreshToken(): void
    {
        $registered = $this->assertOk($this->post('/api/v1/auth/register', [
            'full_name' => 'Refresh User',
            'email' => 'refresh@test.local',
            'password' => self::PASSWORD,
        ]), 201);
        $data = $this->assertOk($this->post('/api/v1/auth/refresh', [
            'refresh_token' => $registered['tokens']['refresh_token'],
        ]));
        $this->assertNotEmpty($data['access_token']);
    }

    public function testRefreshTamperedTokenFails(): void
    {
        $registered = $this->assertOk($this->post('/api/v1/auth/register', [
            'full_name' => 'Tamper User',
            'email' => 'tamper@test.local',
            'password' => self::PASSWORD,
        ]), 201);
        $this->assertError($this->post('/api/v1/auth/refresh', [
            'refresh_token' => $registered['tokens']['refresh_token'] . 'tamper',
        ]), 'INVALID_REFRESH_TOKEN', 401);
    }

    public function testRefreshEmptyBodyFails(): void
    {
        $this->assertError($this->post('/api/v1/auth/refresh', []), 'VALIDATION_ERROR', 400);
    }

    public function testRefreshWhitespaceTokenFails(): void
    {
        $this->assertError($this->post('/api/v1/auth/refresh', [
            'refresh_token' => '   ',
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testGoogleMissingTokenFails(): void
    {
        $this->assertError($this->post('/api/v1/auth/google', []), 'VALIDATION_ERROR', 400);
    }

    public function testGoogleInvalidTokenFails(): void
    {
        $this->assertError($this->post('/api/v1/auth/google', [
            'id_token' => 'not-a-google-token',
        ]), 'GOOGLE_AUTH_FAILED', 401);
    }

    public function testGoogleRejectsNumericToken(): void
    {
        $this->assertError($this->post('/api/v1/auth/google', [
            'id_token' => 99,
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testMeRequiresAuth(): void
    {
        $this->assertError($this->get('/api/v1/users/me'), 'UNAUTHENTICATED', 401);
    }

    public function testMeReturnsCurrentUser(): void
    {
        $user = $this->seedResident();
        $data = $this->assertOk($this->get('/api/v1/users/me', [], $this->actingAs($user)));
        $this->assertSame($user['id'], $data['user']['id']);
        $this->assertArrayHasKey('profile_photo_url', $data['user']);
        $this->assertNull($data['user']['profile_photo_url']);
    }

    public function testRegisterUnknownRoleRejected(): void
    {
        $this->assertError($this->post('/api/v1/auth/register', [
            'full_name' => 'Wizard',
            'email' => 'wizard@test.local',
            'password' => self::PASSWORD,
            'role' => 'wizard',
        ]), 'VALIDATION_ERROR', 400);
    }

    public function testLoginUnknownEmailFails(): void
    {
        $this->assertError($this->post('/api/v1/auth/login', [
            'email' => 'missing@test.local',
            'password' => self::PASSWORD,
        ]), 'INVALID_CREDENTIALS', 401);
    }

    public function testRefreshOverMaxTokenStillValidatedAsString(): void
    {
        $this->assertError($this->post('/api/v1/auth/refresh', [
            'refresh_token' => str_repeat('x', 1001),
        ]), 'VALIDATION_ERROR', 400);
    }
}
