<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class TelemetryController extends Controller
{
    private string $csvDir = '/data/csv';

    public function index()
    {
        $files = collect(File::files($this->csvDir))
            ->filter(fn($file) => str_ends_with($file->getFilename(), '.csv'))
            ->map(fn($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ])
            ->sortByDesc('modified')
            ->values();

        return view('telemetry', [
            'files' => $files,
            'title' => 'Pascal Legacy Telemetry'
        ]);
    }

    public function show(string $filename)
    {
        $filepath = $this->csvDir . '/' . $filename;

        if (!File::exists($filepath) || !str_ends_with($filename, '.csv')) {
            abort(404, 'CSV file not found');
        }

        $data = [];
        $headers = [];

        if (($handle = fopen($filepath, 'r')) !== false) {
            $headers = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                $data[] = array_combine($headers, $row);
            }
            
            fclose($handle);
        }

        return view('telemetry-view', [
            'filename' => $filename,
            'headers' => $headers,
            'data' => $data,
            'title' => "Telemetry: $filename"
        ]);
    }
}
