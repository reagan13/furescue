<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

class AnalyticsController extends AbstractController
{
    private const OVERVIEW_LABELS = [
        'reports' => 'Total reports',
        'reports_verified' => 'Reports verified',
        'cases' => 'Total cases',
        'cases_resolved' => 'Cases resolved',
        'animals' => 'Total animals',
        'animals_adopted' => 'Animals adopted',
        'adoptions_pending' => 'Adoptions pending',
        'adoptions_completed' => 'Adoptions completed',
        'rescuers_active' => 'Active rescuers',
        'rescuers_on_duty' => 'Rescuers on duty',
        'rescuers_off_duty' => 'Rescuers off duty',
        'residents' => 'Residents',
    ];

    public function overview(Request $req): void
    {
        Response::success(['stats' => $this->overviewStats()]);
    }

    public function adoptionTrends(Request $req): void
    {
        Response::success(['trends' => $this->adoptionTrendRows($req)]);
    }

    public function healthUpdates(Request $req): void
    {
        Response::success(['updates' => $this->healthUpdateRows($req)]);
    }

    public function exportOverview(Request $req): void
    {
        $stats = $this->overviewStats();
        $rows = [];
        foreach (self::OVERVIEW_LABELS as $key => $label) {
            $value = $stats[$key] ?? 0;
            $rows[] = [$label, is_scalar($value) ? $value : 0, date('Y-m-d')];
        }
        $this->csv('furescue-overview-' . date('Y-m-d') . '.csv', ['Metric', 'Value', 'Date'], $rows);
    }

    public function exportAdoptionTrends(Request $req): void
    {
        $rows = array_map(
            static fn(array $t): array => [(string) ($t['day'] ?? ''), (int) ($t['completed'] ?? 0)],
            $this->adoptionTrendRows($req)
        );
        $this->csv('furescue-adoption-trends-' . date('Y-m-d') . '.csv', ['Date', 'Count'], $rows);
    }

    public function exportHealthUpdates(Request $req): void
    {
        $rows = array_map(static function (array $u): array {
            return [
                (string) ($u['id'] ?? ''),
                (string) ($u['animal_name'] ?? ''),
                (string) ($u['species'] ?? ''),
                (string) ($u['breed_type'] ?? ''),
                (string) ($u['rescue_status'] ?? ''),
                (string) ($u['health_status'] ?? ''),
                (string) ($u['logged_by_name'] ?? ''),
                (string) ($u['logged_at'] ?? ''),
            ];
        }, $this->healthUpdateRows($req));
        $this->csv(
            'furescue-health-updates-' . date('Y-m-d') . '.csv',
            ['ID', 'Animal', 'Species', 'Breed', 'Rescue Status', 'Health Status', 'Logged By', 'Logged At'],
            $rows
        );
    }

    private function overviewStats(): array
    {
        return [
            'reports' => $this->count('reports'),
            'reports_verified' => $this->countWhere("reports", "status = 'verified'"),
            'cases' => $this->count('cases'),
            'cases_resolved' => $this->countWhere("cases", "status = 'resolved'"),
            'animals' => $this->count('animals'),
            'animals_adopted' => $this->countWhere("animals", "adoption_status = 'adopted'"),
            'adoptions_pending' => $this->countWhere("adoptions", "status = 'pending'"),
            'adoptions_completed' => $this->countWhere("adoptions", "status = 'completed'"),
            ...$this->rescuerDutyCounts(),
            'residents' => $this->countWhere("users", "role = 'resident'"),
            'cases_in_progress' => $this->countWhere("cases", "status IN ('assigned','in_progress')"),
            'reports_pending' => $this->countWhere("reports", "status = 'pending_verification'"),
            'reports_today' => $this->countWhere("reports", "DATE(created_at) = CURDATE()"),
            'pending_today' => $this->countWhere("reports", "status = 'pending_verification' AND DATE(created_at) = CURDATE()"),
            'in_progress_today' => $this->countWhere("cases", "status IN ('assigned','in_progress') AND DATE(updated_at) = CURDATE()"),
            'resolved_today' => $this->countWhere("cases", "status = 'resolved' AND DATE(updated_at) = CURDATE()"),
            'reports_monthly' => $this->reportsMonthly(),
        ];
    }

