<?php


namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AstronomyService extends BaseHttpService
{
    private string $appId;
    private string $appSecret;

    public function __construct()
    {
        $this->appId = env('ASTRO_APP_ID', '');
        $this->appSecret = env('ASTRO_APP_SECRET', '');
        $this->timeout = 20;
    }

    /**
     * Получить астрономические события (с кэшем 1 час)
     */
    public function getEvents(): array
    {
        return Cache::remember('astronomy:events', 3600, function () {
            $auth = base64_encode("{$this->appId}:{$this->appSecret}");
            
            $response = \Illuminate\Support\Facades\Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Basic {$auth}",
                ])
                ->retry($this->retries, $this->retryDelay)
                ->get('https://api.astronomyapi.com/api/v2/bodies/events');

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Astronomy API failed: {$response->status()}");
        });
    }
}