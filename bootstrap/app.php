<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureGatewayRole;
use App\Http\Middleware\LogRequestLatency;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureGatewayRole::class]);
        // Correlation id first so every log line of the request carries it.
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->prepend(LogRequestLatency::class);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->render(function (NotFoundHttpException $e) {
            if ($e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Registro não encontrado.'], 404);
            }
        });
    })->create();
