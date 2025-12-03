<?php

namespace App\Http\Controllers;

use App\Services\OsdrService;
use App\DTO\ApiResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OsdrController extends Controller
{
    public function __construct(
        private OsdrService $osdrService
    ) {}

    /**
     * Страница NASA OSDR датасетов
     */
    public function index(Request $request)
    {
        try {
            $datasets = $this->osdrService->getDatasets(limit: 50);

            return view('osdr', [
                'datasets' => $datasets,
                'title' => 'NASA OSDR Datasets'
            ]);
        } catch (\Exception $e) {
            return view('osdr', [
                'error' => $e->getMessage(),
                'datasets' => [],
                'title' => 'NASA OSDR - Error'
            ]);
        }
    }

    /**
     * API: Синхронизация датасетов из NASA OSDR
     */
    public function apiSync(): JsonResponse
    {
        try {
            $result = $this->osdrService->syncDatasets();
            
            return response()->json(
                ApiResponseDTO::success($result)->toArray()
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponseDTO::error('OSDR_SYNC_ERROR', $e->getMessage())->toArray()
            );
        }
    }

    /**
     * API: Получить список датасетов
     */
    public function apiList(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:200'
            ]);

            $datasets = $this->osdrService->getDatasets(
                limit: $validated['limit'] ?? 50
            );

            $data = array_map(fn($item) => $item->toArray(), $datasets);
            
            return response()->json(
                ApiResponseDTO::success($data)->toArray()
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(
                ApiResponseDTO::error('VALIDATION_ERROR', $e->getMessage())->toArray(),
                422
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponseDTO::error('OSDR_LIST_ERROR', $e->getMessage())->toArray()
            );
        }
    }
}
