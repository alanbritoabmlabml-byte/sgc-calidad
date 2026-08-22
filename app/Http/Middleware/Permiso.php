<?php

namespace App\Http\Middleware;

use App\Support\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza por permiso, no por rol.
 *
 * Uso en rutas:  ->middleware('permiso:inspecciones.emitir')
 * Varios permisos, alcanza con tener uno:  'permiso:lotes.crear,lotes.editar'
 */
class Permiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $usuario = $request->user();

        abort_if($usuario === null, 403);

        // Falla ruidosamente ante un permiso mal escrito en una ruta, en lugar
        // de negar el acceso en silencio y mandar a alguien a buscar el error.
        foreach ($permisos as $permiso) {
            if (! in_array($permiso, Permisos::todos(), true)) {
                abort(500, "El permiso \"{$permiso}\" no existe en el catalogo.");
            }
        }

        abort_unless(
            $usuario->puedeAlguno($permisos),
            403,
            'Tu usuario no tiene permiso para esta accion. Consulta con el administrador.'
        );

        return $next($request);
    }
}
