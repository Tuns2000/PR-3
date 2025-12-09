<?php
namespace App\Services;

use App\DTO\JwstImageDTO;
use Illuminate\Support\Facades\Cache;

class JwstService extends BaseHttpService
{
    private string $rustApiUrl;
    private string $jwstApiUrl;
    private string $programId;

    public function __construct()
    {
        $this->rustApiUrl = env('RUST_ISS_URL', 'http://rust_iss:3000');
        $this->jwstApiUrl = env('JWST_HOST', 'https://api.jwstapi.com');
        $this->programId = env('JWST_PROGRAM_ID', '2734');
        $this->timeout = 30;
    }

    /**
     * Получить изображения JWST по программе (с кэшем 30 минут)
     */
    public function getImages(?string $programId = null): array
    {
        $programId = $programId ?? $this->programId;
        
        return Cache::remember("jwst:images:{$programId}", 1800, function () use ($programId) {
            // Сначала пробуем Rust API
            try {
                $data = $this->get("{$this->rustApiUrl}/jwst/images/{$programId}");
                
                if (($data['ok'] ?? false) && isset($data['data'])) {
                    return array_map(
                        fn($item) => JwstImageDTO::fromArray($item),
                        $data['data']
                    );
                }
                
                // Если ok === false или нет data, пробуем fallback
                throw new \Exception('Rust API returned no data');
            } catch (\Exception $e) {
                // Fallback на прямой API JWST
                try {
                    $data = $this->get("{$this->jwstApiUrl}/images/program/{$programId}");
                    
                    return array_map(
                        fn($item) => JwstImageDTO::fromArray($item),
                        $data['body'] ?? []
                    );
                } catch (\Exception $fallbackError) {
                    // Если оба API не работают, возвращаем пустой массив
                    return [];
                }
            }
        });
    }
}