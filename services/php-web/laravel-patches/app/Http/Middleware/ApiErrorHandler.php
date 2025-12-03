<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ApiErrorHandler
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = uniqid('req_', true);
        $request->headers->set('X-Trace-ID', $traceId);

        Log::info('API Request', [
            'trace_id' => $traceId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
        ]);

        try {
            $response = $next($request);

            // Добавляем Trace ID в заголовки ответа
            $response->headers->set('X-Trace-ID', $traceId);

            return $response;
        } catch (\Exception $e) {
            Log::error('API Error', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
                    'trace_id' => $traceId
                ]
            ], 200); // Всегда HTTP 200
        }
    }
}