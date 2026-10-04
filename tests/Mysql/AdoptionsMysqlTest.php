<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class AdoptionsMysqlTest extends MysqlTestCase
{
    public function testResidentCanApply(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal([
            'adoption_status' => 'available',
            'created_by' => $resident['id'],
        ]);
        $data = $this->assertOk($this->post('/api/v1/adoptions', [
            'animal_id' => $animal['id'],
            'message' => 'We have a yard',
        ], $this->actingAs($resident)), 201);
        $this->assertSame('pending', $data['adoption']['status']);
    }

    public function testRescuerCannotApply(): void
    {
        $rescuer = $this->seedRescuer();
        $animal = $this->seedAnimal([
            'adoption_status' => 'available',
            'created_by' => $this->seedResident()['id'],
        ]);
        $this->assertError($this->post(
            '/api/v1/adoptions',
            ['animal_id' => $animal['id']],
            $this->actingAs($rescuer)
        ), 'FORBIDDEN', 403);
    }
}
