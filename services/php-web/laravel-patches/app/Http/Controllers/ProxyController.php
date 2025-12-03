<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ProxyController extends Controller
{
    private string $rustApiUrl;

    public function __construct()
    {
        $this->rustApiUrl = env('RUST_ISS_URL', 'http://rust_iss:3000');
    }

    /**
     * Прокси для Rust API (без кэширования)
     * Используется для тестирования прямых запросов
     */
    public function proxy(Request $request, string $path): JsonResponse
    {
        try {
            $fullUrl = "{$this->rustApiUrl}/{$path}";
            
            $response = Http::timeout(30)
                ->get($fullUrl, $request->query());

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'PROXY_ERROR',
                    'message' => "Rust API returned HTTP {$response->status()}",
                    'trace_id' => uniqid('prx_', true)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'PROXY_ERROR',
                    'message' => $e->getMessage(),
                    'trace_id' => uniqid('prx_', true)
                ]
            ]);
        }
    }
}
