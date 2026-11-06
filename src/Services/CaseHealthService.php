<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Health a rescuer can see and update on the animal linked to an assigned case.
 */
class CaseHealthService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function snapshot(string $caseId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, species, breed_type, sex, age_estimate, description, photo_urls, adoption_status
             FROM animals
             WHERE case_id = ? AND deleted_at IS NULL
             ORDER BY created_at ASC
             LIMIT 1"
        );
        $stmt->execute([$caseId]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$animal) {
            return null;
        }

        $medicalStmt = $this->pdo->prepare(
            'SELECT medical_history_notes, vaccination_status, vaccination_records, vaccination_details
             FROM animal_medical_records WHERE animal_id = ? LIMIT 1'
        );
        $medicalStmt->execute([$animal['id']]);
        $medical = $medicalStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $vitalStmt = $this->pdo->prepare(
            'SELECT heart_rate_bpm, recorded_at
             FROM vitals_log
             WHERE animal_id = ? AND heart_rate_bpm IS NOT NULL
             ORDER BY recorded_at DESC
             LIMIT 1'
        );
        $vitalStmt->execute([$animal['id']]);
        $vital = $vitalStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $records = $this->decodeList($medical['vaccination_records'] ?? null);
        if ($records === []) {
            $records = $this->decodeList($medical['vaccination_details'] ?? null);
        }

        return [
            'animal' => $animal,
            'medical_history_notes' => $medical['medical_history_notes'] ?? null,
            'vaccination_status' => $medical['vaccination_status'] ?? 'none',
            'vaccinations' => $records,
            'heart_rate_bpm' => isset($vital['heart_rate_bpm']) ? (int) $vital['heart_rate_bpm'] : null,
            'heart_rate_recorded_at' => $vital['recorded_at'] ?? null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function addVaccination(string $caseId, string $userId, string $name, string $date): array
    {
        $animalId = $this->animalId($caseId);
        if ($animalId === null) {
            throw new CaseWorkflowException('NO_ANIMAL', 'This case has no animal record yet.', 422);
        }
        $entry = ['name' => $name, 'date_given' => $date, 'status' => 'given'];
        $stmt = $this->pdo->prepare(
            'SELECT id, vaccination_records, vaccination_status FROM animal_medical_records WHERE animal_id = ? LIMIT 1'
        );
        $stmt->execute([$animalId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $records = $existing ? $this->decodeList($existing['vaccination_records'] ?? null) : [];
        $records[] = $entry;
        $encoded = json_encode($records, JSON_UNESCAPED_UNICODE);
        $status = (string) ($existing['vaccination_status'] ?? 'none');
        if ($status === '' || $status === 'none') {
            $status = 'partial';
        }
        if ($existing) {
            $update = $this->pdo->prepare(
                'UPDATE animal_medical_records
                 SET vaccination_records = ?, vaccination_status = ?, updated_by = ?, updated_at = ?
                 WHERE id = ?'
            );
            $update->execute([$encoded, $status, $userId, date('Y-m-d H:i:s'), $existing['id']]);
        } else {
            $insert = $this->pdo->prepare(
                'INSERT INTO animal_medical_records
                    (id, animal_id, vaccination_records, vaccination_status, updated_by, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                Database::uuidV4(),
                $animalId,
                $encoded,
                $status,
                $userId,
                date('Y-m-d H:i:s'),
            ]);
        }

        return $this->snapshot($caseId) ?? [];
    }

    /**
     * @return array<string,mixed>
     */
    public function addHeartRate(string $caseId, string $userId, int $bpm): array
    {
        $animalId = $this->animalId($caseId);
        if ($animalId === null) {
            throw new CaseWorkflowException('NO_ANIMAL', 'This case has no animal record yet.', 422);
        }
        $insert = $this->pdo->prepare(
            'INSERT INTO vitals_log (id, animal_id, heart_rate_bpm, source, recorded_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([
            Database::uuidV4(),
            $animalId,
            $bpm,
            'manual',
            date('Y-m-d H:i:s'),
        ]);
        unset($userId);

        return $this->snapshot($caseId) ?? [];
    }

    private function animalId(string $caseId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM animals WHERE case_id = ? AND deleted_at IS NULL ORDER BY created_at ASC LIMIT 1'
        );
        $stmt->execute([$caseId]);
        $id = $stmt->fetchColumn();

        return $id !== false && $id !== null ? (string) $id : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_array'));
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }
}
