<?php

namespace App\Tests\Api;

use App\Services\AnimalAssetUpload;
use App\Tests\Support\ApiTestCase;

class ReportsApiTest extends ApiTestCase
{
    private function reportBody(array $overrides = []): array
    {
        return array_merge([
            'animal_description' => 'Injured dog near boulevard',
            'latitude' => (string) self::MATI_LAT,
            'longitude' => (string) self::MATI_LNG,
        ], $overrides);
    }

    public function testResidentCanFileAReportInsideMati(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk(
            $this->post('/api/v1/reports', $this->reportBody(), $this->actingAs($resident)),
            201
        );
        $this->assertSame('validated', $data['report']['validation_status']);
    }

    public function testPinOutsideMatiIsRejected(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/reports', $this->reportBody([
            'latitude' => '14.5995',
            'longitude' => '120.9842',
        ]), $this->actingAs($resident)), 'OUT_OF_BOUNDS', 422);
    }

    public function testPinOnCityBoundaryIsAccepted(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->post('/api/v1/reports', $this->reportBody([
            'latitude' => '6.89',
            'longitude' => '126.13',
        ]), $this->actingAs($resident)), 201);
        $this->assertSame('validated', $data['report']['validation_status']);
    }

    public function testDuplicateDescriptionAndSpotIsFlagged(): void
    {
        $resident = $this->seedResident();
        $token = $this->actingAs($resident);
        $first = $this->assertOk($this->post('/api/v1/reports', $this->reportBody(), $token), 201);
        $second = $this->assertOk($this->post('/api/v1/reports', $this->reportBody(), $token), 201);
        $this->assertSame('flagged_duplicate', $second['report']['validation_status']);
        $this->assertSame($first['report']['id'], $second['report']['duplicate_of_report_id']);
    }

    public function testSecondResidentCannotOpenFirstResidentsReport(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $this->assertError(
            $this->get('/api/v1/reports/' . $report['id'], [], $this->actingAs($other)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCanOpenOwnReport(): void
    {
        $owner = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $data = $this->assertOk($this->get('/api/v1/reports/' . $report['id'], [], $this->actingAs($owner)));
        $this->assertSame($report['id'], $data['report']['id']);
    }

    public function testShowReportNotFound(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/reports/' . $this->uuid(), [], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testResidentCannotVerifyOwnReport(): void
    {
        $resident = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $resident['id']]);
        $this->assertError(
            $this->post('/api/v1/reports/' . $report['id'] . '/verify', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCannotDismissOwnReport(): void
    {
        $resident = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $resident['id']]);
        $this->assertError($this->post(
            '/api/v1/reports/' . $report['id'] . '/dismiss',
            ['dismiss_reason' => 'not real'],
            $this->actingAs($resident)
        ), 'FORBIDDEN', 403);
    }

    public function testRescuerCannotFileAReport(): void
    {
        $rescuer = $this->seedRescuer();
        $this->assertError(
            $this->post('/api/v1/reports', $this->reportBody(), $this->actingAs($rescuer)),
            'FORBIDDEN',
            403
        );
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/reports', [], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsWhitespaceDescription(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/reports', $this->reportBody([
            'animal_description' => '   ',
        ]), $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsNumericDescription(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/reports', $this->reportBody([
            'animal_description' => 12,
        ]), $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsOverMaxDescription(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/reports', $this->reportBody([
            'animal_description' => str_repeat('x', 2001),
        ]), $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsNonNumericLatitude(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/reports', $this->reportBody([
            'latitude' => 'abc',
        ]), $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsPhotoUrlObjects(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/reports', $this->reportBody([
            'photo_urls' => ['url' => '/x.jpg'],
        ]), $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsPhotoUrlNumbers(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/reports', $this->reportBody([
            'photo_urls' => [1, 2],
        ]), $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateAcceptsPhotoUrlArray(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->post('/api/v1/reports', $this->reportBody([
            'photo_urls' => ['/uploads/a.jpg'],
        ]), $this->actingAs($resident)), 201);
        $this->assertNotEmpty($data['report']['id']);
    }

    public function testAdminCanVerifyAndCreatesCase(): void
    {
        $admin = $this->seedAdmin();
        $report = $this->seedReport();
        $data = $this->assertOk($this->post('/api/v1/reports/' . $report['id'] . '/verify', [], $this->actingAs($admin)));
        $this->assertSame('verified', $data['report']['status']);
        $this->assertNotEmpty($data['case_id']);
    }

    public function testAdminDismissRequiresReason(): void
    {
        $admin = $this->seedAdmin();
        $report = $this->seedReport();
        $this->assertError(
            $this->post('/api/v1/reports/' . $report['id'] . '/dismiss', [], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testAdminCanDismissWithReason(): void
    {
        $admin = $this->seedAdmin();
        $report = $this->seedReport();
        $data = $this->assertOk($this->post(
            '/api/v1/reports/' . $report['id'] . '/dismiss',
            ['dismiss_reason' => 'Duplicate sighting'],
            $this->actingAs($admin)
        ));
        $this->assertSame('dismissed', $data['report']['status']);
    }

    public function testListMineReturnsOnlyOwnReports(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $this->seedReport(['resident_id' => $owner['id']]);
        $this->seedReport(['resident_id' => $other['id']]);
        $response = $this->get('/api/v1/reports/me', [], $this->actingAs($owner));
        $this->assertOk($response);
        $this->assertSame(1, $response['body']['meta']['total']);
    }

    public function testListReportsRejectsInvalidStatus(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/reports', ['status' => 'closed'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListReportsRejectsBadPerPage(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/reports', ['per_page' => '-1'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testAdminCanListAllReports(): void
    {
        $admin = $this->seedAdmin();
        $this->seedReport();
        $response = $this->get('/api/v1/reports', [], $this->actingAs($admin));
        $this->assertOk($response);
        $this->assertGreaterThanOrEqual(1, $response['body']['meta']['total']);
    }

    public function testMediaUploadRequiresAFile(): void
    {
        $resident = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $resident['id']]);
        $this->assertError(
            $this->post('/api/v1/reports/' . $report['id'] . '/media', [], $this->actingAs($resident)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testMediaUploadForbiddenForOtherResident(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $this->assertError(
            $this->post('/api/v1/reports/' . $report['id'] . '/media', [], $this->actingAs($other)),
            'FORBIDDEN',
            403
        );
    }

    public function testFakeTypeMediaFileIsRejected(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($tmp, "MZ\x90\x00fake-exe");
        $err = AnimalAssetUpload::validate([
            'name' => 'photo.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ], [
            'jpg' => ['image/jpeg', 'image/jpg'],
            'png' => ['image/png'],
        ], 10 * 1024 * 1024, false);
        @unlink($tmp);
        $this->assertNotNull($err);
        $this->assertStringContainsString('Unsupported file type', $err);
    }

    public function testHugeMediaFileIsRejected(): void
    {
        $err = AnimalAssetUpload::validate([
            'name' => 'clip.mp4',
            'type' => 'video/mp4',
            'tmp_name' => '/tmp/unused',
            'error' => UPLOAD_ERR_OK,
            'size' => 11 * 1024 * 1024,
        ], [
            'mp4' => ['video/mp4'],
        ], 10 * 1024 * 1024, false);
        $this->assertNotNull($err);
        $this->assertStringContainsString('10 MB', $err);
    }

    public function testHeatmapRequiresAuth(): void
    {
        $this->assertError($this->get('/api/v1/reports/map/heatmap'), 'UNAUTHENTICATED', 401);
    }

    public function testResidentCannotOpenTheHeatmap(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/reports/map/heatmap', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testRescuerDoesNotReceiveEveryReport(): void
    {
        $rescuer = $this->seedRescuer();
        $resident = $this->seedResident();
        $this->seedReport(['resident_id' => $resident['id']]);
        $data = $this->assertOk($this->get('/api/v1/reports', [], $this->actingAs($rescuer)));
        $this->assertSame([], $data);
    }

    public function testReportStoresDogOrCat(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->post('/api/v1/reports', $this->reportBody([
            'species' => 'cat',
        ]), $this->actingAs($resident)), 201);
        $this->assertSame('cat', $data['report']['species']);
    }

    public function testWithdrawRemovesAnUnfinishedReport(): void
    {
        $resident = $this->seedResident();
        $created = $this->assertOk($this->post('/api/v1/reports', $this->reportBody(), $this->actingAs($resident)), 201);
        $id = $created['report']['id'];
        $this->assertOk($this->post('/api/v1/reports/' . $id . '/withdraw', [], $this->actingAs($resident)));
        $this->assertError(
            $this->get('/api/v1/reports/' . $id, [], $this->actingAs($resident)),
            'NOT_FOUND',
            404
        );
    }
}
