<?php

namespace App\Services;

use App\Database;
use App\Http\DomainException;
use App\Repositories\AnimalRepository;
use App\Repositories\Repository;
use PDO;

class ResidentListingService
{
    private AnimalRepository $animals;
    private Repository $listings;
    private ListingPhotoUpload $photos;
    private AdoptionEligibilityService $eligibility;

    public function __construct(private PDO $pdo)
    {
        $this->animals = new AnimalRepository($pdo);
        $this->listings = new Repository($pdo, 'adoption_listings');
        $this->photos = new ListingPhotoUpload();
        $this->eligibility = new AdoptionEligibilityService($pdo);
    }

    /**
     * @param list<array{name:string,type:string,tmp_name:string,error:int,size:int}> $files
     * @return array{listing:array,animal:array}
     */
    public function createFromProfile(string $userId, array $fields, array $files = []): array
    {
        $species = (string) $fields['species'];
        $breed = (string) ($fields['breed_type'] ?? ($species === 'cat' ? 'puspin' : 'aspin'));
        $urls = ListingPhotoUpload::normalizeUrlList($fields['photo_urls'] ?? null);
        if ($files !== []) {
            $urls = array_values(array_unique(array_merge($urls, $this->photos->store($files))));
        }
        if ($urls === []) {
            throw new \InvalidArgumentException('At least one profile photo is required.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $animalId = Database::uuidV4();
            $this->animals->create([
                'id' => $animalId,
                'name' => $fields['name'],
                'species' => $species,
                'breed_type' => $breed,
                'sex' => $fields['sex'],
                'age_estimate' => $fields['age_estimate'] ?? null,
                'color_markings' => $fields['color_markings'] ?? null,
                'description' => $fields['description'],
                'photo_urls' => json_encode($urls, JSON_UNESCAPED_SLASHES),
                'adoption_status' => 'not_listed',
                'source' => 'resident_listing',
                'created_by' => $userId,
            ]);
            $listingId = $this->insertListing($animalId, $userId);
            $this->recordMinimumHealth($animalId, $userId, $fields);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $animal = $this->animals->find($animalId);

        return [
            'listing' => $this->listings->find($listingId) ?? [],
            'animal' => $animal ? $animal->toArray() : ['id' => $animalId],
        ];
    }

    /**
     * @return array{listing:array}
     */
    public function createFromExisting(string $userId, string $animalId): array
    {
        $animal = $this->animals->findActive($animalId);
        if (!$animal) {
            throw new DomainException('NOT_FOUND', 'Animal not found', 404);
        }
        if (!$this->eligibility->isEligible($animalId)) {
            throw new DomainException(
                AdoptionEligibilityService::ERROR_CODE,
                AdoptionEligibilityService::ERROR_MESSAGE,
                409
            );
        }
        if ($this->findLiveListing($animalId)) {
            throw new DomainException(
                'LISTING_EXISTS',
                'This animal already has a pending or approved listing.',
                409
            );
        }

        try {
            $listingId = $this->insertListing($animalId, $userId);
        } catch (\PDOException $e) {
            if ($this->findLiveListing($animalId)) {
                throw new DomainException(
                    'LISTING_EXISTS',
                    'This animal already has a pending or approved listing.',
                    409
                );
            }
            throw $e;
        }

        return ['listing' => $this->listings->find($listingId) ?? []];
    }

    public function recordMinimumHealth(string $animalId, string $userId, array $fields): void
    {
        $vaccine = trim((string) ($fields['vaccine_name'] ?? ''));
        $date = trim((string) ($fields['vaccine_date'] ?? ''));
        $weight = $fields['weight_kg'] ?? null;
        $heart = $fields['heart_rate_bpm'] ?? null;
        if ($vaccine === '' && ($weight === null || $weight === '') && ($heart === null || $heart === '')) {
            return;
        }
        if ($date !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('Vaccine date must be YYYY-MM-DD.');
            }
        }
        if ($vaccine !== '' && $date === '') {
            throw new \InvalidArgumentException('A vaccination needs the date it was given.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, vaccination_records, vaccination_status FROM animal_medical_records WHERE animal_id = ? LIMIT 1'
        );
        $stmt->execute([$animalId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $records = [];
        if ($existing && is_string($existing['vaccination_records'] ?? null) && $existing['vaccination_records'] !== '') {
            $decoded = json_decode($existing['vaccination_records'], true);
            $records = is_array($decoded) ? $decoded : [];
        }
        if ($vaccine !== '') {
            $records[] = ['name' => $vaccine, 'date_given' => $date, 'status' => 'given'];
        }
        $status = (string) ($existing['vaccination_status'] ?? 'none');
        if ($records !== [] && ($status === '' || $status === 'none')) {
            $status = 'partial';
        }
        $encoded = json_encode(array_values($records), JSON_UNESCAPED_UNICODE);
        $weightValue = ($weight === null || $weight === '') ? null : $weight;
        if ($existing) {
            $update = $this->pdo->prepare(
                'UPDATE animal_medical_records
                 SET vaccination_records = ?, vaccination_status = ?, weight_kg = COALESCE(?, weight_kg), updated_by = ?, updated_at = ?
                 WHERE id = ?'
            );
            $update->execute([$encoded, $status, $weightValue, $userId, date('Y-m-d H:i:s'), $existing['id']]);
        } elseif ($vaccine !== '' || $weightValue !== null) {
            $insert = $this->pdo->prepare(
                'INSERT INTO animal_medical_records
                    (id, animal_id, vaccination_records, vaccination_status, weight_kg, updated_by, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                Database::uuidV4(),
                $animalId,
                $encoded,
                $status,
                $weightValue,
                $userId,
                date('Y-m-d H:i:s'),
            ]);
        }
        if ($heart !== null && $heart !== '') {
            $vital = $this->pdo->prepare(
                'INSERT INTO vitals_log (id, animal_id, heart_rate_bpm, source, recorded_at) VALUES (?, ?, ?, ?, ?)'
            );
            $vital->execute([Database::uuidV4(), $animalId, (int) $heart, 'manual', date('Y-m-d H:i:s')]);
        }
    }

    public function findLiveListing(string $animalId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM adoption_listings
             WHERE animal_id = ? AND status IN ('pending_review', 'approved')
             ORDER BY created_at DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$animalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function insertListing(string $animalId, string $userId): string
    {
        return $this->listings->create([
            'id' => Database::uuidV4(),
            'animal_id' => $animalId,
            'posted_by' => $userId,
            'status' => 'pending_review',
        ]);
    }
}
