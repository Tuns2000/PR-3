<?php


namespace App\Repositories;

use App\DTO\OsdrDatasetDTO;
use Illuminate\Support\Facades\DB;

class OsdrRepository
{
    /**
     * Получить все датасеты с лимитом
     */
    public function getAll(int $limit = 50): array
    {
        $rows = DB::table('osdr_items')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(
            fn($row) => OsdrDatasetDTO::fromArray((array) $row),
            $rows->toArray()
        );
    }

    /**
     * Найти датасет по ID
     */
    public function findById(int $id): ?OsdrDatasetDTO
    {
        $row = DB::table('osdr_items')
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        return OsdrDatasetDTO::fromArray((array) $row);
    }

    /**
     * Найти датасет по dataset_id
     */
    public function findByDatasetId(string $datasetId): ?OsdrDatasetDTO
    {
        $row = DB::table('osdr_items')
            ->where('dataset_id', $datasetId)
            ->first();

        if (!$row) {
            return null;
        }

        return OsdrDatasetDTO::fromArray((array) $row);
    }

    /**
     * Сохранить или обновить датасет (UPSERT по dataset_id)
     */
    public function upsert(array $dataset): bool
    {
        return DB::table('osdr_items')->upsert(
            [
                'dataset_id' => $dataset['dataset_id'],
                'title' => $dataset['title'],
                'description' => $dataset['description'] ?? null,
                'release_date' => $dataset['release_date'] ?? null,
                'updated_at' => now(),
            ],
            ['dataset_id'], // Уникальный ключ
            ['title', 'description', 'release_date', 'updated_at'] // Обновляемые поля
        );
    }

    /**
     * Получить количество датасетов
     */
    public function count(): int
    {
        return DB::table('osdr_items')->count();
    }

    /**
     * Поиск по названию (LIKE)
     */
    public function search(string $query, int $limit = 20): array
    {
        $rows = DB::table('osdr_items')
            ->where('title', 'ILIKE', "%{$query}%")
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(
            fn($row) => OsdrDatasetDTO::fromArray((array) $row),
            $rows->toArray()
        );
    }
}