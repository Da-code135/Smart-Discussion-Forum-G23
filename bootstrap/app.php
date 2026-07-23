<?php

use App\Http\Middleware\ApiSecurityHeaders;
use App\Http\Middleware\CanAdminGroup;
use App\Http\Middleware\IpWhitelist;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsGroupAdmin;
use App\Http\Middleware\IsSystemAdmin;
use App\Http\Middleware\ThrottlePosts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies — required when running behind Render's load balancer
        $middleware->trustProxies(at: '*');

        // Disable TrustHosts — not needed for single-domain deployment
        $middleware->remove(\Illuminate\Http\Middleware\TrustHosts::class);

        // API rate limiting: 60 requests per minute
        $middleware->throttleApi(60, 0);

        // Register middleware aliases
        $middleware->alias([
            'api.security' => ApiSecurityHeaders::class,
            'admin' => IsAdmin::class,
            'system-admin' => IsSystemAdmin::class,
            'group-admin' => IsGroupAdmin::class,
            'can-admin-group' => CanAdminGroup::class,
            'ip-whitelist' => IpWhitelist::class,

            // Anti-flood: action parameter separated by colon, e.g. throttle.posts:reply
            'throttle.posts' => ThrottlePosts::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
