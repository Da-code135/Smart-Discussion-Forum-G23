<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API rate limiting: 60 requests per minute
        $middleware->throttleApi(60, 0);

        // Register middleware aliases
        $middleware->alias([
            'api.security' => \App\Http\Middleware\ApiSecurityHeaders::class,
            'admin' => \App\Http\Middleware\IsAdmin::class,
            'system-admin' => \App\Http\Middleware\IsSystemAdmin::class,
            'group-admin' => \App\Http\Middleware\IsGroupAdmin::class,
            'can-admin-group' => \App\Http\Middleware\CanAdminGroup::class,
            'ip-whitelist' => \App\Http\Middleware\IpWhitelist::class,

            // Anti-flood: action parameter separated by colon, e.g. throttle.posts:reply
            'throttle.posts' => \App\Http\Middleware\ThrottlePosts::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
