<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUtf8Encoding::class,
            \App\Http\Middleware\TrackUserPresence::class,
        ]);
        $middleware->alias([
            'campus.context' => \App\Http\Middleware\ResolveCampusContext::class,
            'campus.access' => \App\Http\Middleware\EnsureCampusAccess::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'active.user' => \App\Http\Middleware\TrackUserPresence::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Please refresh and try again.',
                ], 419);
            }

            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Your session expired. Please sign in again.']);
        });
    })->create();
