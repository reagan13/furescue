<?php

namespace App\Repositories;

use App\Database;
use App\Entity\Report;
use PDO;

class ReportRepository
{
    private const COLUMNS = [
        'id', 'resident_id', 'animal_description', 'species', 'photo_urls', 'latitude', 'longitude',
        'address_text', 'content_hash', 'duplicate_of_report_id', 'validation_status',
        'status', 'dismiss_reason', 'verified_by', 'verified_at', 'created_at',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(string $id): ?Report
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Report::fromRow($row) : null;
    }

    public function create(array $data): string
    {
        if (empty($data['id'])) {
            $data['id'] = Database::uuidV4();
        }
        foreach (array_keys($data) as $column) {
            $this->assertColumn($column);
        }
        $columns = array_keys($data);
        $colSql = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO reports ({$colSql}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
        return $data['id'];
    }

    public function update(string $id, array $data): bool
    {
        unset($data['id']);
        if (empty($data)) {
            return true;
        }
        foreach (array_keys($data) as $column) {
            $this->assertColumn($column);
        }
        $setSql = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;
        $stmt = $this->pdo->prepare("UPDATE reports SET {$setSql} WHERE id = ?");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function paginate(int $page, int $perPage, array $filters = [], string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        [$where, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $this->assertColumn($orderBy);
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("SELECT * FROM reports {$where} ORDER BY {$orderBy} {$dir} LIMIT " . (int) $perPage . " OFFSET " . (int) $offset);
        $stmt->execute($params);

        return [
            'items' => array_map(fn(array $row) => Report::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];
        foreach ($filters as $column => $value) {
            $this->assertColumn($column);
            if (is_array($value)) {
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $clauses[] = "{$column} IN ({$placeholders})";
                foreach ($value as $v) {
                    $params[] = $v;
                }
            } else {
                $clauses[] = "{$column} = ?";
                $params[] = $value;
            }
        }
        $where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';
        return [$where, $params];
    }

    private function assertColumn(string $column): void
    {
        if (!in_array($column, self::COLUMNS, true)) {
            throw new \InvalidArgumentException("Column not allowed: {$column}");
        }
    }
}
