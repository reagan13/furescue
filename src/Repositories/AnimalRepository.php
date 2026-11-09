<?php

namespace App\Repositories;

use App\Database;
use App\Entity\Animal;
use PDO;

class AnimalRepository
{
    private const COLUMNS = [
        'id', 'name', 'species', 'breed_type', 'model_preset', 'sex', 'age_estimate', 'birth_date',
        'color_markings', 'barangay', 'description', 'photo_urls', 'model_3d_url',
        'photo_360_set', 'adoption_status', 'source', 'case_id', 'created_by', 'deleted_at',
        'created_at', 'updated_at',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(string $id): ?Animal
    {
        $stmt = $this->pdo->prepare('SELECT * FROM animals WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Animal::fromRow($row) : null;
    }

    public function findActive(string $id): ?Animal
    {
        $stmt = $this->pdo->prepare('SELECT * FROM animals WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Animal::fromRow($row) : null;
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
        $stmt = $this->pdo->prepare("INSERT INTO animals ({$colSql}) VALUES ({$placeholders})");
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
        $stmt = $this->pdo->prepare("UPDATE animals SET {$setSql} WHERE id = ?");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function softDelete(string $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE animals SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    private function assertColumn(string $column): void
    {
        if (!in_array($column, self::COLUMNS, true)) {
            throw new \InvalidArgumentException("Column not allowed: {$column}");
        }
    }
}
