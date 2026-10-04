<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class AnimalsMysqlTest extends MysqlTestCase
{
    public function testResidentCanReadAnimals(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal(['created_by' => $resident['id'], 'name' => 'Brownie']);
        $response = $this->get('/api/v1/animals', [], $this->actingAs($resident));
        $this->assertOk($response);
        $ids = array_column($response['body']['data'] ?? [], 'id');
        $this->assertContains($animal['id'], $ids);
    }

    public function testResidentCannotWriteAnimals(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/animals', [
            'name' => 'No Write',
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'male',
        ], $this->actingAs($resident)), 'FORBIDDEN', 403);
    }
}
