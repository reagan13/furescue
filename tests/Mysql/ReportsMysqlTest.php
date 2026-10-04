<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class ReportsMysqlTest extends MysqlTestCase
{
    public function testResidentCanCreateAndAdminCanVerify(): void
    {
        $resident = $this->seedResident();
        $created = $this->assertOk($this->post('/api/v1/reports', [
            'animal_description' => 'Injured aspin near the boulevard',
            'latitude' => (string) self::MATI_LAT,
            'longitude' => (string) self::MATI_LNG,
        ], $this->actingAs($resident)), 201);
        $this->assertSame('pending_verification', $created['report']['status']);

        $admin = $this->seedAdmin();
        $verified = $this->assertOk($this->post(
            '/api/v1/reports/' . $created['report']['id'] . '/verify',
            [],
            $this->actingAs($admin)
        ));
        $this->assertSame('verified', $verified['report']['status']);
    }

    public function testResidentCannotVerify(): void
    {
        $owner = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $this->assertError(
            $this->post('/api/v1/reports/' . $report['id'] . '/verify', [], $this->actingAs($owner)),
            'FORBIDDEN',
            403
        );
    }
}
