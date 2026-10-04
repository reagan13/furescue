<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class UsersMysqlTest extends MysqlTestCase
{
    public function testMeReturnsTheAuthenticatedUser(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->get('/api/v1/users/me', [], $this->actingAs($resident)));
        $this->assertSame($resident['id'], $data['user']['id']);
        $this->assertSame('resident', $data['user']['role']);
    }

    public function testMeRequiresABearerToken(): void
    {
        $this->assertError($this->get('/api/v1/users/me'), 'UNAUTHENTICATED', 401);
    }
}
