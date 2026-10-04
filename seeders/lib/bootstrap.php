<?php

/**
 * Shared seeder bootstrap: env, PDO, and helpers.
 * Safe to require_once from any seeder entry point.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

\App\Env::load();

if (!class_exists('SeedContext', false)) {
    final class SeedContext
    {
        public const DEV_PASSWORD = 'Password123!';

        public const TARGET_RESIDENTS = 140;
        public const TARGET_RESCUERS = 24;
        public const TARGET_APPLICANTS = 8;
        public const TARGET_REJECTED = 4;
        public const TARGET_REPORTS = 160;
        public const TARGET_ANIMALS = 120;
        public const TARGET_ADOPTIONS = 120;
        public const TARGET_MESSAGES = 140;
        public const TARGET_NOTIFICATIONS = 160;
        public const TARGET_PROGRESS = 140;

        public PDO $pdo;
        public string $hash;
        public ?string $adminId = null;

        /** @var list<string> */
        public array $adminIds = [];
        /** @var list<string> */
        public array $residentIds = [];
        /** @var list<string> */
        public array $rescuerIds = [];
        /** @var list<string> */
        public array $pendingRescuerIds = [];
        /** @var list<string> */
        public array $reportIds = [];
        /** @var list<string> */
        public array $verifiedReportIds = [];
        /** @var list<array{id:string,resident_id:string,address_text:?string}> */
        public array $verifiedReports = [];
        /** @var list<string> */
        public array $caseIds = [];
        /** @var list<array{id:string,report_id:string,status:string,rescuer_id:?string}> */
        public array $cases = [];
        /** @var list<string> */
        public array $animalIds = [];
        /** @var list<array{id:string,name:?string,species:string,adoption_status:string,case_id:?string}> */
        public array $animals = [];
        /** @var list<string> */
        public array $adoptionIds = [];
        /** @var list<array{id:string,animal_id:string,applicant_id:string,status:string}> */
        public array $adoptions = [];
        /** @var list<string> */
        public array $moduleIds = [];

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
            $this->hash = password_hash(self::DEV_PASSWORD, PASSWORD_ARGON2ID);
        }

        public function insert(string $table, array $data): string
        {
            $id = $data['id'] ?? \App\Database::uuidV4();
            $data['id'] = $id;
            $cols = array_keys($data);
            $colSql = implode(', ', array_map(static fn($c) => "`$c`", $cols));
            $placeholders = array_map(static fn($c) => ":$c", $cols);
            $sql = "INSERT INTO {$table} ({$colSql}) VALUES (" . implode(', ', $placeholders) . ')';
            $this->pdo->prepare($sql)->execute($data);
            return $id;
        }

        public function rowExists(string $table, string $where, array $params = []): bool
        {
            $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE {$where} LIMIT 1");
            $stmt->execute($params);
            return (bool) $stmt->fetch();
        }

        public function count(string $sql, array $params = []): int
        {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }

        /** @return list<string> */
        public function ids(string $sql, array $params = []): array
        {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        public function userId(string $email): ?string
        {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            return $row ? (string) $row['id'] : null;
        }

        public function ensureUser(array $data): string
        {
            $existing = $this->userId($data['email']);
            if ($existing) {
                return $existing;
            }
            return $this->insert('users', $data);
        }

        public function pick(array $arr): mixed
        {
            return $arr[array_rand($arr)];
        }

        public function int(int $min, int $max): int
        {
            return random_int($min, $max);
        }

        public function float(float $min, float $max, int $decimals = 4): float
        {
            return round($min + (mt_rand() / mt_getrandmax()) * ($max - $min), $decimals);
        }

        public function ago(int $seconds): string
        {
            return date('Y-m-d H:i:s', time() - $seconds);
        }

        public function daysAgo(int $minDays, int $maxDays): string
        {
            return $this->ago($this->int($minDays, $maxDays) * 86400 + $this->int(0, 80000));
        }

        public function dateAgo(int $minDays, int $maxDays): string
        {
            return date('Y-m-d', time() - $this->int($minDays, $maxDays) * 86400);
        }

        public function jitter(float $lat, float $lng, float $spread = 0.004): array
        {
            $outLat = $lat + $this->float(-$spread, $spread, 6);
            $outLng = $lng + $this->float(-$spread, $spread, 6);
            $outLat = min(7.01, max(6.89, $outLat));
            $outLng = min(126.27, max(126.13, $outLng));
            return [$outLat, $outLng];
        }

        public function loadUsers(): void
        {
            $this->adminIds = $this->ids("SELECT id FROM users WHERE role = 'admin' ORDER BY created_at ASC");
            $this->adminId = $this->adminIds[0] ?? $this->userId('admin@furescue.local');
            $this->residentIds = $this->ids("SELECT id FROM users WHERE role = 'resident' AND account_status = 'active' ORDER BY created_at ASC");
            $this->rescuerIds = $this->ids("SELECT id FROM users WHERE role = 'rescuer' AND account_status = 'active' ORDER BY created_at ASC");
            $this->pendingRescuerIds = $this->ids("SELECT id FROM users WHERE role = 'rescuer' AND account_status = 'pending' ORDER BY created_at ASC");
        }

        public function loadReports(): void
        {
            $this->reportIds = $this->ids('SELECT id FROM reports ORDER BY created_at ASC');
            $this->verifiedReportIds = $this->ids("SELECT id FROM reports WHERE status = 'verified' ORDER BY created_at ASC");
            $stmt = $this->pdo->query(
                "SELECT id, resident_id, address_text FROM reports WHERE status = 'verified' ORDER BY created_at ASC"
            );
            $this->verifiedReports = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }

        public function loadCases(): void
        {
            $this->caseIds = $this->ids('SELECT id FROM cases ORDER BY created_at ASC');
            $stmt = $this->pdo->query(
                'SELECT id, report_id, status, assigned_rescuer_id AS rescuer_id FROM cases ORDER BY created_at ASC'
            );
            $this->cases = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }

        public function loadAnimals(bool $includeDeleted = false): void
        {
            $where = $includeDeleted ? '' : ' WHERE deleted_at IS NULL';
            $this->animalIds = $this->ids("SELECT id FROM animals{$where} ORDER BY created_at ASC");
            $stmt = $this->pdo->query(
                "SELECT id, name, species, adoption_status, case_id FROM animals{$where} ORDER BY created_at ASC"
            );
            $this->animals = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }

        public function loadAdoptions(): void
        {
            $this->adoptionIds = $this->ids('SELECT id FROM adoptions ORDER BY created_at ASC');
            $stmt = $this->pdo->query(
                'SELECT id, animal_id, applicant_id, status FROM adoptions ORDER BY created_at ASC'
            );
            $this->adoptions = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }

        public function loadModules(): void
        {
            $this->moduleIds = $this->ids('SELECT id FROM elearning_modules ORDER BY created_at ASC');
        }

        public function echoCount(string $label, string $sql): void
        {
            echo "  {$label}: " . $this->count($sql) . "\n";
        }
    }
}

if (!function_exists('seeders_is_entry')) {
    function seeders_is_entry(string $file): bool
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        if ($script === '') {
            return false;
        }
        $a = realpath($script);
        $b = realpath($file);
        return $a !== false && $b !== false && $a === $b;
    }
}

if (!isset($ctx) || !($ctx instanceof SeedContext)) {
    $pdo = \App\Database::connect();
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $ctx = new SeedContext($pdo);
} else {
    $pdo = $ctx->pdo;
}
