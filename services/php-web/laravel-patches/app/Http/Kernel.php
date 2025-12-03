<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     */
    protected $middleware = [
        // ...existing middleware...
    ];

    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            // ...existing middleware...
        ],

        'api' => [
            \App\Http\Middleware\ApiErrorHandler::class, // ✅ Добавлен
        ],
    ];

    /**
     * The application's route middleware.
     */
    protected $routeMiddleware = [
        // ...existing middleware...
    ];
}