<?php

namespace App\Services;

use App\Auth\PasswordService;
use App\Database;
use App\Entity\User;
use App\Http\DomainException;
use App\Repositories\UserRepository;
use PDO;
use PDOException;

class UserAdminException extends DomainException
{
}

class UserAdminService
{
    private const CLEANUP = [
        ['user_permissions', 'user_id'],
        ['rescuer_duty_status', 'user_id'],
        ['rescuer_approvals', 'user_id'],
        ['notifications', 'user_id'],
    ];

    private const BLOCKING = [
        ['reports', 'resident_id', 'a stray-animal report'],
        ['reports', 'verified_by', 'a report they verified'],
        ['cases', 'assigned_rescuer_id', 'an assigned case'],
        ['cases', 'assigned_by', 'a case they assigned'],
        ['case_activity_log', 'actor_id', 'case activity'],
        ['animals', 'created_by', 'an animal record'],
        ['animal_field_status', 'logged_by', 'a field-status log'],
        ['animal_medical_records', 'updated_by', 'a medical record'],
        ['animal_documents', 'uploaded_by', 'an uploaded document'],
        ['adoptions', 'applicant_id', 'an adoption application'],
        ['adoptions', 'reviewed_by', 'an adoption review'],
        ['adoption_listings', 'posted_by', 'an adoption listing'],
        ['adoption_listings', 'reviewed_by', 'a listing review'],
        ['messages', 'sender_id', 'a message they sent'],
        ['messages', 'receiver_id', 'a message they received'],
        ['elearning_modules', 'created_by', 'an e-learning module'],
        ['elearning_progress', 'resident_id', 'learning progress'],
        ['rescuer_approvals', 'reviewed_by', 'a rescuer review'],
    ];

    private PasswordService $passwords;

    public function __construct(private PDO $pdo, private UserRepository $users)
    {
        $this->passwords = new PasswordService();
    }

    public function create(array $body): User
    {
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $this->assertEmailAvailable($email);

        $role = (string) ($body['role'] ?? 'resident');
        $status = (string) ($body['account_status'] ?? 'active');

        $id = $this->users->create([
            'id' => Database::uuidV4(),
            'full_name' => trim((string) $body['full_name']),
            'email' => $email,
            'password_hash' => $this->passwords->hash((string) $body['password']),
            'auth_provider' => 'native',
            'phone_number' => $this->nullableString($body['phone_number'] ?? null),
            'address' => $this->nullableString($body['address'] ?? null),
            'role' => $role,
            'account_status' => $status,
        ]);

        $user = $this->users->find($id);
        if (!$user) {
            throw new UserAdminException('SERVER_ERROR', 'User could not be created', 500);
        }
        return $user;
    }

    public function prepareUpdate(User $user, array $data, array $body, bool $isAdmin, bool $isSelf): array
    {
        if (array_key_exists('email', $body) && ($isAdmin || $isSelf)) {
            if (!is_string($body['email']) || trim($body['email']) === '') {
                throw new UserAdminException('VALIDATION_ERROR', 'email is required', 400);
            }
            $email = strtolower(trim($body['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new UserAdminException('VALIDATION_ERROR', 'email must be a valid email', 400);
            }
            if (mb_strlen($email) > 150) {
                throw new UserAdminException('VALIDATION_ERROR', 'email must be at most 150 characters', 400);
            }
            $this->assertEmailAvailable($email, $user->id());
            $data['email'] = $email;
        }

        if (array_key_exists('password', $body) && ($isAdmin || $isSelf)) {
            if (!is_string($body['password'])) {
                throw new UserAdminException('VALIDATION_ERROR', 'password must be a string', 400);
            }
            $password = $body['password'];
            if (trim($password) !== '') {
                if (mb_strlen($password) < 8) {
                    throw new UserAdminException('VALIDATION_ERROR', 'password must be at least 8 characters', 400);
                }
                $data['password_hash'] = $this->passwords->hash($password);
            }
        }

        $this->assertPrivilegeChangeAllowed(
            $user,
            array_key_exists('role', $data) ? (string) $data['role'] : null,
            array_key_exists('account_status', $data) ? (string) $data['account_status'] : null
        );

        return $data;
    }

    public function delete(User $target, array $actor): void
    {
        $actorId = (string) ($actor['id'] ?? '');
        if ($actorId !== '' && $actorId === $target->id()) {
            throw new UserAdminException('FORBIDDEN', 'Cannot delete your own account', 403);
        }

        if ($target->role() === 'admin' && $target->accountStatus() === 'active' && $this->countActiveAdmins($target->id()) === 0) {
            throw new UserAdminException('FORBIDDEN', 'Cannot delete the last active admin', 403);
        }

        $blocking = $this->blockingLabel((string) $target->id());
        if ($blocking !== null) {
            throw new UserAdminException(
                'CONFLICT',
                'Cannot delete this user because they still have ' . $blocking . '. Suspend the account instead.',
                409
            );
        }

        $id = (string) $target->id();
        foreach (self::CLEANUP as [$table, $column]) {
            $this->deleteWhere($table, $column, $id);
        }

        if (!$this->users->delete($id)) {
            throw new UserAdminException('NOT_FOUND', 'User not found', 404);
        }
    }

    public function assertPrivilegeChangeAllowed(User $target, ?string $newRole, ?string $newStatus): void
    {
        if ($newRole === null && $newStatus === null) {
            return;
        }
        if ($this->isLastActiveAdmin($target, $newRole, $newStatus)) {
            throw new UserAdminException('FORBIDDEN', 'Cannot change the last active admin', 403);
        }
    }

    public function assertEmailAvailable(string $email, ?string $exceptId = null): void
    {
        $existing = $this->users->findByEmail($email);
        if ($existing && $existing->id() !== $exceptId) {
            throw new UserAdminException('EMAIL_TAKEN', 'Email already registered', 409);
        }
    }

    private function isLastActiveAdmin(User $target, ?string $newRole, ?string $newStatus): bool
    {
        if ($target->role() !== 'admin' || $target->accountStatus() !== 'active') {
            return false;
        }
        $role = $newRole ?? $target->role();
        $status = $newStatus ?? $target->accountStatus();
        if ($role === 'admin' && $status === 'active') {
            return false;
        }
        return $this->countActiveAdmins($target->id()) === 0;
    }

    public function countActiveAdmins(?string $exceptId = null): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND account_status = 'active'";
        $params = [];
        if ($exceptId) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function blockingLabel(string $id): ?string
    {
        foreach (self::BLOCKING as [$table, $column, $label]) {
            if ($this->existsRow($table, $column, $id)) {
                return $label;
            }
        }
        return null;
    }

    private function existsRow(string $table, string $column, string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1");
            $stmt->execute([$id]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    private function deleteWhere(string $table, string $column, string $id): void
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$id]);
        } catch (PDOException) {
            // Optional table (e.g. user_permissions) may be absent in tests.
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
