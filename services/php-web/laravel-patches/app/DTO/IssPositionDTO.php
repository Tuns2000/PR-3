<?php


namespace App\DTO;

class IssPositionDTO
{
    public function __construct(
        public readonly int $id,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $altitude,
        public readonly float $velocity,
        public readonly string $timestamp,
        public readonly string $fetchedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 0,
            latitude: (float) ($data['latitude'] ?? 0),
            longitude: (float) ($data['longitude'] ?? 0),
            altitude: (float) ($data['altitude'] ?? 0),
            velocity: (float) ($data['velocity'] ?? 0),
            timestamp: $data['timestamp'] ?? '',
            fetchedAt: $data['fetched_at'] ?? $data['fetchedAt'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'altitude' => $this->altitude,
            'velocity' => $this->velocity,
            'timestamp' => $this->timestamp,
            'fetched_at' => $this->fetchedAt,
        ];
    }
}