<?php

namespace App\Services;

use PDO;

class HealthRosterService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function list(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                a.id,
                a.name,
                a.species,
                a.breed_type,
                a.sex,
                a.age_estimate,
                a.birth_date,
                a.barangay,
                a.photo_urls,
                a.updated_at AS animal_updated_at,
                am.vaccination_status,
                am.vaccination_details,
                am.last_checkup_date,
                am.next_checkup_due,
                am.vaccination_expiry,
                am.condition,
                am.treatment_stage,
                am.weight_kg,
                am.temperature_c,
                am.vet_name,
                am.medical_history_notes,
                am.updated_at AS medical_updated_at,
                CASE WHEN am.animal_id IS NOT NULL THEN 1 ELSE 0 END AS has_medical_record,
                fs.health_status,
                v.heart_rate_bpm AS last_heart_rate
            FROM animals a
            LEFT JOIN animal_medical_records am ON am.animal_id = a.id
            LEFT JOIN (
                SELECT fs1.animal_id, fs1.health_status
                FROM animal_field_status fs1
                INNER JOIN (
                    SELECT animal_id, MAX(logged_at) AS mx
                    FROM animal_field_status
                    GROUP BY animal_id
                ) fs2 ON fs2.animal_id = fs1.animal_id AND fs2.mx = fs1.logged_at
            ) fs ON fs.animal_id = a.id
            LEFT JOIN (
                SELECT v1.animal_id, v1.heart_rate_bpm
                FROM vitals_log v1
                INNER JOIN (
                    SELECT animal_id, MAX(recorded_at) AS mx
                    FROM vitals_log
                    GROUP BY animal_id
                ) v2 ON v2.animal_id = v1.animal_id AND v2.mx = v1.recorded_at
            ) v ON v.animal_id = a.id
            ORDER BY a.created_at DESC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            $healthStatus = $r['health_status'] ?? 'healthy';
            $condition = $r['condition'];
            if ($condition === null) {
                $condition = $healthStatus === 'not_healthy' ? 'Unknown' : 'Healthy';
            }
            $treatmentStage = $r['treatment_stage'] ?? 'none';

            $vaccinationDetails = $r['vaccination_details'];
            if (is_string($vaccinationDetails) && $vaccinationDetails !== '') {
                $decoded = json_decode($vaccinationDetails, true);
                $vaccinationDetails = is_array($decoded) ? $decoded : [];
            } elseif ($vaccinationDetails === null) {
                $vaccinationDetails = [];
            }

            return [
                'id' => $r['id'],
                'animalId' => $r['id'],
                'animalName' => $r['name'] ?? 'Unnamed',
                'species' => $r['species'],
                'breedType' => $r['breed_type'],
                'sex' => $r['sex'],
                'ageEstimate' => $r['age_estimate'],
                'barangay' => $r['barangay'],
                'photo_urls' => $r['photo_urls'],
                'vaccinationStatus' => $r['vaccination_status'] ?? 'none',
                'vaccinationDetails' => $vaccinationDetails,
                'vaccinationExpiry' => $r['vaccination_expiry'],
                'lastCheckupDate' => $r['last_checkup_date'],
                'nextCheckupDue' => $r['next_checkup_due'],
                'healthStatus' => $healthStatus,
                'condition' => $condition,
                'treatmentStage' => $treatmentStage,
                'heartRateBpm' => $r['last_heart_rate'] !== null ? (int) $r['last_heart_rate'] : null,
                'weightKg' => $r['weight_kg'] !== null ? (float) $r['weight_kg'] : null,
                'temperatureC' => $r['temperature_c'] !== null ? (float) $r['temperature_c'] : null,
                'vetName' => $r['vet_name'],
                'notes' => $r['medical_history_notes'],
                'hasMedicalRecord' => !empty($r['has_medical_record']),
                'updatedAt' => $r['medical_updated_at'] ?? $r['animal_updated_at'],
            ];
        }, $rows);
    }
}
