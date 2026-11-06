<?php

namespace App\Tests\Auth;

use App\Auth\Permissions;
use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    public function testAdminResolvesAllPermissions(): void
    {
        $result = Permissions::resolve('admin');

        $this->assertNotEmpty($result);
        $this->assertContains('animals.read', $result);
        $this->assertContains('users.approve_rescuers', $result);
        $this->assertContains('users.create', $result);
        $this->assertContains('users.write', $result);
        $this->assertContains('users.delete', $result);
        $this->assertContains('vitals.ingest', $result);
        $this->assertNotContains('users.toggle_duty', $result);
    }

    public function testRescuerResolvesDefaults(): void
    {
        $result = Permissions::resolve('rescuer');

        $this->assertContains('cases.read', $result);
        $this->assertContains('cases.status_change', $result);
        $this->assertContains('animals.read', $result);
        $this->assertNotContains('reports.read', $result);
        $this->assertContains('animals.vitals.read', $result);
        $this->assertContains('notifications.read', $result);
        $this->assertContains('elearning.read', $result);
        $this->assertContains('messages.send', $result);
        $this->assertContains('messages.read', $result);
        $this->assertContains('messages.mark_read', $result);
        $this->assertContains('users.update_self', $result);
        $this->assertNotContains('vitals.read', $result);
        $this->assertNotContains('vitals.write', $result);
        $this->assertNotContains('users.approve_rescuers', $result);
        $this->assertNotContains('users.toggle_duty', $result);
    }

    public function testResidentResolvesDefaults(): void
    {
        $result = Permissions::resolve('resident');

        $this->assertContains('reports.create', $result);
        $this->assertContains('reports.read_own', $result);
        $this->assertContains('animals.read', $result);
        $this->assertContains('notifications.read', $result);
        $this->assertContains('adoptions.apply', $result);
        $this->assertContains('adoptions.listings.create', $result);
        $this->assertContains('messages.read', $result);
        $this->assertContains('messages.send', $result);
        $this->assertContains('messages.mark_read', $result);
        $this->assertContains('users.update_self', $result);
        $this->assertNotContains('reports.read', $result);
        $this->assertNotContains('users.approve_rescuers', $result);
        $this->assertNotContains('adoptions.listings.approve', $result);
    }

    public function testExtrasMergeAndDeduplicate(): void
    {
        $result = Permissions::resolve('resident', ['reports.read', 'reports.read', 'custom.perm']);

        $this->assertContains('reports.read', $result);
        $this->assertContains('custom.perm', $result);
        $this->assertCount(1, array_filter($result, fn($p) => $p === 'reports.read'));
    }

    public function testHasReturnsTrueWhenPresent(): void
    {
        $this->assertTrue(Permissions::has(['reports.read', 'animals.read'], 'reports.read'));
    }

    public function testHasReturnsFalseWhenMissing(): void
    {
        $this->assertFalse(Permissions::has(['reports.read'], 'users.approve_rescuers'));
    }

    public function testUnknownRoleReturnsEmptyDefaults(): void
    {
        $result = Permissions::resolve('unknown');

        $this->assertSame([], $result);
    }
}
