<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class ListingsMysqlTest extends MysqlTestCase
{
    public function testResidentCanCreateListingFromProfile(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->post('/api/v1/adoption-listings', [
            'name' => 'Ligaya',
            'species' => 'dog',
            'sex' => 'female',
            'description' => 'Friendly aspin rescued near the boulevard. Needs a quiet home.',
            'photo_urls' => ['/uploads/demo/animals/ligaya.svg'],
        ], $this->actingAs($resident)), 201);
        $this->assertSame('pending_review', $data['listing']['status']);
    }

    public function testRescuerCannotCreateListing(): void
    {
        $rescuer = $this->seedRescuer();
        $this->assertError($this->post('/api/v1/adoption-listings', [
            'name' => 'Blocked',
            'species' => 'dog',
            'sex' => 'male',
            'description' => 'Should be denied by listings.create permission.',
        ], $this->actingAs($rescuer)), 'FORBIDDEN', 403);
    }
}
