<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssController;
use App\Http\Controllers\OsdrController;
use App\Http\Controllers\JwstController;
use App\Http\Controllers\AstroController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\LegacyController;
use App\Http\Controllers\TelemetryController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Главная страница - дашборд
Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// ISS страницы и API
Route::prefix('iss')->group(function () {
    Route::get('/', [IssController::class, 'index'])->name('iss.index');
    Route::get('/api/last', [IssController::class, 'apiLast'])->name('iss.api.last');
    Route::post('/api/fetch', [IssController::class, 'apiFetch'])->name('iss.api.fetch');
    Route::get('/api/history', [IssController::class, 'apiHistory'])->name('iss.api.history');
});

// OSDR страницы и API
Route::prefix('osdr')->group(function () {
    Route::get('/', [OsdrController::class, 'index'])->name('osdr.index');
    Route::post('/api/sync', [OsdrController::class, 'apiSync'])->name('osdr.api.sync');
    Route::get('/api/list', [OsdrController::class, 'apiList'])->name('osdr.api.list');
});

// JWST API
Route::prefix('jwst')->group(function () {
    Route::get('/api/images/{programId?}', [JwstController::class, 'apiImages'])->name('jwst.api.images');
});

// Astronomy API
Route::prefix('astro')->group(function () {
    Route::get('/', [AstroController::class, 'index'])->name('astro.index');
    Route::get('/api/events', [AstroController::class, 'apiEvents'])->name('astro.api.events');
});

// Proxy для прямых запросов к Rust API (для отладки)
Route::get('/proxy/{path}', [ProxyController::class, 'proxy'])
    ->where('path', '.*')
    ->name('proxy');

// CMS страницы
Route::get('/page/{slug}', [CmsController::class, 'show'])->name('cms.page');

// Legacy CSV/XLSX интерфейс
Route::prefix('legacy')->group(function () {
    Route::get('/', [LegacyController::class, 'index'])->name('legacy.index');
    Route::get('/view/{filename}', [LegacyController::class, 'view'])->name('legacy.view');
});

// Telemetry CSV визуализация
Route::prefix('telemetry')->group(function () {
    Route::get('/', [TelemetryController::class, 'index'])->name('telemetry.index');
    Route::get('/{filename}', [TelemetryController::class, 'show'])->name('telemetry.show');
});
