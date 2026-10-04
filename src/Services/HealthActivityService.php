<?php

namespace App\Services;

use PDO;

class HealthActivityService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function daily(): array
    {
        $days = 400;
        $map = [];
        $today = new \DateTime();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = (clone $today)->modify("-{$i} days")->format('Y-m-d');
            $map[$d] = ['date' => $d, 'checkups' => 0, 'treatments' => 0, 'vaccinations' => 0];
        }

        $this->fill($map,
            "SELECT DATE(last_checkup_date) AS day, COUNT(*) AS c
             FROM animal_medical_records WHERE last_checkup_date IS NOT NULL
             GROUP BY day",
            'checkups');

        $this->fill($map,
            "SELECT DATE(logged_at) AS day, COUNT(*) AS c
             FROM animal_field_status WHERE health_status = 'not_healthy'
             GROUP BY day",
            'treatments');

        $this->fill($map,
            "SELECT DATE(last_checkup_date) AS day, COUNT(*) AS c
             FROM animal_medical_records WHERE vaccination_status <> 'none' AND last_checkup_date IS NOT NULL
             GROUP BY day",
            'vaccinations');

        return array_values($map);
    }

    private function fill(array &$map, string $sql, string $key): void
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $day = $row['day'];
            if (isset($map[$day])) {
                $map[$day][$key] += (int) $row['c'];
            }
        }
    }
}
