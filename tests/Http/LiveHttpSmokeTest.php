<?php

namespace App\Tests\Http;

use PHPUnit\Framework\TestCase;

/**
 * @group live
 */
final class LiveHttpSmokeTest extends TestCase
{
    private const PASSWORD = 'Password123!';

    protected function setUp(): void
    {
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('curl extension is not available.');
        }
        if (!LiveHttpClient::reachable()) {
            $this->markTestSkipped('php -S is not running at ' . LiveHttpClient::baseUrl() . '. Start bin\\serve-test.bat');
        }
    }

    public function testFormLoginSetsCookieAndRedirectsResidentHome(): void
    {
        $cookie = $this->cookieFile();
        $login = LiveHttpClient::request(
            'POST',
            '/auth/login.php',
            http_build_query([
                'email' => 'juan@furescue.local',
                'password' => self::PASSWORD,
            ]),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $cookie
        );
        $this->assertSame(302, $login['status'], $login['body']);
        $this->assertStringContainsString('/reports/', LiveHttpClient::location($login));

        $home = LiveHttpClient::request('GET', '/reports/', null, [], $cookie);
        $this->assertSame(200, $home['status']);
        $this->assertStringContainsString('FurEscue', $home['body']);
    }

    public function testApiLoginReturnsEnvelope(): void
    {
        $response = LiveHttpClient::request(
            'POST',
            '/api/v1/auth/login',
            json_encode([
                'email' => 'juan@furescue.local',
                'password' => self::PASSWORD,
            ], JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json']
        );
        $this->assertSame(200, $response['status'], $response['body']);
        $payload = json_decode($response['body'], true);
        $this->assertIsArray($payload);
        $this->assertTrue($payload['success'] ?? false);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('error', $payload);
        $this->assertNull($payload['error']);
        $this->assertNotEmpty($payload['data']['tokens']['access_token'] ?? null);
    }

    public function testUnauthenticatedAdminRedirectsToLogin(): void
    {
        $response = LiveHttpClient::request('GET', '/admin/');
        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/auth/login.php', LiveHttpClient::location($response));
    }

    public function testResidentCookieBouncesOffAdmin(): void
    {
        $cookie = $this->cookieFile();
        LiveHttpClient::request(
            'POST',
            '/auth/login.php',
            http_build_query([
                'email' => 'juan@furescue.local',
                'password' => self::PASSWORD,
            ]),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $cookie
        );
        $admin = LiveHttpClient::request('GET', '/admin/', null, [], $cookie);
        $this->assertSame(302, $admin['status']);
        $this->assertStringContainsString('/reports/', LiveHttpClient::location($admin));
    }

    public function testOptionsSendsCorsHeaderForAllowListedOrigin(): void
    {
        $response = LiveHttpClient::request(
            'OPTIONS',
            '/api/v1/auth/login',
            null,
            ['Origin' => 'http://127.0.0.1:8000']
        );
        $this->assertSame(200, $response['status']);
        $this->assertSame(
            'http://127.0.0.1:8000',
            $response['headers']['access-control-allow-origin'] ?? ''
        );
    }

    public function testFormLoginRejectsPendingRescuer(): void
    {
        $login = LiveHttpClient::request(
            'POST',
            '/auth/login.php',
            http_build_query([
                'email' => 'rescuer6@furescue.local',
                'password' => self::PASSWORD,
            ]),
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        $this->assertSame(200, $login['status']);
        $this->assertStringContainsString('Account is not active.', $login['body']);
    }

    private function cookieFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'frsess');
        $this->assertNotFalse($path);
        $this->addToAssertionCount(0);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });
        return $path;
    }
}
