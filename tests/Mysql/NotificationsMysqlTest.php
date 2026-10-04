<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class NotificationsMysqlTest extends MysqlTestCase
{
    public function testResidentCanListOwnNotifications(): void
    {
        $resident = $this->seedResident();
        $this->seedNotification([
            'user_id' => $resident['id'],
            'message' => 'MySQL inbox row',
        ]);
        $response = $this->get('/api/v1/notifications', [], $this->actingAs($resident));
        $this->assertOk($response);
        $this->assertNotEmpty($response['body']['data']);
    }

    public function testResidentCannotBroadcast(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/admin/notifications', [
            'message' => 'Should be denied',
        ], $this->actingAs($resident)), 'FORBIDDEN', 403);
    }
}
