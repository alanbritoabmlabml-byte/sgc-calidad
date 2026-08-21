<?php

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
        $middleware->alias([
            'editor' => \App\Http\Middleware\PuedeEditar::class,
            'admin' => \App\Http\Middleware\SoloAdmin::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // El certificado publico sigue respondiendo durante un despliegue.
        // Un cliente que escanea el QR de un lote no tiene por que encontrarse
        // con una pagina de mantenimiento.
        $middleware->preventRequestsDuringMaintenance(except: [
            'c/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
