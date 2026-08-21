<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use Illuminate\View\View;

/**
 * Vista publica del certificado de calidad, sin autenticacion.
 * Es el destino del codigo QR impreso en la etiqueta del lote.
 */
class CertificadoController extends Controller
{
    public function show(string $token): View
    {
        $inspeccion = Inspection::where('public_token', $token)
            ->with([
                'lot.product', 'lot.sector', 'lot.machine', 'lot.sourceLot.product',
                'process', 'template.parameters', 'machine', 'measurements',
            ])
            ->firstOrFail();

        // Mientras la boleta no este emitida, el QR no resuelve nada:
        // evita que una etiqueta impresa por adelantado exponga datos en borrador.
        abort_unless($inspeccion->estaPublicada(), 404);

        return view('publico.certificado', [
            'inspeccion' => $inspeccion,
            // El resumen omite la observacion interna: eso no sale de la empresa.
            'resumen' => $inspeccion->resumen(),
        ]);
    }
}
