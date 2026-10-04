<?php

namespace App\Services;

use App\Repositories\Repository;
use PDO;

class HealthRecordService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(string $id): ?array
    {
        $animal = (new Repository($this->pdo, 'animals'))->find($id);
        if (!$animal) {
            return null;
        }

        $fsStmt = $this->pdo->prepare(
            "SELECT health_status FROM animal_field_status
             WHERE animal_id = ? ORDER BY logged_at DESC LIMIT 1"
        );
        $fsStmt->execute([$id]);
        $fsRow = $fsStmt->fetch(PDO::FETCH_ASSOC);
        $healthStatus = $fsRow['health_status'] ?? 'healthy';

        $medical = (new Repository($this->pdo, 'animal_medical_records'))->findBy('animal_id', $id) ?: [];

        $vitalsRows = (new Repository($this->pdo, 'vitals_log'))->all(['animal_id' => $id], 'recorded_at', 'DESC');
        $latestVital = $vitalsRows[0] ?? null;

        $photoUrl = null;
        if (!empty($animal['photo_urls'])) {
            $urls = $animal['photo_urls'];
            if (is_string($urls)) {
                $dec = json_decode($urls, true);
                $urls = is_array($dec) ? $dec : [];
            }
            if (is_array($urls) && count($urls) > 0) {
                $photoUrl = $urls[0];
            }
        }

        $condition = $medical['condition'] ?? null;
        if ($condition === null) {
            $condition = $healthStatus === 'not_healthy' ? 'Unknown' : 'Healthy';
        }

        $vaccinationStatus = $medical['vaccination_status'] ?? 'none';
        $vaccinationDetails = $medical['vaccination_details'] ?? null;
        if (is_string($vaccinationDetails) && $vaccinationDetails !== '') {
            $dec = json_decode($vaccinationDetails, true);
            $vaccinationDetails = is_array($dec) ? $dec : [];
        } elseif ($vaccinationDetails === null) {
            $vaccinationDetails = [];
        }

        // Prefer structured vaccination_records (admin-entered type/date/next-due/status);
        // fall back to legacy vaccination_details. No engine-based evaluation.
        $rawRecords = $medical['vaccination_records'] ?? [];
        if (is_string($rawRecords) && $rawRecords !== '') {
            $rawRecords = json_decode($rawRecords, true) ?: [];
        }
        if (!is_array($rawRecords)) {
            $rawRecords = [];
        }
        if (empty($rawRecords) && !empty($vaccinationDetails)) {
            $rawRecords = array_map(function ($v) {
                return [
                    'vaccine' => $v['vaccine'] ?? 'Vaccine',
                    'administered_date' => $v['dateGiven'] ?? ($v['date'] ?? null),
                    'next_due' => $v['nextDue'] ?? null,
                    'status' => $v['status'] ?? null,
                    'dose_number' => $v['doseNumber'] ?? null,
                    'manufacturer' => $v['manufacturer'] ?? null,
                    'product_name' => $v['productName'] ?? null,
                    'batch_number' => $v['batchNumber'] ?? null,
                    'route' => $v['route'] ?? null,
                    'notes' => $v['notes'] ?? null,
                ];
            }, $vaccinationDetails);
        }

        $vaccinations = array_map(function ($r) {
            $status = $r['status'] ?? null;
            if (!in_array($status, ['none', 'partial', 'complete', 'Completed', 'Pending', 'Overdue'], true)) {
                $status = $status ?: 'Completed';
            }
            return [
                'vaccine' => $r['vaccine'] ?? 'Vaccine',
                'dateGiven' => $r['administered_date'] ?? $r['dateGiven'] ?? null,
                'nextDue' => $r['next_due'] ?? $r['nextDue'] ?? null,
                'dueWindow' => null,
                'status' => $status,
                'doseNumber' => $r['dose_number'] ?? null,
                'flags' => [],
                'seriesComplete' => null,
                'minimumAgeWeeks' => null,
                'manufacturer' => $r['manufacturer'] ?? null,
                'productName' => $r['product_name'] ?? null,
                'batchNumber' => $r['batchNumber'] ?? null,
                'route' => $r['route'] ?? null,
                'notes' => $r['notes'] ?? null,
            ];
        }, $rawRecords);

        $today = new \DateTime();
        $reminders = [];
        $addReminder = function (string $title, $due, string $icon) use (&$reminders, $today) {
            if (!$due) {
                return;
            }
            $d = \DateTime::createFromFormat('Y-m-d', substr((string) $due, 0, 10));
            if (!$d) {
                return;
            }
            $days = (int) ceil(($d->getTimestamp() - $today->getTimestamp()) / 86400);
            $tone = $days < 0 ? 'red' : ($days <= 30 ? 'yellow' : 'blue');
            $reminders[] = [
                'title' => $title,
                'dueDate' => $d->format('M d, Y'),
                'days' => $days,
                'tone' => $tone,
                'icon' => $icon,
            ];
        };
        $addReminder('Next checkup', $medical['next_checkup_due'] ?? null, 'stethoscope');
        $addReminder('Vaccination expiry', $medical['vaccination_expiry'] ?? null, 'syringe');

        // Vaccination reminders come from the admin-set next-due date on each record.
        foreach ($vaccinations as $v) {
            $due = $v['nextDue'] ?? null;
            if ($due) {
                $vaccine = $v['vaccine'] ?? 'Vaccine';
                $addReminder("{$vaccine} vaccination due", $due, 'syringe');
            }
        }

        $history = [];
        if (!empty($medical['last_checkup_date'])) {
            $history[] = [
                'date' => $medical['last_checkup_date'],
                'doctor' => $medical['vet_name'] ?? 'Furescue Vet',
                'title' => 'Regular Check-up',
                'description' => 'General physical examination',
                'tone' => 'green',
            ];
        }
        foreach ($vaccinations as $v) {
            if (!empty($v['dateGiven'])) {
                $history[] = [
                    'date' => $v['dateGiven'],
                    'doctor' => $medical['vet_name'] ?? 'Furescue Vet',
                    'title' => $v['vaccine'] . ' Vaccination',
                    'description' => 'Vaccine administered',
                    'tone' => 'blue',
                ];
            }
        }
        if ($healthStatus === 'not_healthy') {
            $history[] = [
                'date' => $medical['updated_at'] ?? $animal['updated_at'],
                'doctor' => $medical['vet_name'] ?? 'Furescue Vet',
                'title' => 'Treatment',
                'description' => 'Marked not healthy — ' . ($condition ?? 'condition'),
                'tone' => 'red',
            ];
        }
        usort($history, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

        $vitals = [];
        if (isset($medical['weight_kg']) && $medical['weight_kg'] !== null) {
            $vitals[] = ['label' => 'Weight', 'value' => (string) $medical['weight_kg'], 'unit' => 'kg'];
        }
        if (isset($medical['temperature_c']) && $medical['temperature_c'] !== null) {
            $vitals[] = ['label' => 'Body Temperature', 'value' => (string) $medical['temperature_c'], 'unit' => '°C'];
        }
        if ($latestVital && isset($latestVital['heart_rate_bpm']) && $latestVital['heart_rate_bpm'] !== null) {
            $vitals[] = ['label' => 'Heart Rate', 'value' => (string) $latestVital['heart_rate_bpm'], 'unit' => 'bpm'];
        }
        $vitalMeta = $latestVital ? ('Recorded on ' . substr((string) $latestVital['recorded_at'], 0, 10)) : null;

        $heartRateHistory = [];
        foreach (array_reverse($vitalsRows) as $vr) {
            if (isset($vr['heart_rate_bpm']) && $vr['heart_rate_bpm'] !== null) {
                $heartRateHistory[] = [
                    'date' => substr((string) ($vr['recorded_at'] ?? ''), 0, 10),
                    'value' => (int) $vr['heart_rate_bpm'],
                ];
            }
        }

        $docRows = (new Repository($this->pdo, 'animal_documents'))->all(['animal_id' => $id], 'created_at', 'DESC');
        $documents = array_map(function ($d) {
            return [
                'id' => $d['id'],
                'name' => $d['name'],
                'type' => $d['doc_type'] ?? null,
                'fileUrl' => $d['file_url'] ?? null,
                'meta' => $d['meta'] ?? null,
            ];
        }, $docRows);

        $notesMeta = '';
        if (!empty($medical['vet_name'])) {
            $notesMeta .= 'by ' . $medical['vet_name'];
        }
        if (!empty($medical['updated_at'])) {
            $notesMeta = trim(($notesMeta !== '' ? $notesMeta . ' · ' : '') . 'updated ' . substr((string) $medical['updated_at'], 0, 10));
        }

        return [
            'id' => $animal['id'],
            'hasMedicalRecord' => !empty($medical),
            'name' => $animal['name'] ?? 'Unnamed',
            'species' => $animal['species'] ?? null,
            'breedType' => $animal['breed_type'] ?? null,
            'sex' => $animal['sex'] ?? null,
            'ageEstimate' => $animal['age_estimate'] ?? null,
            'birthDate' => $animal['birth_date'] ?? null,
            'barangay' => $animal['barangay'] ?? null,
            'adoptionStatus' => $animal['adoption_status'] ?? null,
            'photoUrl' => $photoUrl,
            'overview' => [
                'healthStatus' => $healthStatus,
                'vaccinationStatus' => $vaccinationStatus,
                'deworming' => $medical['deworming_status'] ?? 'unknown',
                'neutered' => $medical['neutered'] ?? 'unknown',
                'notes' => $medical['medical_history_notes'] ?? null,
                'notesMeta' => $notesMeta,
            ],
            'history' => $history,
            'vaccinations' => $vaccinations,
            'reminders' => $reminders,
            'vitals' => $vitals,
            'vitalMeta' => $vitalMeta,
            'heartRateHistory' => $heartRateHistory,
            'documents' => $documents,
            'protocols' => VaccinationEngine::protocolsForSpecies($animal['species'] ?? ''),
            'ageWeeks' => null,
        ];
    }
}
