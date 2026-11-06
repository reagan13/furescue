<?php

declare(strict_types=1);

use App\Database;
use App\Repositories\Repository;
use App\Repositories\UserRepository;
use App\Services\VaccinationEngine;

require __DIR__ . '/../../../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__, 3))->safeLoad();

$requiredRole = 'admin';
require_once dirname(__DIR__, 3) . '/views/path.php';
require views_path('components/guard.php');

require views_path('components/admin-ui-helpers.php');

$recordIdParam = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
if ($recordIdParam === '' || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $recordIdParam) !== 1) {
    header('Location: /admin/health-records/');
    exit;
}

$pdo = Database::connect();
$uid = (string) $_SESSION['user']['id'];

$animalRepo = new Repository($pdo, 'animals');
$animal = $animalRepo->find($recordIdParam);
if ($animal === null || !empty($animal['deleted_at'])) {
    header('Location: /admin/health-records/');
    exit;
}

$fsStmt = $pdo->prepare(
    "SELECT health_status FROM animal_field_status
     WHERE animal_id = ? ORDER BY logged_at DESC LIMIT 1"
);
$fsStmt->execute([$recordIdParam]);
$fsRow = $fsStmt->fetch(\PDO::FETCH_ASSOC);
$healthStatus = $fsRow['health_status'] ?? 'healthy';

$medicalRepo = new Repository($pdo, 'animal_medical_records');
$medical = $medicalRepo->findBy('animal_id', $recordIdParam) ?: [];

$vitalsRepo = new Repository($pdo, 'vitals_log');
$vitalsRows = $vitalsRepo->all(['animal_id' => $recordIdParam], 'recorded_at', 'DESC');
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

$vaccinationStatus = $medical['vaccination_status'] ?? 'none';
$vaccinationDetails = $medical['vaccination_details'] ?? null;
if (is_string($vaccinationDetails) && $vaccinationDetails !== '') {
    $dec = json_decode($vaccinationDetails, true);
    $vaccinationDetails = is_array($dec) ? $dec : [];
} elseif ($vaccinationDetails === null) {
    $vaccinationDetails = [];
}

$rawRecords = $medical['vaccination_records'] ?? [];
if (is_string($rawRecords) && $rawRecords !== '') {
    $rawRecords = json_decode($rawRecords, true) ?: [];
}
if (!is_array($rawRecords)) {
    $rawRecords = [];
}
if (empty($rawRecords) && !empty($vaccinationDetails)) {
    $rawRecords = array_map(static function ($v) {
        if (is_string($v)) {
            $text = trim($v);
            return [
                'vaccine' => $text !== '' ? $text : 'Vaccine',
                'administered_date' => null,
                'next_due' => null,
                'status' => null,
                'dose_number' => null,
                'manufacturer' => null,
                'product_name' => null,
                'batch_number' => null,
                'route' => null,
                'notes' => null,
            ];
        }
        $row = is_array($v) ? $v : [];
        return [
            'vaccine' => $row['vaccine'] ?? 'Vaccine',
            'administered_date' => $row['dateGiven'] ?? ($row['date'] ?? null),
            'next_due' => $row['nextDue'] ?? null,
            'status' => $row['status'] ?? null,
            'dose_number' => $row['doseNumber'] ?? null,
            'manufacturer' => $row['manufacturer'] ?? null,
            'product_name' => $row['productName'] ?? null,
            'batch_number' => $row['batchNumber'] ?? null,
            'route' => $row['route'] ?? null,
            'notes' => $row['notes'] ?? null,
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
        'batchNumber' => $r['batch_number'] ?? null,
        'route' => $r['route'] ?? null,
        'notes' => $r['notes'] ?? null,
    ];
}, $rawRecords);

$today = new \DateTime();
$reminders = [];
$addReminder = function (string $title, $due, string $icon) use (&$reminders, $today): void {
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

foreach ($vaccinations as $v) {
    $due = $v['nextDue'] ?? null;
    if ($due) {
        $vaccine = $v['vaccine'] ?? 'Vaccine';
        $addReminder($vaccine . ' vaccination due', $due, 'syringe');
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
$condition = $medical['condition'] ?? null;
if ($condition === null) {
    $condition = $healthStatus === 'not_healthy' ? 'Unknown' : 'Healthy';
}
if ($healthStatus === 'not_healthy') {
    $history[] = [
        'date' => $medical['updated_at'] ?? $animal['updated_at'],
        'doctor' => $medical['vet_name'] ?? 'Furescue Vet',
        'title' => 'Treatment',
        'description' => 'Marked not healthy — ' . $condition,
        'tone' => 'red',
    ];
}
usort($history, static fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

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

$docRows = (new Repository($pdo, 'animal_documents'))->all(['animal_id' => $recordIdParam], 'created_at', 'DESC');
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

$record = [
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

$cap = static function (mixed $v): string {
    $s = (string) ($v ?? '');
    return $s !== '' ? mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8') : $s;
};

$toneFor = static function (string $field, mixed $value): string {
    return match ($field) {
        'healthStatus' => $value === 'not_healthy' ? 'red' : 'green',
        'vaccinationStatus' => $value === 'complete' ? 'blue' : ($value === 'partial' ? 'yellow' : 'red'),
        'deworming' => $value === 'up_to_date' ? 'green' : ($value === 'overdue' ? 'red' : 'yellow'),
        'neutered' => $value === 'yes' ? 'green' : ($value === 'no' ? 'orange' : 'yellow'),
        default => 'blue',
    };
};

$TONE = [
    'green' => 'tint-green text-green',
    'blue' => 'tint-blue text-blue',
    'purple' => 'tint-purple text-purple',
    'orange' => 'tint-orange text-orange',
    'red' => 'tint-red text-red',
    'yellow' => 'tint-yellow text-yellow',
];
$ICON = [
    'green' => 'heart',
    'blue' => 'shield',
    'purple' => 'link',
    'orange' => 'scissors',
    'red' => 'activity',
    'yellow' => 'clock',
];

$chip = static function (string $tone, string $text): string {
    return '<span class="pill pill--' . e($tone) . '">' . e($text) . '</span>';
};

$emptyState = static function (string $msg, string $icon = 'inbox'): string {
    return '<div class="empty-state"><i data-lucide="' . e($icon) . '"></i><span>' . e($msg) . '</span></div>';
};

$vaxStatusPill = static function (mixed $status): string {
    $s = (string) ($status ?? '');
    $tone = match ($s) {
        'complete', 'Completed' => 'green',
        'partial' => 'yellow',
        'none', 'Not vaccinated' => 'red',
        default => 'gray',
    };
    $cls = $tone === 'gray' ? 'pill' : 'pill pill--' . $tone;
    $label = $s !== '' ? ucfirst(strtolower($s)) : 'Unknown';
    return '<span class="' . e($cls) . '">' . e($label) . '</span>';
};

$actionsHtml = !empty($record['hasMedicalRecord'])
    ? button_html('Edit', 'outline', 'default', '', '', 'data-action="edit-record"')
        . button_html('Add Health Record', 'default', 'sm', '', '', 'data-action="add-record"')
        . button_html('Post for adoption', 'outline', 'sm', '', '', 'data-action="post-for-adoption"')
        . button_html('Delete', 'outline', 'sm', '', '', 'data-action="delete-record"')
    : button_html('Add Health Record', 'default', 'sm', '', '', 'data-action="add-record"');
