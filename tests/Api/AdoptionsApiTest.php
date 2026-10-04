<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class AdoptionsApiTest extends ApiTestCase
{
    public function testResidentCanApplyForAvailableAnimal(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal(['adoption_status' => 'available']);
        $data = $this->assertOk($this->post(
            '/api/v1/adoptions',
            ['animal_id' => $animal['id'], 'message' => 'We have a yard'],
            $this->actingAs($resident)
        ), 201);
        $this->assertSame('pending', $data['adoption']['status']);
    }

    public function testCannotApplyForNotListedAnimal(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal(['adoption_status' => 'not_listed']);
        $this->assertError($this->post(
            '/api/v1/adoptions',
            ['animal_id' => $animal['id']],
            $this->actingAs($resident)
        ), 'NOT_ADOPTABLE', 409);
    }

    public function testCannotApplyForAdoptedAnimal(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal(['adoption_status' => 'adopted']);
        $this->assertError($this->post(
            '/api/v1/adoptions',
            ['animal_id' => $animal['id']],
            $this->actingAs($resident)
        ), 'NOT_ADOPTABLE', 409);
    }

    public function testSecondPendingApplyIsConflict(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal(['adoption_status' => 'available']);
        $token = $this->actingAs($resident);
        $this->assertOk($this->post('/api/v1/adoptions', ['animal_id' => $animal['id']], $token), 201);
        $this->assertError(
            $this->post('/api/v1/adoptions', ['animal_id' => $animal['id']], $token),
            'APPLICATION_EXISTS',
            409
        );
    }

    public function testMissingAnimalIsNotFound(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post(
            '/api/v1/adoptions',
            ['animal_id' => $this->uuid()],
            $this->actingAs($resident)
        ), 'NOT_FOUND', 404);
    }

    public function testApplyRejectsInvalidUuid(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post(
            '/api/v1/adoptions',
            ['animal_id' => 'animal-1'],
            $this->actingAs($resident)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testApplyRejectsEmptyBody(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/adoptions', [], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testApplyRejectsNumericMessage(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal(['adoption_status' => 'available']);
        $this->assertError($this->post('/api/v1/adoptions', [
            'animal_id' => $animal['id'],
            'message' => 99,
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testRescuerCannotApply(): void
    {
        $rescuer = $this->seedRescuer();
        $animal = $this->seedAnimal(['adoption_status' => 'available']);
        $this->assertError($this->post(
            '/api/v1/adoptions',
            ['animal_id' => $animal['id']],
            $this->actingAs($rescuer)
        ), 'FORBIDDEN', 403);
    }

    public function testApplicantCanCancelPending(): void
    {
        $resident = $this->seedResident();
        $adoption = $this->seedAdoption(['applicant_id' => $resident['id'], 'status' => 'pending']);
        $data = $this->assertOk($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/cancel',
            [],
            $this->actingAs($resident)
        ));
        $this->assertSame('cancelled', $data['adoption']['status']);
    }

    public function testCannotCancelAfterApproved(): void
    {
        $resident = $this->seedResident();
        $adoption = $this->seedAdoption(['applicant_id' => $resident['id'], 'status' => 'approved']);
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/cancel',
            [],
            $this->actingAs($resident)
        ), 'INVALID_STATE', 409);
    }

    public function testOtherResidentCannotCancelYourApplication(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $adoption = $this->seedAdoption(['applicant_id' => $owner['id']]);
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/cancel',
            [],
            $this->actingAs($other)
        ), 'FORBIDDEN', 403);
    }

    public function testResidentCannotSeeSomeoneElsesApplication(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $adoption = $this->seedAdoption(['applicant_id' => $owner['id']]);
        $this->assertError(
            $this->get('/api/v1/adoptions/' . $adoption['id'], [], $this->actingAs($other)),
            'FORBIDDEN',
            403
        );
    }

    public function testShowAdoptionNotFound(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/adoptions/' . $this->uuid(), [], $this->actingAs($resident)),
            'NOT_FOUND',
            404
        );
    }

    public function testResidentListIsScopedToSelf(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $this->seedAdoption(['applicant_id' => $owner['id']]);
        $this->seedAdoption(['applicant_id' => $other['id']]);
        $response = $this->get('/api/v1/adoptions', [], $this->actingAs($owner));
        $this->assertOk($response);
        $this->assertSame(1, $response['body']['meta']['total']);
    }

    public function testListRejectsInvalidStatus(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/adoptions', ['status' => 'lost'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListRejectsBadPerPage(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/adoptions', ['per_page' => 'foo'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testAdminCanApprove(): void
    {
        $admin = $this->seedAdmin();
        $adoption = $this->seedAdoption();
        $data = $this->assertOk($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/approve',
            [],
            $this->actingAs($admin)
        ));
        $this->assertSame('approved', $data['adoption']['status']);
    }

    public function testRejectRequiresReason(): void
    {
        $admin = $this->seedAdmin();
        $adoption = $this->seedAdoption();
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/reject',
            [],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testAdminCanRejectWithReason(): void
    {
        $admin = $this->seedAdmin();
        $adoption = $this->seedAdoption();
        $data = $this->assertOk($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/reject',
            ['rejection_reason' => 'Incomplete home check'],
            $this->actingAs($admin)
        ));
        $this->assertSame('rejected', $data['adoption']['status']);
    }

    public function testCannotReviewTwice(): void
    {
        $admin = $this->seedAdmin();
        $adoption = $this->seedAdoption(['status' => 'approved']);
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/approve',
            [],
            $this->actingAs($admin)
        ), 'ALREADY_REVIEWED', 409);
    }

    public function testCompleteRequiresApproved(): void
    {
        $admin = $this->seedAdmin();
        $adoption = $this->seedAdoption(['status' => 'pending']);
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/complete',
            [],
            $this->actingAs($admin)
        ), 'INVALID_STATE', 409);
    }

    public function testAdminCanCompleteApproved(): void
    {
        $admin = $this->seedAdmin();
        $adoption = $this->seedAdoption(['status' => 'approved']);
        $data = $this->assertOk($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/complete',
            [],
            $this->actingAs($admin)
        ));
        $this->assertSame('completed', $data['adoption']['status']);
    }

    public function testResidentCannotApprove(): void
    {
        $resident = $this->seedResident();
        $adoption = $this->seedAdoption();
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/approve',
            [],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testResidentCannotReject(): void
    {
        $resident = $this->seedResident();
        $adoption = $this->seedAdoption();
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/reject',
            ['rejection_reason' => 'no'],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testCancelUnknownIsNotFound(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $this->uuid() . '/cancel',
            [],
            $this->actingAs($resident)
        ), 'NOT_FOUND', 404);
    }

    public function testRejectOverMaxReason(): void
    {
        $admin = $this->seedAdmin();
        $adoption = $this->seedAdoption();
        $this->assertError($this->post(
            '/api/v1/adoptions/' . $adoption['id'] . '/reject',
            ['rejection_reason' => str_repeat('x', 501)],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testAdminListSeesAll(): void
    {
        $admin = $this->seedAdmin();
        $this->seedAdoption();
        $this->seedAdoption();
        $response = $this->get('/api/v1/adoptions', [], $this->actingAs($admin));
        $this->assertOk($response);
        $this->assertSame(2, $response['body']['meta']['total']);
    }

    public function testAdminListIncludesSpeciesAndPhotos(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal([
            'name' => 'Ace',
            'species' => 'dog',
            'breed_type' => 'aspin',
            'adoption_status' => 'available',
        ]);
        $this->pdo->prepare('UPDATE animals SET photo_urls = ? WHERE id = ?')->execute([
            json_encode(['/uploads/demo/animals/ace.svg']),
            $animal['id'],
        ]);
        $this->seedAdoption(['animal_id' => $animal['id']]);
        $response = $this->get('/api/v1/adoptions', [], $this->actingAs($admin));
        $items = $this->assertOk($response);
        $this->assertNotEmpty($items);
        $this->assertSame('dog', $items[0]['species'] ?? null);
        $this->assertSame('Ace', $items[0]['animal_name'] ?? null);
        $this->assertArrayHasKey('photo_urls', $items[0]);
        $this->assertStringContainsString('ace.svg', (string) $items[0]['photo_urls']);
    }
}
