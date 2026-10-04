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
