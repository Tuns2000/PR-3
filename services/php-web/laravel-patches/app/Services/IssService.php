<?php


namespace App\Services;

use App\DTO\IssPositionDTO;
use App\Repositories\IssRepository;
use Illuminate\Support\Facades\Cache;

class IssService extends BaseHttpService
{
    private string $rustApiUrl;
    private IssRepository $repository;

    public function __construct(IssRepository $repository)
    {
        $this->rustApiUrl = env('RUST_ISS_URL', 'http://rust_iss:3000');
        $this->repository = $repository;
        $this->timeout = 10;
    }

    /**
     * Получить последнюю позицию МКС (с кэшем 5 минут)
     */
    public function getLastPosition(): IssPositionDTO
    {
        return Cache::remember('iss:last', 300, function () {
            $data = $this->get("{$this->rustApiUrl}/iss/last");
            
            if (!$data['ok']) {
                throw new \Exception($data['error']['message'] ?? 'Unknown error');
            }

            return IssPositionDTO::fromArray($data['data']);
        });
    }

    /**
     * Принудительное обновление позиции МКС
     */
    public function fetchPosition(): IssPositionDTO
    {
        Cache::forget('iss:last');
        
        $data = $this->get("{$this->rustApiUrl}/iss/fetch");
        
        if (!$data['ok']) {
            throw new \Exception($data['error']['message'] ?? 'Unknown error');
        }

        return IssPositionDTO::fromArray($data['data']);
    }

    /**
     * Получить историю позиций с фильтрацией
     */
    public function getHistory(?string $startDate = null, ?string $endDate = null, int $limit = 100): array
    {
        $params = ['limit' => $limit];
        
        if ($startDate) {
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $params['end_date'] = $endDate;
        }

        $data = $this->get("{$this->rustApiUrl}/iss/history", $params);
        
        if (!$data['ok']) {
            throw new \Exception($data['error']['message'] ?? 'Unknown error');
        }

        return array_map(
            fn($item) => IssPositionDTO::fromArray($item),
            $data['data']
        );
    }
}