<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class AnalyticsHealthApiTest extends ApiTestCase
{
    public function testResidentCannotOpenOverview(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/analytics/overview', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCannotExportOverview(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/analytics/overview/export', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCannotOpenAdoptionTrends(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/analytics/adoption-trends', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCannotExportAdoptionTrends(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/analytics/adoption-trends/export', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCannotOpenHealthUpdates(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/health/updates', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCannotExportHealthUpdates(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/health/updates/export', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCannotOpenHealthRecords(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/health/records', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCannotOpenHealthActivity(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/health/activity', [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testAdminCanOpenOverview(): void
    {
        $admin = $this->seedAdmin();
        $this->seedReport();
        $data = $this->assertOk($this->get('/api/v1/analytics/overview', [], $this->actingAs($admin)));
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('reports', $data['stats']);
        $this->assertArrayHasKey('rescuers_on_duty', $data['stats']);
        $this->assertArrayHasKey('rescuers_active', $data['stats']);
        $this->assertArrayHasKey('rescuers_off_duty', $data['stats']);
    }

    public function testOverviewCountsOnDutyAndActiveRescuers(): void
    {
        $admin = $this->seedAdmin();
        $this->seedRescuer(['on_duty' => true]);
        $this->seedRescuer();
        $this->seedRescuer(['account_status' => 'pending']);
        $data = $this->assertOk($this->get('/api/v1/analytics/overview', [], $this->actingAs($admin)));
        $stats = $data['stats'];
        $this->assertSame(2, $stats['rescuers_active']);
        $this->assertSame(1, $stats['rescuers_on_duty']);
        $this->assertSame(1, $stats['rescuers_off_duty']);
    }

    public function testAdminCanOpenAdoptionTrends(): void
    {
        $admin = $this->seedAdmin();
        $data = $this->assertOk($this->get('/api/v1/analytics/adoption-trends', [], $this->actingAs($admin)));
        $this->assertArrayHasKey('trends', $data);
    }

    public function testAdminCanOpenHealthUpdates(): void
    {
        $admin = $this->seedAdmin();
        $data = $this->assertOk($this->get('/api/v1/health/updates', [], $this->actingAs($admin)));
        $this->assertArrayHasKey('updates', $data);
    }

    public function testAdminCanOpenHealthRecords(): void
    {
        $admin = $this->seedAdmin();
        $this->seedEligibleAnimal();
        $data = $this->assertOk($this->get('/api/v1/health/records', [], $this->actingAs($admin)));
        $this->assertNotEmpty($data['records']);
    }

    public function testAdminCanOpenHealthActivity(): void
    {
        $admin = $this->seedAdmin();
        $data = $this->assertOk($this->get('/api/v1/health/activity', [], $this->actingAs($admin)));
        $this->assertArrayHasKey('daily', $data);
    }

    public function testOverviewRequiresAuth(): void
    {
        $this->assertError($this->get('/api/v1/analytics/overview'), 'UNAUTHENTICATED', 401);
    }

    public function testHealthRecordsRequireAuth(): void
    {
        $this->assertError($this->get('/api/v1/health/records'), 'UNAUTHENTICATED', 401);
    }

    public function testRescuerCannotOpenOverview(): void
    {
        $rescuer = $this->seedRescuer();
        $this->assertError(
            $this->get('/api/v1/analytics/overview', [], $this->actingAs($rescuer)),
            'FORBIDDEN',
            403
        );
    }

    public function testRescuerCannotOpenHealthRecords(): void
    {
        $rescuer = $this->seedRescuer();
        $this->assertError(
            $this->get('/api/v1/health/records', [], $this->actingAs($rescuer)),
            'FORBIDDEN',
            403
        );
    }

    public function testAdoptionTrendsIgnoresInvalidDates(): void
    {
        $admin = $this->seedAdmin();
        $data = $this->assertOk($this->get(
            '/api/v1/analytics/adoption-trends',
            ['start' => 'nope', 'end' => 'also-nope'],
            $this->actingAs($admin)
        ));
        $this->assertArrayHasKey('trends', $data);
    }

    public function testHealthUpdatesAcceptsValidRange(): void
    {
        $admin = $this->seedAdmin();
        $data = $this->assertOk($this->get(
            '/api/v1/health/updates',
            ['start' => '2026-01-01', 'end' => '2026-12-31'],
            $this->actingAs($admin)
        ));
        $this->assertArrayHasKey('updates', $data);
    }

    public function testHealthActivityRequiresAuth(): void
    {
        $this->assertError($this->get('/api/v1/health/activity'), 'UNAUTHENTICATED', 401);
    }

    public function testAdminCanExportOverviewCsv(): void
    {
        $admin = $this->seedAdmin();
        $this->seedReport();
        $response = $this->get('/api/v1/analytics/overview/export', [], $this->actingAs($admin));
        $this->assertSame(200, $response['status'], $response['raw'] ?: 'empty body');
        $this->assertSame('text/csv; charset=utf-8', $response['headers']['Content-Type'] ?? null);
        $this->assertStringContainsString('attachment; filename="', $response['headers']['Content-Disposition'] ?? '');
        $this->assertStringContainsString('Metric,Value,Date', $response['raw']);
        $this->assertStringContainsString('Total reports', $response['raw']);
        $this->assertStringNotContainsString('reports_monthly', $response['raw']);
        $this->assertStringNotContainsString('Array', $response['raw']);
    }
}
