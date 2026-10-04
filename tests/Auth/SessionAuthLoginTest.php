<?php

namespace App\Tests\Auth;

use App\Auth\PasswordService;
use App\Auth\SessionAuth;
use App\Database;
use App\Tests\Support\SqliteTestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
final class SessionAuthLoginTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite driver is not available.');
        }
        SqliteTestDatabase::env();
        $this->pdo = SqliteTestDatabase::create();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
    }

    public function testAttemptLoginSetsSessionKeys(): void
    {
        $user = $this->insertUser('resident', 'active');
        $authed = SessionAuth::attemptLogin($user['email'], 'Password123!', $this->pdo);
        $this->assertNotNull($authed);
        $this->assertSame('', SessionAuth::$lastError);
        $this->assertSame($user['id'], $_SESSION['user']['id']);
        $this->assertSame($user['email'], $_SESSION['user']['email']);
        $this->assertSame('resident', $_SESSION['user']['role']);
    }

    public function testAttemptLoginRejectsBadPassword(): void
    {
        $user = $this->insertUser('resident', 'active');
        $this->assertNull(SessionAuth::attemptLogin($user['email'], 'wrong-password', $this->pdo));
        $this->assertSame(SessionAuth::ERR_INVALID_CREDENTIALS, SessionAuth::$lastError);
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function testAttemptLoginRejectsPendingRescuer(): void
    {
        $user = $this->insertUser('rescuer', 'pending');
        $this->assertNull(SessionAuth::attemptLogin($user['email'], 'Password123!', $this->pdo));
        $this->assertSame(SessionAuth::ERR_ACCOUNT_PENDING, SessionAuth::$lastError);
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function testLogoutClearsSessionUser(): void
    {
        $user = $this->insertUser('admin', 'active');
        $this->assertNotNull(SessionAuth::attemptLogin($user['email'], 'Password123!', $this->pdo));
        SessionAuth::logout();
        $this->assertNull(SessionAuth::user());
    }

    /** @return array{id:string,email:string} */
    private function insertUser(string $role, string $status): array
    {
        $id = Database::uuidV4();
        $email = $role . '-' . substr($id, 0, 8) . '@session.test';
        $hash = (new PasswordService())->hash('Password123!');
        $this->pdo->prepare(
            'INSERT INTO users (id, full_name, email, password_hash, auth_provider, role, account_status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, 'Session User', $email, $hash, 'native', $role, $status]);
        return ['id' => $id, 'email' => $email];
    }
}
