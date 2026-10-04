<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class MessagesMysqlTest extends MysqlTestCase
{
    public function testOwnerCanOpenAReportThread(): void
    {
        $owner = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $data = $this->assertOk($this->post('/api/v1/messages', [
            'receiver_id' => $admin['id'],
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => 'Still there?',
        ], $this->actingAs($owner)), 201);
        $this->assertSame('Still there?', $data['message']['message_text']);
    }

    public function testStrangerCannotMessageAboutSomeoneElsesReport(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $admin['id'],
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => 'Not my report',
        ], $this->actingAs($other)), 'FORBIDDEN', 403);
    }
}
