<?php

namespace App\Entity;

class Report
{
    private ?string $id;
    private ?string $residentId;
    private ?string $animalDescription;
    private ?string $species;
    private ?string $photoUrls;
    private ?string $latitude;
    private ?string $longitude;
    private ?string $addressText;
    private ?string $contentHash;
    private ?string $duplicateOfReportId;
    private ?string $validationStatus;
    private ?string $status;
    private ?string $dismissReason;
    private ?string $verifiedBy;
    private ?string $verifiedAt;
    private ?string $createdAt;

    private function __construct()
    {
    }

    public static function fromRow(array $row): self
    {
        $report = new self();
        $report->id = $row['id'] ?? null;
        $report->residentId = $row['resident_id'] ?? null;
        $report->animalDescription = $row['animal_description'] ?? null;
        $report->species = $row['species'] ?? null;
        $report->photoUrls = $row['photo_urls'] ?? null;
        $report->latitude = $row['latitude'] ?? null;
        $report->longitude = $row['longitude'] ?? null;
        $report->addressText = $row['address_text'] ?? null;
        $report->contentHash = $row['content_hash'] ?? null;
        $report->duplicateOfReportId = $row['duplicate_of_report_id'] ?? null;
        $report->validationStatus = $row['validation_status'] ?? null;
        $report->status = $row['status'] ?? null;
        $report->dismissReason = $row['dismiss_reason'] ?? null;
        $report->verifiedBy = $row['verified_by'] ?? null;
        $report->verifiedAt = $row['verified_at'] ?? null;
        $report->createdAt = $row['created_at'] ?? null;
        return $report;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function residentId(): ?string
    {
        return $this->residentId;
    }

    public function animalDescription(): ?string
    {
        return $this->animalDescription;
    }

    public function species(): ?string
    {
        return $this->species;
    }

    public function photoUrls(): ?string
    {
        return $this->photoUrls;
    }

    public function latitude(): ?string
    {
        return $this->latitude;
    }

    public function longitude(): ?string
    {
        return $this->longitude;
    }

    public function addressText(): ?string
    {
        return $this->addressText;
    }

    public function contentHash(): ?string
    {
        return $this->contentHash;
    }

    public function duplicateOfReportId(): ?string
    {
        return $this->duplicateOfReportId;
    }

    public function validationStatus(): ?string
    {
        return $this->validationStatus;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    public function dismissReason(): ?string
    {
        return $this->dismissReason;
    }

    public function verifiedBy(): ?string
    {
        return $this->verifiedBy;
    }

    public function verifiedAt(): ?string
    {
        return $this->verifiedAt;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'resident_id' => $this->residentId,
            'animal_description' => $this->animalDescription,
            'species' => $this->species,
            'photo_urls' => $this->photoUrls,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address_text' => $this->addressText,
            'content_hash' => $this->contentHash,
            'duplicate_of_report_id' => $this->duplicateOfReportId,
            'validation_status' => $this->validationStatus,
            'status' => $this->status,
            'dismiss_reason' => $this->dismissReason,
            'verified_by' => $this->verifiedBy,
            'verified_at' => $this->verifiedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
