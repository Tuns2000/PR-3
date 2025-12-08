<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // API endpoints (stateless, no CSRF needed)
        'api/*',
        '/iss/api/*',
        '/osdr/api/*',
        '/astro/api/*',
        '/proxy/*',
        
        // Legacy upload endpoint (training purposes - NOT RECOMMENDED in production)
        '/upload',
    ];
}
