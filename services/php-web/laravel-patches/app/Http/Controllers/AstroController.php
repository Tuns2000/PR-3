<?php

namespace App\Http\Controllers;

use App\Services\AstronomyService;
use App\DTO\ApiResponseDTO;
use Illuminate\Http\JsonResponse;

class AstroController extends Controller
{
    public function __construct(
        private AstronomyService $astronomyService
    ) {}

    /**
     * Страница: Астрономические события
     */
    public function index()
    {
        try {
            $events = $this->astronomyService->getEvents();
            
            return view('astro', [
                'title' => 'Astronomy Events',
                'events' => $events
            ]);
        } catch (\Exception $e) {
            return view('astro', [
                'title' => 'Astronomy Events',
                'error' => $e->getMessage(),
                'events' => []
            ]);
        }
    }

    /**
     * API: Получить астрономические события
     */
    public function apiEvents(): JsonResponse
    {
        try {
            $events = $this->astronomyService->getEvents();
            
            return response()->json(
                ApiResponseDTO::success($events)->toArray()
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponseDTO::error('ASTRONOMY_FETCH_ERROR', $e->getMessage())->toArray()
            );
        }
    }
}
