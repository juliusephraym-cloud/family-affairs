<?php

use App\Exceptions\DomainRuleException;
use App\Http\Middleware\EnsureActiveMember;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, SecurityHeaders::class]);
        $middleware->alias(['member' => EnsureActiveMember::class]);
        $middleware->validateCsrfTokens(except: ['webhooks/nextsms']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // Behind nginx on the VPS
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Business-rule refusals are shown to the user as a friendly message, never a 500.
        $exceptions->render(function (DomainRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*', 'webhooks/*') || $request->expectsJson());
    })->create();
