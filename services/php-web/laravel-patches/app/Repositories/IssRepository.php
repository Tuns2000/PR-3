<?php


namespace App\Repositories;

use App\DTO\IssPositionDTO;
use Illuminate\Support\Facades\DB;

class IssRepository
{
    /**
     * Получить последнюю позицию МКС из БД
     */
    public function getLastPosition(): ?IssPositionDTO
    {
        $row = DB::table('iss_fetch_log')
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$row) {
            return null;
        }

        return IssPositionDTO::fromArray((array) $row);
    }

    /**
     * Получить историю позиций с фильтрацией
     */
    public function getHistory(?string $startDate = null, ?string $endDate = null, int $limit = 100): array
    {
        $query = DB::table('iss_fetch_log')
            ->orderBy('timestamp', 'desc');

        if ($startDate) {
            $query->where('timestamp', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('timestamp', '<=', $endDate);
        }

        $rows = $query->limit($limit)->get();

        return array_map(
            fn($row) => IssPositionDTO::fromArray((array) $row),
            $rows->toArray()
        );
    }

    /**
     * Сохранить позицию МКС (UPSERT по timestamp)
     */
    public function upsert(array $position): bool
    {
        return DB::table('iss_fetch_log')->upsert(
            [
                'latitude' => $position['latitude'],
                'longitude' => $position['longitude'],
                'altitude' => $position['altitude'],
                'velocity' => $position['velocity'],
                'timestamp' => $position['timestamp'],
                'fetched_at' => now(),
            ],
            ['timestamp'], // Уникальный ключ
            ['latitude', 'longitude', 'altitude', 'velocity', 'fetched_at'] // Обновляемые поля
        );
    }

    /**
     * Получить количество записей за последние N часов
     */
    public function countRecent(int $hours = 24): int
    {
        return DB::table('iss_fetch_log')
            ->where('fetched_at', '>=', now()->subHours($hours))
            ->count();
    }
}