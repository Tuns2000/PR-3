<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ApiException;

abstract class BaseHttpService
{
    protected int $timeout = 30;
    protected int $retries = 3;
    protected int $retryDelay = 1000; // ms

    /**
     * Выполнить GET запрос с retry стратегией
     */
    protected function get(string $url, array $params = []): array
    {
        try {
            Log::info("HTTP GET", [
                'url' => $url,
                'params' => $params
            ]);

            $response = Http::timeout($this->timeout)
                ->retry($this->retries, $this->retryDelay)
                ->get($url, $params);

            if ($response->successful()) {
                return $response->json();
            }

            throw new ApiException(
                "HTTP {$response->status()}: {$response->body()}",
                $response->status()
            );

        } catch (\Exception $e) {
            Log::error("HTTP GET failed", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw new ApiException(
                "Failed to fetch data from {$url}: {$e->getMessage()}",
                500,
                $e
            );
        }
    }
}