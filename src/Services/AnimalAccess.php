<?php

namespace App\Services;

use PDO;

/**
 * Residents see the adoption gallery and their own animals.
 * Medical notes stay with admin, and with the rescuer assigned to that case.
 */
class AnimalAccess
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{id?:string,role?:string} $user
     * @param list<string> $permissions
     * @param array<string,mixed> $animal
     */
    public function canSee(array $user, array $permissions, array $animal): bool
    {
        if (in_array('animals.write', $permissions, true) || ($user['role'] ?? '') === 'admin') {
            return true;
        }
        $status = (string) ($animal['adoption_status'] ?? '');
        if (in_array($status, ['available', 'pending', 'adopted'], true)) {
            return true;
        }
        $userId = (string) ($user['id'] ?? '');
        if ($userId !== '' && $userId === (string) ($animal['created_by'] ?? '')) {
            return true;
        }

        return ($user['role'] ?? '') === 'rescuer'
            && $this->isAssignedRescuer($userId, (string) ($animal['id'] ?? ''), $animal['case_id'] ?? null);
    }

    /**
     * @param array{id?:string,role?:string} $user
     * @param list<string> $permissions
     */
    public function canReadMedical(array $user, array $permissions, string $animalId, mixed $caseId = null): bool
    {
        if (in_array('animals.medical.read', $permissions, true) || in_array('animals.medical.write', $permissions, true)) {
            return true;
        }
        if (($user['role'] ?? '') !== 'rescuer') {
            return false;
        }

        return $this->isAssignedRescuer((string) ($user['id'] ?? ''), $animalId, $caseId);
    }

    /**
     * @param array{id?:string,role?:string} $user
     * @param list<string> $permissions
     */
    public function canWriteVitals(array $user, array $permissions, string $animalId, mixed $caseId = null): bool
    {
        if (in_array('animals.medical.write', $permissions, true) || in_array('animals.write', $permissions, true)) {
            return true;
        }
        if (!in_array('animals.vitals.read', $permissions, true)) {
            return false;
        }

        return $this->isAssignedRescuer((string) ($user['id'] ?? ''), $animalId, $caseId);
    }

    public function isAssignedRescuer(string $userId, string $animalId, mixed $caseId = null): bool
    {
        if ($userId === '') {
            return false;
        }
        $caseId = is_string($caseId) ? $caseId : '';
        if ($caseId !== '') {
            $stmt = $this->pdo->prepare('SELECT 1 FROM cases WHERE id = ? AND assigned_rescuer_id = ? LIMIT 1');
            $stmt->execute([$caseId, $userId]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }
        if ($animalId === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM animals a
             JOIN cases c ON c.id = a.case_id
             WHERE a.id = ? AND c.assigned_rescuer_id = ?
             LIMIT 1'
        );
        $stmt->execute([$animalId, $userId]);

        return (bool) $stmt->fetchColumn();
    }
}
