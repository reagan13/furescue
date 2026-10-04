<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class AuthMysqlTest extends MysqlTestCase
{
    public function testRegisterLoginAndRefreshOnMysql(): void
    {
        $email = 'mysql-auth-' . substr($this->uuid(), 0, 8) . '@test.local';
        $registered = $this->assertOk($this->post('/api/v1/auth/register', [
            'full_name' => 'MySQL Auth',
            'email' => $email,
            'password' => self::PASSWORD,
        ]), 201);
        $this->assertSame('resident', $registered['user']['role']);
        $this->assertNotEmpty($registered['tokens']['access_token']);

        $login = $this->assertOk($this->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => self::PASSWORD,
        ]));
        $this->assertNotEmpty($login['tokens']['refresh_token']);

        $refresh = $this->assertOk($this->post('/api/v1/auth/refresh', [
            'refresh_token' => $login['tokens']['refresh_token'],
        ]));
        $this->assertNotEmpty($refresh['access_token']);
    }

    public function testPendingRescuerCannotLogin(): void
    {
        $user = $this->seedRescuer(['account_status' => 'pending']);
        $this->assertError($this->post('/api/v1/auth/login', [
            'email' => $user['email'],
            'password' => self::PASSWORD,
        ]), 'ACCOUNT_PENDING', 403);
    }
}
