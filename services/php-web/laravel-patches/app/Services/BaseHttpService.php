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
        $attempt = 0;

        while ($attempt < $this->retries) {
            try {
                Log::info("HTTP GET", [
                    'url' => $url,
                    'attempt' => $attempt + 1,
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
                $attempt++;
                
                if ($attempt >= $this->retries) {
                    Log::error("HTTP GET failed after {$this->retries} retries", [
                        'url' => $url,
                        'error' => $e->getMessage()
                    ]);
                    throw new ApiException(
                        "Failed to fetch data from {$url}: {$e->getMessage()}",
                        500,
                        $e
                    );
                }

                usleep($this->retryDelay * 1000 * $attempt); // Exponential backoff
            }
        }
    }
}