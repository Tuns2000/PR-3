<?php

namespace App\Services;

use App\DTO\OsdrDatasetDTO;
use App\Repositories\OsdrRepository;
use Illuminate\Support\Facades\Cache;

class OsdrService extends BaseHttpService
{
    private string $rustApiUrl;
    private OsdrRepository $repository;

    public function __construct(OsdrRepository $repository)
    {
        $this->rustApiUrl = env('RUST_ISS_URL', 'http://rust_iss:3000');
        $this->repository = $repository;
        $this->timeout = 10;
    }

    /**
     * Синхронизация датасетов из NASA OSDR
     */
    public function syncDatasets(): array
    {
        Cache::forget('osdr:all');
        
        $data = $this->get("{$this->rustApiUrl}/osdr/sync");
        
        if (!$data['success']) {
            throw new \Exception($data['error'] ?? 'Unknown error');
        }

        return $data['data'];
    }

    /**
     * Получить список датасетов (с кэшем 30 минут)
     */
    public function getDatasets(int $limit = 50): array
    {
        return Cache::remember("osdr:all:{$limit}", 1800, function () use ($limit) {
            // Сначала пробуем из Rust API
            try {
                $data = $this->get("{$this->rustApiUrl}/osdr/list");
                
                if (isset($data['success']) && $data['success']) {
                    return array_map(
                        fn($item) => OsdrDatasetDTO::fromArray($item),
                        $data['data']
                    );
                }
            } catch (\Exception $e) {
                // Fallback на БД
                return $this->repository->getAll($limit);
            }

            return [];
        });
    }
}