    private function reportsMonthly(): array
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? "SELECT strftime('%Y-%m', created_at) AS ym, COUNT(*) AS c
               FROM reports
               WHERE created_at >= date('now', '-5 months', 'start of month')
               GROUP BY ym
               ORDER BY ym"
            : "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
               FROM reports
               WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
               GROUP BY ym
               ORDER BY ym";
        $stmt = $this->pdo->query($sql);
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[(string) $row['ym']] = (int) $row['c'];
        }
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $dt = new \DateTimeImmutable("first day of -{$i} months");
            $ym = $dt->format('Y-m');
            $out[] = ['month' => $dt->format('M'), 'count' => $map[$ym] ?? 0];
        }
        return $out;
    }

    private function adoptionTrendRows(Request $req): array
    {
        [$start, $end] = $this->range($req);
        $ranged = $start !== null && $end !== null;
        $sql = "SELECT DATE(completed_at) AS day, COUNT(*) AS completed
             FROM adoptions WHERE status = 'completed' AND completed_at IS NOT NULL";
        $args = [];
        if ($ranged) {
            $sql .= " AND DATE(completed_at) BETWEEN ? AND ?";
            $args = [$start, $end];
        }
        $sql .= $ranged
            ? " GROUP BY DATE(completed_at) ORDER BY day ASC LIMIT 400"
            : " GROUP BY DATE(completed_at) ORDER BY day DESC LIMIT 30";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function healthUpdateRows(Request $req): array
    {
        [$start, $end] = $this->range($req);
        $ranged = $start !== null && $end !== null;
        $sql = "SELECT fs.id, fs.animal_id, fs.rescue_status, fs.health_status, fs.logged_at,
                    a.name AS animal_name, a.species, a.breed_type,
                    u.full_name AS logged_by_name
             FROM animal_field_status fs
             JOIN animals a ON a.id = fs.animal_id
             LEFT JOIN users u ON u.id = fs.logged_by";
        $args = [];
        if ($ranged) {
            $sql .= " WHERE DATE(fs.logged_at) BETWEEN ? AND ?";
            $args = [$start, $end];
        }
        $sql .= $ranged
            ? " ORDER BY fs.logged_at DESC LIMIT 500"
            : " ORDER BY fs.logged_at DESC LIMIT 50";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function range(Request $req): array
    {
        return [$this->dateParam($req, 'start'), $this->dateParam($req, 'end')];
    }

    private function dateParam(Request $req, string $key): ?string
    {
        $raw = trim((string) ($req->query[$key] ?? ''));
        if ($raw === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $raw);
        if (!$dt || $dt->format('Y-m-d') !== $raw) {
            return null;
        }
        return $raw;
    }

    private function csv(string $filename, array $header, array $rows): void
    {
        Response::$sent = true;
        Response::$statusCode = 200;
        Response::$headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ];
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store');
            header('Access-Control-Allow-Origin: *');
        }
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $header, ',', '"', '\\');
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $cell) {
                $cells[] = is_scalar($cell) || $cell === null ? (string) $cell : '';
            }
            fputcsv($out, $cells, ',', '"', '\\');
        }
        fclose($out);
    }

    private function rescuerDutyCounts(): array
    {
        $active = $this->countWhere("users", "role = 'rescuer' AND account_status = 'active'");
        $onDuty = $this->countWhere(
            "rescuer_duty_status d JOIN users u ON u.id = d.user_id",
            "d.status = 'on_duty' AND u.account_status = 'active' AND u.role = 'rescuer'"
        );
        return [
            'rescuers_active' => $active,
            'rescuers_on_duty' => $onDuty,
            'rescuers_off_duty' => max(0, $active - $onDuty),
        ];
    }

    private function count(string $table): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table}");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function countWhere(string $table, string $where): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
