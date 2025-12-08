<?php

namespace App\Http\Controllers;

use App\Services\IssService;
use App\DTO\ApiResponseDTO;
use App\Http\Requests\IssFetchRequest;
use App\Http\Requests\IssHistoryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IssController extends Controller
{
    public function __construct(
        private IssService $issService
    ) {}

    /**
     * Страница МКС с картой и историей
     */
    public function index(Request $request)
    {
        try {
            $issPosition = $this->issService->getLastPosition();
            $history = $this->issService->getHistory(limit: 100);

            return view('iss', [
                'issPosition' => $issPosition,
                'history' => $history,
                'title' => 'ISS Tracker'
            ]);
        } catch (\Exception $e) {
            return view('iss', [
                'error' => $e->getMessage(),
                'issPosition' => null,
                'history' => [],
                'title' => 'ISS Tracker - Error'
            ]);
        }
    }

    /**
     * API: Получить последнюю позицию МКС
     */
    public function apiLast(): JsonResponse
    {
        try {
            $position = $this->issService->getLastPosition();
            
            return response()->json(
                ApiResponseDTO::success($position->toArray())->toArray()
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponseDTO::error('ISS_FETCH_ERROR', $e->getMessage())->toArray()
            );
        }
    }

    /**
     * API: Принудительное обновление позиции МКС
     */
    public function apiFetch(IssFetchRequest $request): JsonResponse
    {
        try {
            $position = $this->issService->fetchPosition();
            
            return response()->json(
                ApiResponseDTO::success($position->toArray())->toArray()
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponseDTO::error('ISS_FETCH_ERROR', $e->getMessage())->toArray()
            );
        }
    }

    /**
     * API: Получить историю позиций с фильтрацией
     */
    public function apiHistory(IssHistoryRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $history = $this->issService->getHistory(
                startDate: $validated['start'] ?? null,
                endDate: $validated['end'] ?? null,
                limit: $validated['limit'] ?? 100
            );

            $data = array_map(fn($item) => $item->toArray(), $history);
            
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
                ApiResponseDTO::error('ISS_HISTORY_ERROR', $e->getMessage())->toArray()
            );
        }
    }
}
