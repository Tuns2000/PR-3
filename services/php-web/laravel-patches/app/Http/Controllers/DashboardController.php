<?php

namespace App\Http\Controllers;

use App\Services\IssService;
use App\Services\OsdrService;
use App\Services\JwstService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private IssService $issService,
        private OsdrService $osdrService,
        private JwstService $jwstService
    ) {}

    /**
     * Главный дашборд с данными МКС, OSDR и JWST
     */
    public function index(Request $request)
    {
        try {
            // Получаем данные через сервисы (вся логика в Service Layer)
            $issPosition = $this->issService->getLastPosition();
            $osdrDatasets = $this->osdrService->getDatasets(limit: 10);
            // JWST API недоступен (404), используем пустой массив
            $jwstImages = [];

            return view('dashboard', [
                'issPosition' => $issPosition,
                'osdrDatasets' => $osdrDatasets,
                'jwstImages' => $jwstImages,
                'title' => 'Space Dashboard - Cassiopeia'
            ]);
        } catch (\Exception $e) {
            return view('dashboard', [
                'error' => 'Failed to load dashboard data: ' . $e->getMessage(),
                'issPosition' => null,
                'osdrDatasets' => [],
                'jwstImages' => [],
                'title' => 'Space Dashboard - Error'
            ]);
        }
    }
}
