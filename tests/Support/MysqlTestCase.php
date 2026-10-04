<?php

namespace App\Tests\Support;

use App\Database;
use App\Env;
use App\Services\DedupService;
use PDO;
use PDOException;

/**
 * In-process Router.dispatch against the dedicated MySQL `furescue_test` schema.
 *
 * @group mysql
 */
abstract class MysqlTestCase extends ApiTestCase
{
    protected function setUp(): void
    {
        $this->bootMysqlTestEnv();
        parent::setUp();
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        Database::reset();
    }

    protected function bootMysqlTestEnv(): void
    {
        if (!Env::loadTest()) {
            $this->markTestSkipped('Missing .env.test. Copy .env.test.example.');
        }
        Database::reset();
        if (!Env::isTestDatabase()) {
            $this->markTestSkipped('MySQL tests require DB_NAME=furescue_test.');
        }
        try {
            Database::connect()->query('SELECT 1');
        } catch (PDOException $e) {
            $this->markTestSkipped('MySQL test database is not reachable: ' . $e->getMessage());
        }
    }

    protected function createPdo(): PDO
    {
        return Database::connect();
    }

    protected function makeDedup(): DedupService
    {
        return new DedupService($this->pdo);
    }

    protected function seedReport(array $overrides = []): array
    {
        if (!isset($overrides['resident_id'])) {
            $overrides['resident_id'] = $this->seedResident()['id'];
        }
        return parent::seedReport($overrides);
    }

    protected function seedAnimal(array $overrides = []): array
    {
        if (!isset($overrides['created_by'])) {
            $overrides['created_by'] = $this->seedResident()['id'];
        }
        return parent::seedAnimal($overrides);
    }

    protected function seedModule(array $overrides = []): array
    {
        if (!isset($overrides['created_by'])) {
            $overrides['created_by'] = $this->seedAdmin()['id'];
        }
        return parent::seedModule($overrides);
    }

    protected function seedNotification(array $overrides = []): array
    {
        if (!isset($overrides['user_id'])) {
            $overrides['user_id'] = $this->seedResident()['id'];
        }
        return parent::seedNotification($overrides);
    }

    protected function seedListing(array $overrides = []): array
    {
        if (!isset($overrides['posted_by'])) {
            $overrides['posted_by'] = $this->seedResident()['id'];
        }
        if (!isset($overrides['animal_id'])) {
            $overrides['animal_id'] = $this->seedAnimal(['created_by' => $overrides['posted_by']])['id'];
        }
        return parent::seedListing($overrides);
    }

    protected function seedAdoption(array $overrides = []): array
    {
        if (!isset($overrides['applicant_id'])) {
            $overrides['applicant_id'] = $this->seedResident()['id'];
        }
        if (!isset($overrides['animal_id'])) {
            $overrides['animal_id'] = $this->seedAnimal([
                'adoption_status' => 'available',
                'created_by' => $overrides['applicant_id'],
            ])['id'];
        }
        return parent::seedAdoption($overrides);
    }
}
