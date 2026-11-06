<?php

namespace App\Tests\Api;

use App\Services\AnimalAssetUpload;
use App\Tests\Support\ApiTestCase;

class AnimalsApiTest extends ApiTestCase
{
    public function testListAnimalsReturnsMeta(): void
    {
        $resident = $this->seedResident();
        $this->seedAnimal(['adoption_status' => 'available']);
        $this->seedAnimal(['adoption_status' => 'not_listed']);
        $response = $this->get('/api/v1/animals', [], $this->actingAs($resident));
        $this->assertOk($response);
        $this->assertSame(1, $response['body']['meta']['total']);
    }

    public function testListAnimalsRejectsInvalidSpecies(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/animals', ['species' => 'bird'], $this->actingAs($resident)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListAnimalsRejectsInvalidAdoptionStatus(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/animals', ['adoption_status' => 'sold'], $this->actingAs($resident)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListAnimalsRejectsBadPage(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/animals', ['page' => '0'], $this->actingAs($resident)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListAnimalsRejectsInvalidCaseId(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/animals', ['case_id' => 'case-1'], $this->actingAs($resident)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testShowAnimalNotFound(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/animals/' . $this->uuid(), [], $this->actingAs($resident)),
            'NOT_FOUND',
            404
        );
    }

    public function testShowAnimalIncludesMedical(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedEligibleAnimal(['adoption_status' => 'available']);
        $data = $this->assertOk($this->get('/api/v1/animals/' . $animal['id'], [], $this->actingAs($resident)));
        $this->assertSame($animal['id'], $data['animal']['id']);
        $this->assertArrayNotHasKey('medical', $data['animal']);
        $admin = $this->seedAdmin();
        $adminView = $this->assertOk($this->get('/api/v1/animals/' . $animal['id'], [], $this->actingAs($admin)));
        $this->assertArrayHasKey('medical', $adminView['animal']);
    }

    public function testResidentCannotCreateAnimal(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'male',
        ], $this->actingAs($resident)), 'FORBIDDEN', 403);
    }

    public function testResidentCannotDeleteAnimal(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal();
        $this->assertError(
            $this->delete('/api/v1/animals/' . $animal['id'], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testAdminCanCreateNotListedAnimal(): void
    {
        $admin = $this->seedAdmin();
        $data = $this->assertOk($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'male',
            'name' => 'Mango',
        ], $this->actingAs($admin)), 201);
        $this->assertSame('not_listed', $data['animal']['adoption_status']);
    }

    public function testCreateAvailableWithoutVaccinesIsRejected(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'male',
            'adoption_status' => 'available',
        ], $this->actingAs($admin)), 'NOT_HEALTH_READY', 409);
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/animals', [], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsInvalidSpecies(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/animals', [
            'species' => 'bird',
            'breed_type' => 'aspin',
            'sex' => 'male',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsNumericName(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'male',
            'name' => 12,
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsInvalidCaseId(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'male',
            'case_id' => 'case-1',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testPatchAvailableWithoutVaccinesIsRejected(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->patch(
            '/api/v1/animals/' . $animal['id'],
            ['adoption_status' => 'available'],
            $this->actingAs($admin)
        ), 'NOT_HEALTH_READY', 409);
    }

    public function testPatchRejectsInvalidAdoptionStatus(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->patch(
            '/api/v1/animals/' . $animal['id'],
            ['adoption_status' => 'sold'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRejectsInvalidSpecies(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->patch(
            '/api/v1/animals/' . $animal['id'],
            ['species' => 'bird'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRejectsInvalidUuidCaseId(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->patch(
            '/api/v1/animals/' . $animal['id'],
            ['case_id' => 'nope'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRejectsEmptyBody(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError(
            $this->patch('/api/v1/animals/' . $animal['id'], [], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testPatchUnknownAnimalIsNotFound(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->patch('/api/v1/animals/' . $this->uuid(), ['name' => 'X'], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testAdminCanRenameAnimal(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $data = $this->assertOk($this->patch(
            '/api/v1/animals/' . $animal['id'],
            ['name' => 'Coco'],
            $this->actingAs($admin)
        ));
        $this->assertSame('Coco', $data['animal']['name']);
    }

    public function testAdminCanDeleteAnimal(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertOk($this->delete('/api/v1/animals/' . $animal['id'], $this->actingAs($admin)));
        $this->assertError(
            $this->get('/api/v1/animals/' . $animal['id'], [], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testDeleteUnknownAnimalIsNotFound(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->delete('/api/v1/animals/' . $this->uuid(), $this->actingAs($admin)), 'NOT_FOUND', 404);
    }

    public function testResidentCannotReadMedicalWrite(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal();
        $this->assertError($this->put(
            '/api/v1/animals/' . $animal['id'] . '/medical',
            ['weight_kg' => 10],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testResidentCannotReadMedical(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedEligibleAnimal(['adoption_status' => 'available']);
        $this->assertError(
            $this->get('/api/v1/animals/' . $animal['id'] . '/medical', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testMedicalShowUnknownAnimal(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/animals/' . $this->uuid() . '/medical', [], $this->actingAs($resident)),
            'NOT_FOUND',
            404
        );
    }

    public function testMedicalUpsertRejectsHotTemperature(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->put(
            '/api/v1/animals/' . $animal['id'] . '/medical',
            ['temperature_c' => 'hot'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testMedicalUpsertRejectsNonNumericWeight(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->put(
            '/api/v1/animals/' . $animal['id'] . '/medical',
            ['weight_kg' => 'heavy'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testMedicalUpsertRejectsBadVaccinationStatus(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->put(
            '/api/v1/animals/' . $animal['id'] . '/medical',
            ['vaccination_status' => 'maybe'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testMedicalUpsertRejectsBadNeutered(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->put(
            '/api/v1/animals/' . $animal['id'] . '/medical',
            ['neutered' => 'sort-of'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testMedicalUpsertRejectsEmptyBody(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError(
            $this->put('/api/v1/animals/' . $animal['id'] . '/medical', [], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testMedicalUpsertHappyPath(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $data = $this->assertOk($this->put(
            '/api/v1/animals/' . $animal['id'] . '/medical',
            ['weight_kg' => 12.5, 'temperature_c' => 38.2, 'vaccination_status' => 'partial'],
            $this->actingAs($admin)
        ));
        $this->assertSame('partial', $data['medical']['vaccination_status']);
    }

    public function testDeviceVitalsWrongKey(): void
    {
        $animal = $this->seedAnimal();
        $this->assertError($this->post('/api/v1/vitals', [
            'animal_id' => $animal['id'],
            'heart_rate_bpm' => 80,
        ], null, ['X-Device-Key' => 'nope']), 'UNAUTHENTICATED', 401);
    }

    public function testDeviceVitalsRejectsJwtInsteadOfKey(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->post(
            '/api/v1/vitals',
            ['animal_id' => $animal['id'], 'heart_rate_bpm' => 80],
            $this->actingAs($admin)
        ), 'UNAUTHENTICATED', 401);
    }

    public function testDeviceVitalsHappyPath(): void
    {
        $animal = $this->seedAnimal();
        $data = $this->assertOk($this->post(
            '/api/v1/vitals',
            ['animal_id' => $animal['id'], 'heart_rate_bpm' => 80],
            null,
            ['X-Device-Key' => 'test-device-key']
        ), 201);
        $this->assertSame(80, (int) $data['vital']['heart_rate_bpm']);
    }

    public function testDeviceVitalsRejectsInvalidAnimalId(): void
    {
        $this->assertError($this->post(
            '/api/v1/vitals',
            ['animal_id' => 'animal-1', 'heart_rate_bpm' => 80],
            null,
            ['X-Device-Key' => 'test-device-key']
        ), 'VALIDATION_ERROR', 400);
    }

    public function testDeviceVitalsMissingAnimal(): void
    {
        $this->assertError($this->post(
            '/api/v1/vitals',
            ['animal_id' => $this->uuid(), 'heart_rate_bpm' => 80],
            null,
            ['X-Device-Key' => 'test-device-key']
        ), 'NOT_FOUND', 404);
    }

    public function testManualVitalsRejectsNegativeHeartRate(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->post(
            '/api/v1/animals/' . $animal['id'] . '/vitals',
            ['heart_rate_bpm' => -5],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testManualVitalsRejectsNonNumericRespiratoryRate(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->post(
            '/api/v1/animals/' . $animal['id'] . '/vitals',
            ['heart_rate_bpm' => 80, 'respiratory_rate_bpm' => 'fast'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testManualVitalsHappyPath(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $data = $this->assertOk($this->post(
            '/api/v1/animals/' . $animal['id'] . '/vitals',
            ['heart_rate_bpm' => 90, 'respiratory_rate_bpm' => 20],
            $this->actingAs($admin)
        ), 201);
        $this->assertSame(90, (int) $data['vital']['heart_rate_bpm']);
    }

    public function testResidentCannotPostManualVitals(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal();
        $this->assertError($this->post(
            '/api/v1/animals/' . $animal['id'] . '/vitals',
            ['heart_rate_bpm' => 90],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testListVitalsRejectsBadPerPage(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError(
            $this->get('/api/v1/animals/' . $animal['id'] . '/vitals', ['per_page' => '101'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListVitalsUnknownAnimal(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/animals/' . $this->uuid() . '/vitals', [], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testResidentCannotUploadDocument(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal();
        $this->assertError(
            $this->post('/api/v1/animals/' . $animal['id'] . '/documents', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testDocumentUploadRequiresFile(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError(
            $this->post('/api/v1/animals/' . $animal['id'] . '/documents', [], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testDocumentUnknownAnimal(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->post('/api/v1/animals/' . $this->uuid() . '/documents', [], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testTwentyMegabyteRenamedExeIsRejected(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($tmp, "MZ\x90\x00" . str_repeat('A', 100));
        $err = AnimalAssetUpload::validate([
            'name' => 'record.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => 20 * 1024 * 1024,
        ], [
            'pdf' => ['application/pdf', 'application/x-pdf'],
            'jpg' => ['image/jpeg', 'image/jpg'],
        ], 10 * 1024 * 1024, false);
        @unlink($tmp);
        $this->assertNotNull($err);
        $this->assertStringContainsString('10 MB', $err);
    }

    public function testExeSniffedAsPdfNameIsRejected(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($tmp, "MZ\x90\x00fake");
        $err = AnimalAssetUpload::validate([
            'name' => 'record.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ], [
            'pdf' => ['application/pdf', 'application/x-pdf'],
        ], 10 * 1024 * 1024, false);
        @unlink($tmp);
        $this->assertNotNull($err);
        $this->assertStringContainsString('Unsupported file type', $err);
    }

    public function testFieldStatusRequiresPermission(): void
    {
        $resident = $this->seedResident();
        $animal = $this->seedAnimal();
        $this->assertError($this->post('/api/v1/animals/' . $animal['id'] . '/field-status', [
            'rescue_status' => 'rescued',
            'health_status' => 'healthy',
        ], $this->actingAs($resident)), 'FORBIDDEN', 403);
    }

    public function testFieldStatusRejectsBadEnum(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->post('/api/v1/animals/' . $animal['id'] . '/field-status', [
            'rescue_status' => 'maybe',
            'health_status' => 'healthy',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testAdminCanLogFieldStatus(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $data = $this->assertOk($this->post('/api/v1/animals/' . $animal['id'] . '/field-status', [
            'rescue_status' => 'rescued',
            'health_status' => 'healthy',
        ], $this->actingAs($admin)), 201);
        $this->assertSame('rescued', $data['field_status']['rescue_status']);
    }

    public function testHealthRecordUnknownAnimal(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/animals/' . $this->uuid() . '/health-record', [], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testHealthRecordHappyPath(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedEligibleAnimal();
        $data = $this->assertOk($this->get(
            '/api/v1/animals/' . $animal['id'] . '/health-record',
            [],
            $this->actingAs($admin)
        ));
        $this->assertSame($animal['id'], $data['record']['id']);
    }

    public function testDocumentPatchUnknown(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->patch('/api/v1/documents/' . $this->uuid(), ['name' => 'X'], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testDocumentDeleteUnknown(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->delete('/api/v1/documents/' . $this->uuid(), $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }
}
