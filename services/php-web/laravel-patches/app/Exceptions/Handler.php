<?php


namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): JsonResponse|\Illuminate\Http\Response
    {
        // Если запрос к API, возвращаем JSON
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->renderApiException($e);
        }

        return parent::render($request, $e);
    }

    /**
     * Преобразовать исключение в единый формат API
     */
    private function renderApiException(Throwable $e): JsonResponse
    {
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        if ($e instanceof ApiException) {
            return response()->json($e->toArray(), 200); // Всегда HTTP 200
        }

        // Для остальных ошибок
        return response()->json([
            'ok' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
                'trace_id' => uniqid('err_', true)
            ]
        ], 200); // Всегда HTTP 200
    }
}