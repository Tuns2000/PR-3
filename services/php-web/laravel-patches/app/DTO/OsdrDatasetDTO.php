<?php


namespace App\DTO;

class OsdrDatasetDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $datasetId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $releaseDate,
        public readonly string $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 0,
            datasetId: $data['dataset_id'] ?? $data['datasetId'] ?? '',
            title: $data['title'] ?? 'Untitled',
            description: $data['description'] ?? null,
            releaseDate: $data['release_date'] ?? $data['releaseDate'] ?? null,
            updatedAt: $data['updated_at'] ?? $data['updatedAt'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'dataset_id' => $this->datasetId,
            'title' => $this->title,
            'description' => $this->description,
            'release_date' => $this->releaseDate,
            'updated_at' => $this->updatedAt,
        ];
    }
}