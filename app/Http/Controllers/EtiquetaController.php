<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hojas de etiquetas QR para imprimir en papel autoadhesivo.
 * Cada etiqueta lleva el QR que resuelve el certificado de calidad del lote.
 */
class EtiquetaController extends Controller
{
    /** Cantidad de etiquetas por defecto y tope por hoja. */
    private const CANTIDAD_DEFECTO = 12;

    private const CANTIDAD_MAX = 120;

    public function deInspeccion(Request $request, Inspection $inspeccion): View
    {
        $inspeccion->load(['lot.product', 'lot.sector', 'process', 'machine']);

        $cantidad = $this->cantidad($request);

        // Se registra que las etiquetas se generaron, para auditoria.
        if ($inspeccion->etiquetas_impresas_at === null) {
            $inspeccion->update(['etiquetas_impresas_at' => now()]);
        }

        return view('etiquetas.hoja', [
            'inspecciones' => collect(array_fill(0, $cantidad, $inspeccion)),
            'titulo' => "Etiquetas de {$inspeccion->code}",
            'origen' => $inspeccion,
        ]);
    }

    /** Una etiqueta por cada inspeccion emitida del lote. */
    public function deLote(Request $request, Lot $lote): View
    {
        $lote->load(['product', 'sector', 'inspections.process', 'inspections.machine']);

        $emitidas = $lote->inspections
            ->filter(fn (Inspection $i) => $i->estaPublicada())
            ->sortBy(fn (Inspection $i) => $i->process->orden)
            ->values();

        abort_if(
            $emitidas->isEmpty(),
            422,
            "El lote {$lote->code} todavia no tiene ninguna boleta emitida, no hay QR que imprimir."
        );

        $cantidad = $this->cantidad($request);

        // Se repite el juego completo de boletas del lote tantas veces como se pida.
        $etiquetas = collect()
            ->pad($cantidad, null)
            ->flatMap(fn () => $emitidas)
            ->take(self::CANTIDAD_MAX);

        return view('etiquetas.hoja', [
            'inspecciones' => $etiquetas,
            'titulo' => "Etiquetas del lote {$lote->code}",
            'origen' => $lote,
        ]);
    }

    private function cantidad(Request $request): int
    {
        $cantidad = $request->integer('cantidad', self::CANTIDAD_DEFECTO);

        return max(1, min($cantidad, self::CANTIDAD_MAX));
    }
}
