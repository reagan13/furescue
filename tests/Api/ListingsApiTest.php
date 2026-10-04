<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class ListingsApiTest extends ApiTestCase
{
    public function testCreateRejectsAnimalThatIsNotHealthReady(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal();
        $this->assertError($this->post(
            '/api/v1/adoption-listings',
            ['animal_id' => $animal['id']],
            $this->actingAs($resident)
        ), 'NOT_HEALTH_READY', 409);
    }

    public function testCreateHappyPathForEligibleAnimal(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedEligibleAnimal();
        $data = $this->assertOk($this->post(
            '/api/v1/adoption-listings',
            ['animal_id' => $animal['id']],
            $this->actingAs($resident)
        ), 201);
        $this->assertSame('pending_review', $data['listing']['status']);
    }

    public function testCannotRelistWhileLiveListingExists(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedEligibleAnimal();
        $token = $this->actingAs($resident);
        $this->assertOk($this->post('/api/v1/adoption-listings', ['animal_id' => $animal['id']], $token), 201);
        $this->assertError(
            $this->post('/api/v1/adoption-listings', ['animal_id' => $animal['id']], $token),
            'LISTING_EXISTS',
            409
        );
    }

    public function testRejectedListingMayBeRelisted(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedEligibleAnimal();
        $this->seedListing(['animal_id' => $animal['id'], 'posted_by' => $resident['id'], 'status' => 'rejected']);
        $data = $this->assertOk($this->post(
            '/api/v1/adoption-listings',
            ['animal_id' => $animal['id']],
            $this->actingAs($resident)
        ), 201);
        $this->assertSame('pending_review', $data['listing']['status']);
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/adoption-listings', [], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateFromProfileDoesNotRequireHealthRecords(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->post(
            '/api/v1/adoption-listings',
            [
                'name' => 'Ligaya',
                'species' => 'dog',
                'sex' => 'female',
                'description' => 'Friendly aspin rescued near the boulevard. Needs a quiet home.',
                'photo_urls' => ['/uploads/demo/animals/ligaya.svg'],
            ],
            $this->actingAs($resident)
        ), 201);
        $this->assertSame('pending_review', $data['listing']['status']);
        $this->assertSame('Ligaya', $data['animal']['name']);
        $this->assertSame('resident_listing', $data['animal']['source']);
    }

    public function testCreateFromProfileRequiresPhoto(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post(
            '/api/v1/adoption-listings',
            [
                'name' => 'Sky',
                'species' => 'dog',
                'sex' => 'male',
                'description' => 'A rescued aspin looking for a home.',
            ],
            $this->actingAs($resident)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsInvalidUuid(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post(
            '/api/v1/adoption-listings',
            ['animal_id' => 'animal-1'],
            $this->actingAs($resident)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testCreateMissingAnimal(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post(
            '/api/v1/adoption-listings',
            ['animal_id' => $this->uuid()],
            $this->actingAs($resident)
        ), 'NOT_FOUND', 404);
    }

    public function testRescuerCannotCreateListing(): void
    {
        $rescuer = $this->seedRescuer();
        $animal = $this->seedEligibleAnimal();
        $this->assertError($this->post(
            '/api/v1/adoption-listings',
            ['animal_id' => $animal['id']],
            $this->actingAs($rescuer)
        ), 'FORBIDDEN', 403);
    }

    public function testResidentListIsScoped(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $this->seedListing(['posted_by' => $owner['id']]);
        $this->seedListing(['posted_by' => $other['id']]);
        $response = $this->get('/api/v1/adoption-listings', [], $this->actingAs($owner));
        $this->assertOk($response);
        $this->assertSame(1, $response['body']['meta']['total']);
    }

    public function testListRejectsInvalidStatus(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/adoption-listings', ['status' => 'live'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListRejectsBadPage(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/adoption-listings', ['page' => '-1'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testShowListingHappy(): void
    {
        $resident = $this->seedResident();
        $listing = $this->seedListing(['posted_by' => $resident['id']]);
        $data = $this->assertOk($this->get(
            '/api/v1/adoption-listings/' . $listing['id'],
            [],
            $this->actingAs($resident)
        ));
        $this->assertSame($listing['id'], $data['listing']['id']);
    }

    public function testResidentCannotShowSomeoneElsesListing(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $listing = $this->seedListing(['posted_by' => $owner['id']]);
        $response = $this->get(
            '/api/v1/adoption-listings/' . $listing['id'],
            [],
            $this->actingAs($other)
        );
        $this->assertError($response, 'FORBIDDEN', 403);
        $this->assertSame('Not your listing', $response['body']['error']['message'] ?? null);
    }

    public function testAdminCanShowAnyListing(): void
    {
        $owner = $this->seedResident();
        $admin = $this->seedAdmin();
        $listing = $this->seedListing(['posted_by' => $owner['id']]);
        $data = $this->assertOk($this->get(
            '/api/v1/adoption-listings/' . $listing['id'],
            [],
            $this->actingAs($admin)
        ));
        $this->assertSame($listing['id'], $data['listing']['id']);
    }

    public function testShowListingNotFound(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/adoption-listings/' . $this->uuid(), [], $this->actingAs($resident)),
            'NOT_FOUND',
            404
        );
    }

    public function testAdminCanApproveEligibleListing(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedEligibleAnimal();
        $listing = $this->seedListing(['animal_id' => $animal['id']]);
        $data = $this->assertOk($this->post(
            '/api/v1/adoption-listings/' . $listing['id'] . '/approve',
            [],
            $this->actingAs($admin)
        ));
        $this->assertSame('approved', $data['listing']['status']);
    }

    public function testRejectRequiresNotes(): void
    {
        $admin = $this->seedAdmin();
        $listing = $this->seedListing();
        $this->assertError($this->post(
            '/api/v1/adoption-listings/' . $listing['id'] . '/reject',
            [],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testAdminCanRejectWithNotes(): void
    {
        $admin = $this->seedAdmin();
        $listing = $this->seedListing();
        $data = $this->assertOk($this->post(
            '/api/v1/adoption-listings/' . $listing['id'] . '/reject',
            ['review_notes' => 'Photos are unclear'],
            $this->actingAs($admin)
        ));
        $this->assertSame('rejected', $data['listing']['status']);
    }

    public function testResidentCannotApprove(): void
    {
        $resident = $this->seedResident();
        $listing = $this->seedListing();
        $this->assertError($this->post(
            '/api/v1/adoption-listings/' . $listing['id'] . '/approve',
            [],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testApproveUnknownListing(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post(
            '/api/v1/adoption-listings/' . $this->uuid() . '/approve',
            [],
            $this->actingAs($admin)
        ), 'NOT_FOUND', 404);
    }

    public function testAdminListIncludesAnimalSpecies(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal(['name' => 'Ace', 'species' => 'dog']);
        $this->seedListing(['animal_id' => $animal['id']]);
        $response = $this->get('/api/v1/adoption-listings', [], $this->actingAs($admin));
        $items = $this->assertOk($response);
        $this->assertNotEmpty($items);
        $this->assertSame('dog', $items[0]['species'] ?? null);
        $this->assertSame('Ace', $items[0]['animal_name'] ?? null);
    }
}
