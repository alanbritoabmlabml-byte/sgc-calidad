<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe la configuracion del sistema (plantillas, catalogos, usuarios)
 * al rol administrador.
 */
class SoloAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->esAdmin(), 403, 'Esta seccion es solo para administradores.');

        return $next($request);
    }
}
