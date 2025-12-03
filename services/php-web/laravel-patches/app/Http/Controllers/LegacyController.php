<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LegacyController extends Controller
{
    /**
     * Страница со списком CSV/XLSX файлов
     */
    public function index(Request $request)
    {
        $csvDir = env('CSV_OUT_DIR', '/data/csv');
        
        // Проверка существования директории
        if (!File::exists($csvDir)) {
            return view('legacy.index', [
                'files' => [],
                'error' => 'CSV directory not found: ' . $csvDir
            ]);
        }
        
        // Получение списка файлов (CSV + XLSX)
        $allFiles = File::files($csvDir);
        $files = collect($allFiles)
            ->filter(fn($file) => Str::endsWith($file->getFilename(), ['.csv', '.xlsx']))
            ->map(function ($file) {
                return [
                    'name' => $file->getFilename(),
                    'size' => $this->formatBytes($file->getSize()),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    'type' => Str::endsWith($file->getFilename(), '.xlsx') ? 'XLSX' : 'CSV',
                ];
            })
            ->sortByDesc('modified')
            ->values();
        
        // Пагинация
        $page = $request->get('page', 1);
        $perPage = 20;
        $total = $files->count();
        $files = $files->forPage($page, $perPage);
        
        return view('legacy.index', [
            'files' => $files,
            'total' => $total,
            'currentPage' => $page,
            'lastPage' => ceil($total / $perPage),
            'csvDir' => $csvDir,
        ]);
    }
    
    /**
     * Просмотр содержимого CSV файла
     */
    public function view(Request $request, $filename)
    {
        $csvDir = env('CSV_OUT_DIR', '/data/csv');
        $filePath = $csvDir . '/' . $filename;
        
        // Проверка безопасности (защита от path traversal)
        if (!Str::endsWith($filename, ['.csv', '.xlsx']) || Str::contains($filename, '..')) {
            abort(403, 'Invalid file type');
        }
        
        if (!File::exists($filePath)) {
            abort(404, 'File not found');
        }
        
        // Чтение CSV
        if (Str::endsWith($filename, '.csv')) {
            $content = File::get($filePath);
            $lines = explode("\n", $content);
            $data = array_map(fn($line) => str_getcsv($line), $lines);
            
            return view('legacy.view', [
                'filename' => $filename,
                'headers' => $data[0] ?? [],
                'rows' => array_slice($data, 1),
                'type' => 'CSV',
            ]);
        }
        
        // XLSX скачивание
        if (Str::endsWith($filename, '.xlsx')) {
            return response()->download($filePath);
        }
    }
    
    /**
     * Форматирование размера файла
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}