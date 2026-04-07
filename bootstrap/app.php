<?php

use App\Http\Middleware\ExtendChatRequestTimeout;
use App\Http\Responses\ChatEnvelope;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'chat.timeout' => ExtendChatRequestTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ProviderOverloadedException $_, Request $request) {
            if ($request->routeIs('chat')) {
                return response()->json(ChatEnvelope::providerOverloaded(), 503);
            }
        });

        $exceptions->render(function (RateLimitedException $_, Request $request) {
            if ($request->routeIs('chat')) {
                return response()->json(ChatEnvelope::providerRateLimited(), 429);
            }
        });
    })->create();
