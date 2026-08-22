<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Lot;
use App\Support\FormatoEtiqueta;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hojas de etiquetas QR. Cada etiqueta lleva el QR que resuelve el certificado
 * de calidad del lote, mas los datos legibles para quien la lea sin escanear.
 *
 * Soporta impresora termica Zebra (una etiqueta por pagina, del tamano exacto
 * del rollo) y hoja A4 autoadhesiva.
 */
class EtiquetaController extends Controller
{
    /** Cantidad por defecto y tope. */
    private const CANTIDAD_DEFECTO = 5;

    private const CANTIDAD_MAX = 200;

    public function deInspeccion(Request $request, Inspection $inspeccion): View
    {
        $inspeccion->load(['lot.product', 'lot.sector', 'process', 'machine']);

        abort_unless(
            $inspeccion->estaPublicada(),
            422,
            "La boleta {$inspeccion->code} todavia no fue emitida. Hasta que se emita, el QR de la ".
            'etiqueta no resuelve el certificado, asi que imprimirla no sirve.'
        );

        $cantidad = $this->cantidad($request);

        if ($inspeccion->etiquetas_impresas_at === null) {
            $inspeccion->update(['etiquetas_impresas_at' => now()]);
        }

        return view('etiquetas.hoja', [
            'inspecciones' => collect(array_fill(0, $cantidad, $inspeccion)),
            'titulo' => "Etiquetas de {$inspeccion->code}",
            'origen' => $inspeccion,
            'formato' => $this->formato($request),
            'formatos' => FormatoEtiqueta::catalogo(),
            'cantidad' => $cantidad,
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
            'formato' => $this->formato($request),
            'formatos' => FormatoEtiqueta::catalogo(),
            'cantidad' => $cantidad,
        ]);
    }

    /** @return array<string, mixed> */
    private function formato(Request $request): array
    {
        return FormatoEtiqueta::resolver(
            (string) $request->input('formato', FormatoEtiqueta::ZEBRA_50X30),
            $request->filled('ancho') ? (float) $request->input('ancho') : null,
            $request->filled('alto') ? (float) $request->input('alto') : null,
        );
    }

    private function cantidad(Request $request): int
    {
        $cantidad = $request->integer('cantidad', self::CANTIDAD_DEFECTO);

        return max(1, min($cantidad, self::CANTIDAD_MAX));
    }
}
