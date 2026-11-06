<?php

namespace App\Services;

use PDO;

/**
 * Rescue-activity and place counts for the admin analytics page.
 * Health rows come from medical records the health screen writes.
 */
class AnalyticsSummary
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{verified_reports:int,resolved_cases:int,rescue_rate:?float,avg_hours_to_resolve:?float}
     */
    public function rescueActivity(): array
    {
        $verified = (int) $this->pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'verified'")->fetchColumn();
        $resolved = (int) $this->pdo->query("SELECT COUNT(*) FROM cases WHERE status = 'resolved'")->fetchColumn();
        $rate = $verified > 0 ? round($resolved / $verified, 3) : null;

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $hours = $driver === 'sqlite'
            ? '(julianday(c.updated_at) - julianday(COALESCE(a.assigned_at, c.created_at))) * 24'
            : 'TIMESTAMPDIFF(HOUR, COALESCE(a.assigned_at, c.created_at), c.updated_at)';
        $sql = "SELECT AVG({$hours})
                FROM cases c
                LEFT JOIN (
                    SELECT case_id, MIN(created_at) AS assigned_at
                    FROM case_activity_log
                    WHERE action = 'assigned'
                    GROUP BY case_id
                ) a ON a.case_id = c.id
                WHERE c.status = 'resolved'";
        $avg = $this->pdo->query($sql)->fetchColumn();

        return [
            'verified_reports' => $verified,
            'resolved_cases' => $resolved,
            'rescue_rate' => $rate,
            'avg_hours_to_resolve' => $avg === null || $avg === false ? null : round((float) $avg, 1),
        ];
    }

    /**
     * @return list<array{place:string,reports:int}>
     */
    public function geographic(int $limit = 8): array
    {
        $limit = max(1, $limit);
        $sql = "SELECT COALESCE(NULLIF(TRIM(address_text), ''), 'Unspecified place') AS place,
                       COUNT(*) AS reports
                FROM reports
                WHERE status = 'verified' AND validation_status = 'validated'
                GROUP BY place
                ORDER BY reports DESC, place ASC
                LIMIT {$limit}";
        $rows = [];
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'place' => (string) $row['place'],
                'reports' => (int) $row['reports'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function medicalUpdates(?string $start, ?string $end): array
    {
        $ranged = $start !== null && $end !== null;
        $sql = "SELECT m.id, m.vaccination_status, m.updated_at, m.weight_kg,
                       a.name AS animal_name, a.species, a.breed_type,
                       u.full_name AS updated_by_name
                FROM animal_medical_records m
                JOIN animals a ON a.id = m.animal_id
                LEFT JOIN users u ON u.id = m.updated_by";
        $args = [];
        if ($ranged) {
            $sql .= ' WHERE DATE(m.updated_at) BETWEEN ? AND ?';
            $args = [$start, $end];
        }
        $sql .= $ranged
            ? ' ORDER BY m.updated_at DESC LIMIT 500'
            : ' ORDER BY m.updated_at DESC LIMIT 50';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
