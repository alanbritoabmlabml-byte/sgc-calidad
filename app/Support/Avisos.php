<?php

namespace App\Support;

use App\Models\Inspection;
use App\Models\Lot;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Avisos del sistema: lo que quedo a medio camino y nadie cerro.
 *
 * El caso central es la boleta sin emitir. Una inspeccion cargada pero no
 * emitida no sirve de nada afuera: el QR de su etiqueta no resuelve, asi que
 * el cliente que escanea no ve el certificado. Es un trabajo hecho que no
 * llega a destino, y sin un aviso no se nota.
 */
class Avisos
{
    /**
     * @return Collection<int, array{
     *     clave: string, titulo: string, detalle: string, cantidad: int,
     *     tono: string, ruta: ?string
     * }>
     */
    public static function todos(): Collection
    {
        return collect([
            self::sinEmitir(),
            self::pendientesDeCierre(),
            self::lotesBloqueados(),
            self::lotesSinInspeccion(),
            self::fichasIncompletas(),
        ])->filter(fn (array $aviso) => $aviso['cantidad'] > 0)->values();
    }

    /** Cantidad total, para la campana de la barra superior. */
    public static function cantidad(): int
    {
        return self::todos()->sum('cantidad');
    }

    /** Cuantos avisos son urgentes (tono rojo). */
    public static function urgentes(): int
    {
        return self::todos()->where('tono', 'rojo')->sum('cantidad');
    }

    /** @return array<string, mixed> */
    private static function sinEmitir(): array
    {
        $total = Inspection::whereNull('published_at')
            ->whereIn('estado', [Inspection::CONFORME, Inspection::OBSERVADO, Inspection::RECHAZADO])
            ->count();

        return [
            'clave' => 'sin_emitir',
            'titulo' => 'Boletas cerradas sin emitir',
            'detalle' => 'La inspeccion esta completa pero la boleta no se emitio, '
                .'asi que el QR de su etiqueta todavia no resuelve el certificado.',
            'cantidad' => $total,
            'tono' => 'rojo',
            'ruta' => route('inspecciones.index', ['estado' => '', 'sin_emitir' => 1]),
        ];
    }

    /** @return array<string, mixed> */
    private static function pendientesDeCierre(): array
    {
        $total = Inspection::where('estado', Inspection::PENDIENTE)->count();

        return [
            'clave' => 'pendientes',
            'titulo' => 'Inspecciones pendientes de cierre',
            'detalle' => 'Se cargaron mediciones pero nadie definio el estado de inspeccion.',
            'cantidad' => $total,
            'tono' => 'ambar',
            'ruta' => route('inspecciones.index', ['estado' => Inspection::PENDIENTE]),
        ];
    }

    /** @return array<string, mixed> */
    private static function lotesBloqueados(): array
    {
        $total = Lot::where('estado', Lot::BLOQUEADO)->count();

        return [
            'clave' => 'bloqueados',
            'titulo' => 'Lotes bloqueados',
            'detalle' => 'Una inspeccion resulto no conforme: el lote no puede avanzar al proceso siguiente.',
            'cantidad' => $total,
            'tono' => 'rojo',
            'ruta' => route('lotes.index', ['estado' => Lot::BLOQUEADO]),
        ];
    }

    /** @return array<string, mixed> */
    private static function lotesSinInspeccion(): array
    {
        $total = Lot::whereDoesntHave('inspections')
            ->where('estado', '!=', Lot::CERRADO)
            ->count();

        return [
            'clave' => 'sin_inspeccion',
            'titulo' => 'Lotes sin ninguna inspeccion',
            'detalle' => 'El lote se creo pero todavia no se le registro ningun control.',
            'cantidad' => $total,
            'tono' => 'ambar',
            'ruta' => route('lotes.index'),
        ];
    }

    /**
     * Productos sin sus nominales completos. Sin peso o gramaje nominal, el
     * ensayo no tiene contra que evaluar y el veredicto queda sin sustento.
     *
     * @return array<string, mixed>
     */
    private static function fichasIncompletas(): array
    {
        $total = Product::where('active', true)
            ->where(fn ($q) => $q
                ->whereNull('ancho_nominal')->orWhereNull('largo_nominal')
                ->orWhereNull('gramaje_nominal')->orWhereNull('peso_nominal'))
            ->count();

        return [
            'clave' => 'ficha_incompleta',
            'titulo' => 'Productos con ficha tecnica incompleta',
            'detalle' => 'Faltan valores nominales. Sin ellos el ensayo no tiene especificacion '
                .'contra la que evaluar y el veredicto queda sin sustento.',
            'cantidad' => $total,
            'tono' => 'ambar',
            'ruta' => route('admin.productos.index', ['incompletos' => 1]),
        ];
    }
}
