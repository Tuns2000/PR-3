<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AstronomyService extends BaseHttpService
{
    private string $appId;
    private string $appSecret;
    private string $baseUrl = 'https://api.astronomyapi.com/api/v2';

    public function __construct()
    {
        $this->appId = env('ASTRO_APP_ID', '');
        $this->appSecret = env('ASTRO_APP_SECRET', '');
        $this->timeout = 30;
    }

    /**
     * Получить позиции небесных тел через Astronomy API
     */
    public function getEvents(): array
    {
        return Cache::remember('astronomy:positions', 3600, function () { // Кэш 1 час
            $auth = base64_encode("{$this->appId}:{$this->appSecret}");
            
            // Observer parameters (required for positions endpoint)
            // Координаты по умолчанию: Kentucky, USA (из примера документации)
            $latitude = 38.775867;
            $longitude = -84.39733;
            $elevation = 0;
            
            // Date range: текущая дата и следующие 3 дня
            $fromDate = date('Y-m-d'); // Только дата
            $toDate = date('Y-m-d', strtotime('+3 days'));
            $time = date('H:i:s'); // Время отдельно
            
            // Строим URL с query параметрами (все обязательные)
            $url = "{$this->baseUrl}/bodies/positions"
                . "?latitude={$latitude}"
                . "&longitude={$longitude}"
                . "&elevation={$elevation}"
                . "&from_date={$fromDate}"
                . "&to_date={$toDate}"
                . "&time={$time}";
            
            Log::info("Astronomy API request", [
                'url' => $url,
                'auth_length' => strlen($auth),
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Basic {$auth}",
                ])
                ->get($url);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $status = $response->status();
                
                Log::error("Astronomy API failed", [
                    'status' => $status,
                    'body' => $errorBody,
                    'url' => $url,
                ]);
                
                throw new \Exception("Astronomy API error: {$status}");
            }

            $data = $response->json();
            
            if (!isset($data['data']['table']['rows']) || empty($data['data']['table']['rows'])) {
                throw new \Exception('No astronomy data available');
            }

            return $data;
        });
    }

    /**
     * Получить список доступных небесных тел
     */
    public function getBodies(): array
    {
        return Cache::remember('astronomy:bodies', 3600, function () {
            $auth = base64_encode("{$this->appId}:{$this->appSecret}");
            
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Basic {$auth}",
                ])
                ->get("{$this->baseUrl}/bodies");

            if (!$response->successful()) {
                throw new \Exception("Astronomy API failed: {$response->status()}");
            }

            return $response->json();
        });
    }
}