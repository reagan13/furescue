<?php

namespace App\Tests\Support;

use App\Auth\GoogleAuthService;
use App\Auth\JwtService;
use App\Auth\PasswordService;
use App\Database;
use App\Http\RouteLoader;
use App\Http\Router;
use App\Middleware\AuthMiddleware;
use App\Services\DedupService;
use App\Services\GeoService;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    use InteractsWithHttp;

    protected const MATI_LAT = 6.9554;
    protected const MATI_LNG = 126.2131;
    protected const PASSWORD = 'Password123!';

    protected PDO $pdo;
    protected Router $router;
    protected JwtService $jwt;
    protected PasswordService $passwords;

    private static ?string $passwordHash = null;

    protected function setUp(): void
    {
        $this->applyJwtTestEnv();

        $this->pdo = $this->createPdo();
        $this->jwt = new JwtService();
        $this->passwords = new PasswordService();

        $this->router = new Router();
        RouteLoader::register($this->router, [
            'pdo' => $this->pdo,
            'jwt' => $this->jwt,
            'password' => $this->passwords,
            'google' => new GoogleAuthService(),
            'dedup' => $this->makeDedup(),
            'geo' => new GeoService(),
            'authMw' => new AuthMiddleware($this->pdo, $this->jwt),
        ]);
    }

    protected function applyJwtTestEnv(): void
    {
        $_ENV['JWT_SECRET'] = $_ENV['JWT_SECRET'] ?? 'api_test_secret';
        $_ENV['JWT_REFRESH_SECRET'] = $_ENV['JWT_REFRESH_SECRET'] ?? 'api_test_refresh_secret';
        $_ENV['JWT_ALGO'] = $_ENV['JWT_ALGO'] ?? 'HS256';
        $_ENV['JWT_TTL_MINUTES'] = $_ENV['JWT_TTL_MINUTES'] ?? '60';
        $_ENV['JWT_REFRESH_TTL_DAYS'] = $_ENV['JWT_REFRESH_TTL_DAYS'] ?? '7';
        $_ENV['GOOGLE_CLIENT_ID'] = $_ENV['GOOGLE_CLIENT_ID'] ?? 'api-test-client-id';
        $_ENV['DEVICE_API_KEY'] = $_ENV['DEVICE_API_KEY'] ?? 'test-device-key';
    }

    protected function createPdo(): PDO
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite driver is not available.');
        }

        SqliteTestDatabase::env();
        $_ENV['JWT_SECRET'] = 'api_test_secret';
        $_ENV['JWT_REFRESH_SECRET'] = 'api_test_refresh_secret';
        $_ENV['JWT_ALGO'] = 'HS256';
        $_ENV['JWT_TTL_MINUTES'] = '60';
        $_ENV['JWT_REFRESH_TTL_DAYS'] = '7';
        $_ENV['GOOGLE_CLIENT_ID'] = 'api-test-client-id';
        $_ENV['DEVICE_API_KEY'] = 'test-device-key';

        return SqliteTestDatabase::create();
    }

    protected function makeDedup(): DedupService
    {
        return new TestDedupService($this->pdo);
    }

    protected function get(string $path, array $query = [], ?string $token = null): array
    {
        return $this->call('GET', $path, [], $query, $token);
    }

    protected function post(string $path, array $body = [], ?string $token = null, array $headers = []): array
    {
        return $this->call('POST', $path, $body, [], $token, $headers);
    }

    protected function patch(string $path, array $body = [], ?string $token = null): array
    {
        return $this->call('PATCH', $path, $body, [], $token);
    }

    protected function put(string $path, array $body = [], ?string $token = null): array
    {
        return $this->call('PUT', $path, $body, [], $token);
    }

    protected function delete(string $path, ?string $token = null): array
    {
        return $this->call('DELETE', $path, [], [], $token);
    }

    protected function call(
        string $method,
        string $path,
        array $body = [],
        array $query = [],
        ?string $token = null,
        array $headers = []
    ): array {
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $request = $this->makeRequest($method, $path, $body, $query, $headers);
        return $this->observe(fn () => $this->router->dispatch($request));
    }

    protected function postInvalidJson(string $path, ?string $token = null): array
    {
        $headers = $token !== null ? ['Authorization' => 'Bearer ' . $token] : [];
        $request = $this->makeRequest('POST', $path, [], [], $headers);
        $request->invalidJson = true;
        return $this->observe(fn () => $this->router->dispatch($request));
    }

    protected function actingAs(array $user): string
    {
        return $this->jwt->issueAccessToken($user);
    }

    protected function expiredToken(array $user): string
    {
        $previous = $_ENV['JWT_TTL_MINUTES'] ?? '60';
        $_ENV['JWT_TTL_MINUTES'] = '-1';
        $jwt = new JwtService();
        $token = $jwt->issueAccessToken($user);
        $_ENV['JWT_TTL_MINUTES'] = $previous;
        return $token;
    }

    protected function assertOk(array $response, int $status = 200): array
    {
        $this->assertSame($status, $response['status'], $response['raw'] ?: 'empty body');
        $this->assertTrue($response['body']['success'] ?? false, $response['raw'] ?: 'missing success');
        $this->assertArrayHasKey('data', $response['body']);
        return $response['body']['data'] ?? [];
    }

    protected function assertError(array $response, string $code, int $status): void
    {
        $this->assertSame($status, $response['status'], $response['raw'] ?: 'empty body');
        $this->assertFalse($response['body']['success'] ?? true, $response['raw'] ?: 'expected failure');
        $this->assertSame($code, $response['body']['error']['code'] ?? null, $response['raw'] ?: 'missing error');
    }

    protected function passwordHash(): string
    {
        return self::$passwordHash ??= $this->passwords->hash(self::PASSWORD);
    }

    protected function seedUser(string $role, array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $email = $overrides['email'] ?? ($role . '-' . substr($id, 0, 8) . '@test.local');
        $row = [
            'id' => $id,
            'full_name' => $overrides['full_name'] ?? ucfirst($role) . ' User',
            'email' => $email,
            'password_hash' => $overrides['password_hash'] ?? $this->passwordHash(),
            'auth_provider' => 'native',
            'phone_number' => $overrides['phone_number'] ?? null,
            'address' => $overrides['address'] ?? null,
            'role' => $role,
            'account_status' => $overrides['account_status'] ?? 'active',
            'profile_photo_url' => $overrides['profile_photo_url'] ?? null,
        ];
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, full_name, email, password_hash, auth_provider, phone_number, address, role, account_status, profile_photo_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $row['id'], $row['full_name'], $row['email'], $row['password_hash'], $row['auth_provider'],
            $row['phone_number'], $row['address'], $row['role'], $row['account_status'], $row['profile_photo_url'],
        ]);
        return $row;
    }

    protected function seedResident(array $overrides = []): array
    {
        return $this->seedUser('resident', $overrides);
    }

    protected function seedRescuer(array $overrides = []): array
    {
        $user = $this->seedUser('rescuer', $overrides);
        if (!empty($overrides['on_duty'])) {
            $this->pdo->prepare(
                'INSERT INTO rescuer_duty_status (id, user_id, status) VALUES (?, ?, ?)'
            )->execute([Database::uuidV4(), $user['id'], 'on_duty']);
        }
        return $user;
    }

    protected function seedAdmin(array $overrides = []): array
    {
        return $this->seedUser('admin', $overrides);
    }

    protected function seedAnimal(array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $row = [
            'id' => $id,
            'name' => $overrides['name'] ?? 'Brownie',
            'species' => $overrides['species'] ?? 'dog',
            'breed_type' => $overrides['breed_type'] ?? 'aspin',
            'sex' => $overrides['sex'] ?? 'male',
            'adoption_status' => $overrides['adoption_status'] ?? 'not_listed',
            'source' => $overrides['source'] ?? 'rescued_case',
            'created_by' => $overrides['created_by'] ?? null,
            'case_id' => $overrides['case_id'] ?? null,
            'description' => $overrides['description'] ?? null,
        ];
        $this->pdo->prepare(
            "INSERT INTO animals (id, name, species, breed_type, sex, adoption_status, source, created_by, case_id, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $row['id'], $row['name'], $row['species'], $row['breed_type'], $row['sex'],
            $row['adoption_status'], $row['source'], $row['created_by'], $row['case_id'], $row['description'],
        ]);
        return $row;
    }

    protected function seedEligibleAnimal(array $overrides = []): array
    {
        $animal = $this->seedAnimal($overrides);
        $this->pdo->prepare(
            "INSERT INTO animal_medical_records (id, animal_id, vaccination_details, weight_kg)
             VALUES (?, ?, ?, ?)"
        )->execute([
            Database::uuidV4(),
            $animal['id'],
            json_encode([['name' => 'Rabies']]),
            '12.5',
        ]);
        return $animal;
    }

    protected function seedReport(array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $lat = $overrides['latitude'] ?? self::MATI_LAT;
        $lng = $overrides['longitude'] ?? self::MATI_LNG;
        $desc = $overrides['animal_description'] ?? 'Injured dog near boulevard';
        $row = [
            'id' => $id,
            'resident_id' => $overrides['resident_id'] ?? Database::uuidV4(),
            'animal_description' => $desc,
            'latitude' => $lat,
            'longitude' => $lng,
            'content_hash' => DedupService::contentHash($desc, (float) $lat, (float) $lng),
            'validation_status' => $overrides['validation_status'] ?? 'validated',
            'status' => $overrides['status'] ?? 'pending_verification',
            'address_text' => $overrides['address_text'] ?? null,
            'photo_urls' => is_array($overrides['photo_urls'] ?? null)
                ? json_encode($overrides['photo_urls'], JSON_UNESCAPED_SLASHES)
                : ($overrides['photo_urls'] ?? null),
        ];
        $this->pdo->prepare(
            "INSERT INTO reports (id, resident_id, animal_description, latitude, longitude, address_text, photo_urls, content_hash, validation_status, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $row['id'], $row['resident_id'], $row['animal_description'], $row['latitude'], $row['longitude'],
            $row['address_text'], $row['photo_urls'], $row['content_hash'], $row['validation_status'], $row['status'],
        ]);
        return $row;
    }

    protected function seedCase(array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $row = [
            'id' => $id,
            'report_id' => $overrides['report_id'] ?? $this->seedReport()['id'],
            'assigned_rescuer_id' => $overrides['assigned_rescuer_id'] ?? null,
            'assigned_by' => $overrides['assigned_by'] ?? null,
            'status' => $overrides['status'] ?? 'open',
            'resolution_photos' => $overrides['resolution_photos'] ?? null,
        ];
        $this->pdo->prepare(
            "INSERT INTO cases (id, report_id, assigned_rescuer_id, assigned_by, status, resolution_photos)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $row['id'], $row['report_id'], $row['assigned_rescuer_id'], $row['assigned_by'],
            $row['status'], $row['resolution_photos'],
        ]);
        return $row;
    }

    protected function seedAdoption(array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $row = [
            'id' => $id,
            'animal_id' => $overrides['animal_id'] ?? $this->seedAnimal(['adoption_status' => 'available'])['id'],
            'applicant_id' => $overrides['applicant_id'] ?? Database::uuidV4(),
            'status' => $overrides['status'] ?? 'pending',
            'message' => $overrides['message'] ?? null,
        ];
        $this->pdo->prepare(
            "INSERT INTO adoptions (id, animal_id, applicant_id, message, status) VALUES (?, ?, ?, ?, ?)"
        )->execute([$row['id'], $row['animal_id'], $row['applicant_id'], $row['message'], $row['status']]);
        return $row;
    }

    protected function seedListing(array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $row = [
            'id' => $id,
            'animal_id' => $overrides['animal_id'] ?? $this->seedAnimal()['id'],
            'posted_by' => $overrides['posted_by'] ?? Database::uuidV4(),
            'status' => $overrides['status'] ?? 'pending_review',
        ];
        $this->pdo->prepare(
            "INSERT INTO adoption_listings (id, animal_id, posted_by, status) VALUES (?, ?, ?, ?)"
        )->execute([$row['id'], $row['animal_id'], $row['posted_by'], $row['status']]);
        return $row;
    }

    protected function seedModule(array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $row = [
            'id' => $id,
            'title' => $overrides['title'] ?? 'Street dog basics',
            'category' => $overrides['category'] ?? 'general_care',
            'content_body' => $overrides['content_body'] ?? 'Feed, water, and call a rescuer.',
            'quiz_json' => $overrides['quiz_json'] ?? null,
            'published_status' => $overrides['published_status'] ?? 'published',
            'created_by' => $overrides['created_by'] ?? Database::uuidV4(),
        ];
        $this->pdo->prepare(
            "INSERT INTO elearning_modules (id, title, category, content_body, quiz_json, published_status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $row['id'], $row['title'], $row['category'], $row['content_body'], $row['quiz_json'],
            $row['published_status'], $row['created_by'],
        ]);
        return $row;
    }

    protected function seedNotification(array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $row = [
            'id' => $id,
            'user_id' => $overrides['user_id'] ?? Database::uuidV4(),
            'type' => $overrides['type'] ?? 'admin_announcement',
            'message' => $overrides['message'] ?? 'Hello',
            'related_type' => $overrides['related_type'] ?? null,
            'related_id' => $overrides['related_id'] ?? null,
            'is_read' => $overrides['is_read'] ?? 0,
        ];
        $this->pdo->prepare(
            "INSERT INTO notifications (id, user_id, type, message, related_type, related_id, is_read)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $row['id'], $row['user_id'], $row['type'], $row['message'],
            $row['related_type'], $row['related_id'], $row['is_read'],
        ]);
        return $row;
    }

    protected function uuid(): string
    {
        return Database::uuidV4();
    }
}
