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
        // La autorizacion es por permiso, no por rol: un rol solo define el
        // juego inicial de permisos de un usuario.
        $middleware->alias([
            'permiso' => \App\Http\Middleware\Permiso::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // El certificado publico sigue respondiendo durante un despliegue.
        // Un cliente que escanea el QR de un lote no tiene por que encontrarse
        // con una pagina de mantenimiento.
        $middleware->preventRequestsDuringMaintenance(except: [
            'c/*',
        ]);

        // El sistema se sirve detras de un tunel (Cloudflare Tunnel), asi que
        // el esquema y la IP del cliente llegan en las cabeceras X-Forwarded-*.
        // Sin esto Laravel cree que la conexion es http y genera enlaces http
        // dentro de una pagina https, y el navegador del cliente los bloquea.
        //
        // Confiar en todos los proxies es correcto en esta topologia porque el
        // unico que alcanza a la aplicacion es el conector del tunel, que corre
        // en el mismo equipo. Si algun dia se expone el puerto directamente a
        // la red, hay que acotar esta lista a las IP del proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
