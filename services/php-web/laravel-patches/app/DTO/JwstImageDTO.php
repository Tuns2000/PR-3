<?php

namespace App\DTO;

class JwstImageDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $observation_id,
        public readonly string $program,
        public readonly mixed $details,
        public readonly string $file_type,
        public readonly string $thumbnail,
        public readonly string $location
    ) {}

    public static function fromArray(array $data): self
    {
        // details может быть строкой или объектом (массивом)
        $details = $data['details'] ?? null;
        if (is_array($details)) {
            $details = (object) $details;
        }
        
        return new self(
            id: $data['id'] ?? '',
            observation_id: $data['observation_id'] ?? $data['observationId'] ?? '',
            program: $data['program'] ?? '',
            details: $details,
            file_type: $data['file_type'] ?? $data['fileType'] ?? 'jpg',
            thumbnail: $data['thumbnail'] ?? '',
            location: $data['location'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'observation_id' => $this->observation_id,
            'program' => $this->program,
            'details' => $this->details,
            'file_type' => $this->file_type,
            'thumbnail' => $this->thumbnail,
            'location' => $this->location,
        ];
    }
}