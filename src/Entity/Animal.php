<?php

namespace App\Entity;

class Animal
{
    private ?string $id;
    private ?string $name;
    private ?string $species;
    private ?string $breedType;
    private ?string $modelPreset;
    private ?string $sex;
    private ?string $ageEstimate;
    private ?string $birthDate;
    private ?string $colorMarkings;
    private ?string $barangay;
    private ?string $description;
    private ?string $photoUrls;
    private ?string $model3dUrl;
    private ?string $photo360Set;
    private ?string $adoptionStatus;
    private ?string $source;
    private ?string $caseId;
    private ?string $createdBy;
    private ?string $createdAt;
    private ?string $updatedAt;
    private ?string $deletedAt;

    private function __construct()
    {
    }

    public static function fromRow(array $row): self
    {
        $animal = new self();
        $animal->id = $row['id'] ?? null;
        $animal->name = $row['name'] ?? null;
        $animal->species = $row['species'] ?? null;
        $animal->breedType = $row['breed_type'] ?? null;
        $animal->modelPreset = $row['model_preset'] ?? null;
        $animal->sex = $row['sex'] ?? null;
        $animal->ageEstimate = $row['age_estimate'] ?? null;
        $animal->birthDate = $row['birth_date'] ?? null;
        $animal->colorMarkings = $row['color_markings'] ?? null;
        $animal->barangay = $row['barangay'] ?? null;
        $animal->description = $row['description'] ?? null;
        $animal->photoUrls = $row['photo_urls'] ?? null;
        $animal->model3dUrl = $row['model_3d_url'] ?? null;
        $animal->photo360Set = $row['photo_360_set'] ?? null;
        $animal->adoptionStatus = $row['adoption_status'] ?? null;
        $animal->source = $row['source'] ?? null;
        $animal->caseId = $row['case_id'] ?? null;
        $animal->createdBy = $row['created_by'] ?? null;
        $animal->createdAt = $row['created_at'] ?? null;
        $animal->updatedAt = $row['updated_at'] ?? null;
        $animal->deletedAt = $row['deleted_at'] ?? null;
        return $animal;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function species(): ?string
    {
        return $this->species;
    }

    public function breedType(): ?string
    {
        return $this->breedType;
    }

    public function modelPreset(): ?string
    {
        return $this->modelPreset;
    }

    public function sex(): ?string
    {
        return $this->sex;
    }

    public function ageEstimate(): ?string
    {
        return $this->ageEstimate;
    }

    public function birthDate(): ?string
    {
        return $this->birthDate;
    }

    public function colorMarkings(): ?string
    {
        return $this->colorMarkings;
    }

    public function barangay(): ?string
    {
        return $this->barangay;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function photoUrls(): ?string
    {
        return $this->photoUrls;
    }

    public function model3dUrl(): ?string
    {
        return $this->model3dUrl;
    }

    public function photo360Set(): ?string
    {
        return $this->photo360Set;
    }

    public function adoptionStatus(): ?string
    {
        return $this->adoptionStatus;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?string
    {
        return $this->deletedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'species' => $this->species,
            'breed_type' => $this->breedType,
            'model_preset' => $this->modelPreset,
            'sex' => $this->sex,
            'age_estimate' => $this->ageEstimate,
            'birth_date' => $this->birthDate,
            'color_markings' => $this->colorMarkings,
            'barangay' => $this->barangay,
            'description' => $this->description,
            'photo_urls' => $this->photoUrls,
            'model_3d_url' => $this->model3dUrl,
            'photo_360_set' => $this->photo360Set,
            'adoption_status' => $this->adoptionStatus,
            'source' => $this->source,
            'case_id' => $this->caseId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'deleted_at' => $this->deletedAt,
        ];
    }
}
