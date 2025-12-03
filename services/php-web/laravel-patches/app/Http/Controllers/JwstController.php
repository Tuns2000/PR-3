<?php


namespace App\Http\Controllers;

use App\Services\JwstService;
use App\DTO\ApiResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JwstController extends Controller
{
    public function __construct(
        private JwstService $jwstService
    ) {}

    /**
     * API: Получить изображения JWST по программе
     */
    public function apiImages(Request $request, ?string $programId = null): JsonResponse
    {
        try {
            $images = $this->jwstService->getImages($programId);

            $data = array_map(fn($item) => $item->toArray(), $images);
            
            return response()->json(
                ApiResponseDTO::success($data)->toArray()
            );
        } catch (\Exception $e) {
            return response()->json(
                ApiResponseDTO::error('JWST_FETCH_ERROR', $e->getMessage())->toArray()
            );
        }
    }
}