<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a quien puede cargar datos (admin y calidad).
 * El rol "lectura" consulta y reimprime, pero no modifica registros.
 */
class PuedeEditar
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->puedeEditar(), 403, 'Tu usuario solo tiene permiso de lectura.');

        return $next($request);
    }
}
