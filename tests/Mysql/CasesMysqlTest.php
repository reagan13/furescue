<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class CasesMysqlTest extends MysqlTestCase
{
    public function testAssignedRescuerCanAccept(): void
    {
        $rescuer = $this->seedRescuer(['on_duty' => true]);
        $case = $this->seedCase([
            'assigned_rescuer_id' => $rescuer['id'],
            'status' => 'assigned',
        ]);
        $data = $this->assertOk($this->post(
            '/api/v1/cases/' . $case['id'] . '/accept',
            [],
            $this->actingAs($rescuer)
        ));
        $this->assertSame('in_progress', $data['case']['status']);
    }

    public function testResidentCannotAccept(): void
    {
        $rescuer = $this->seedRescuer(['on_duty' => true]);
        $resident = $this->seedResident();
        $case = $this->seedCase([
            'assigned_rescuer_id' => $rescuer['id'],
            'status' => 'assigned',
        ]);
        $this->assertError(
            $this->post('/api/v1/cases/' . $case['id'] . '/accept', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }
}
