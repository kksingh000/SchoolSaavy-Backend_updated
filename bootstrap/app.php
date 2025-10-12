<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        
        // Register middleware aliases
        $middleware->alias([
            'inject.school' => \App\Http\Middleware\InjectSchoolData::class,
            'json.response' => \App\Http\Middleware\ForceJsonResponse::class,
            'refresh.token' => \App\Http\Middleware\RefreshTokenIfNeeded::class,
            'user.type' => \App\Http\Middleware\CheckUserType::class,
            'super.admin' => \App\Http\Middleware\CheckSuperAdmin::class,
            'school.status' => \App\Http\Middleware\CheckSchoolStatus::class,
            'api.cache' => \App\Http\Middleware\ApiCacheMiddleware::class,
            'track.activity' => \App\Http\Middleware\TrackActivity::class,
        ]);

        // Apply TrackActivity middleware to all API routes
        $middleware->api(append: [
            \App\Http\Middleware\TrackActivity::class,
        ]);
    })
    ->withEvents(discover: [
        __DIR__ . '/../app/Listeners',
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
