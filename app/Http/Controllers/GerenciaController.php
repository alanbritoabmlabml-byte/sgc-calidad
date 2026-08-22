<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Lot;
use App\Models\Measurement;
use App\Models\Process;
use App\Support\Avisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Tablero gerencial: la misma informacion que ve Calidad, pero agregada y
 * en tendencia. Responde "como venimos" en lugar de "que hay que hacer ahora".
 */
class GerenciaController extends Controller
{
    /** Ventanas de tiempo ofrecidas. */
    private const PERIODOS = [30 => 'Ultimos 30 dias', 90 => 'Ultimos 90 dias', 365 => 'Ultimo ano'];

    public function __invoke(Request $request): View
    {
        $dias = (int) $request->integer('dias', 90);
        $dias = array_key_exists($dias, self::PERIODOS) ? $dias : 90;

        $hasta = today();
        $desde = $hasta->copy()->subDays($dias - 1);

        $cerradas = Inspection::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', Inspection::PENDIENTE);

        return view('gerencia', [
            'periodos' => self::PERIODOS,
            'dias' => $dias,
            'desde' => $desde,
            'hasta' => $hasta,

            'resumen' => $this->resumen(clone $cerradas),
            'unidades' => $this->unidades(clone $cerradas),
            'serie' => $this->serieMensual(),
            'porProceso' => $this->porProceso($desde, $hasta),
            'porMaquina' => $this->porMaquina($desde, $hasta),
            'porProducto' => $this->porProducto($desde, $hasta),
            'desvios' => $this->desviosPorCaracteristica($desde, $hasta),
            'avisos' => Avisos::todos(),
        ]);
    }

    /** @return array<string, mixed> */
    private function resumen($cerradas): array
    {
        $porEstado = (clone $cerradas)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $total = (int) $porEstado->sum();

        return [
            'total' => $total,
            'conformes' => (int) $porEstado->get(Inspection::CONFORME, 0),
            'observadas' => (int) $porEstado->get(Inspection::OBSERVADO, 0),
            'rechazadas' => (int) $porEstado->get(Inspection::RECHAZADO, 0),
            'conformidad' => $total > 0
                ? round($porEstado->get(Inspection::CONFORME, 0) / $total * 100, 1)
                : null,
            // Aprobado = conforme u observado: el lote pudo seguir.
            'aprobacion' => $total > 0
                ? round(
                    ($porEstado->get(Inspection::CONFORME, 0) + $porEstado->get(Inspection::OBSERVADO, 0))
                    / $total * 100, 1)
                : null,
            'lotesLiberados' => Lot::where('estado', Lot::LIBERADO)->count(),
            'lotesBloqueados' => Lot::where('estado', Lot::BLOQUEADO)->count(),
            'sinEmitir' => Inspection::whereNull('published_at')
                ->where('estado', '!=', Inspection::PENDIENTE)->count(),
        ];
    }

    /** Unidades inspeccionadas y falladas en el periodo. */
    private function unidades($cerradas): array
    {
        $fila = (clone $cerradas)
            ->selectRaw('sum(total_unidades) as u, sum(total_falladas) as f')
            ->first();

        $u = (int) ($fila->u ?? 0);
        $f = (int) ($fila->f ?? 0);

        return [
            'inspeccionadas' => $u,
            'falladas' => $f,
            'buenas' => $u - $f,
            'porcentaje' => $u > 0 ? round($f / $u * 100, 2) : null,
        ];
    }

    /**
     * Conformidad mes a mes de los ultimos 12 meses. Es la vista de tendencia:
     * un mes malo aislado se distingue de una caida sostenida.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serieMensual(): array
    {
        $desde = today()->copy()->startOfMonth()->subMonths(11);

        $filas = Inspection::where('fecha', '>=', $desde)
            ->where('estado', '!=', Inspection::PENDIENTE)
            ->get(['fecha', 'estado'])
            ->groupBy(fn (Inspection $i) => $i->fecha->format('Y-m'));

        $serie = [];

        for ($i = 0; $i < 12; $i++) {
            $mes = $desde->copy()->addMonths($i);
            $clave = $mes->format('Y-m');
            $grupo = $filas->get($clave, collect());
            $total = $grupo->count();

            $serie[] = [
                'clave' => $clave,
                'etiqueta' => mb_substr($mes->locale('es')->monthName, 0, 3).' '.$mes->format('y'),
                'total' => $total,
                'conformes' => $grupo->where('estado', Inspection::CONFORME)->count(),
                'observadas' => $grupo->where('estado', Inspection::OBSERVADO)->count(),
                'rechazadas' => $grupo->where('estado', Inspection::RECHAZADO)->count(),
                'conformidad' => $total > 0
                    ? round($grupo->where('estado', Inspection::CONFORME)->count() / $total * 100, 1)
                    : null,
            ];
        }

        return $serie;
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function porProceso($desde, $hasta)
    {
        $inspecciones = Inspection::with('process')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', Inspection::PENDIENTE)
            ->get(['process_id', 'estado']);

        return Process::orderBy('sector_id')->orderBy('orden')->get()
            ->map(function (Process $p) use ($inspecciones) {
                $grupo = $inspecciones->where('process_id', $p->id);
                $total = $grupo->count();

                return [
                    'nombre' => $p->name,
                    'codigo' => $p->code,
                    'total' => $total,
                    'conformes' => $grupo->where('estado', Inspection::CONFORME)->count(),
                    'observadas' => $grupo->where('estado', Inspection::OBSERVADO)->count(),
                    'rechazadas' => $grupo->where('estado', Inspection::RECHAZADO)->count(),
                    'conformidad' => $total > 0
                        ? round($grupo->where('estado', Inspection::CONFORME)->count() / $total * 100, 1)
                        : null,
                ];
            })
            ->filter(fn (array $f) => $f['total'] > 0)
            ->values();
    }

    /**
     * Maquinas ordenadas por cantidad de inspecciones no conformes u observadas.
     * Sirve para ver si un telar concentra los desvios.
     */
    private function porMaquina($desde, $hasta)
    {
        return Inspection::with('machine')
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNotNull('machine_id')
            ->where('estado', '!=', Inspection::PENDIENTE)
            ->get(['machine_id', 'estado'])
            ->groupBy('machine_id')
            ->map(function ($grupo) {
                $total = $grupo->count();
                $desvios = $grupo->whereIn('estado', [Inspection::OBSERVADO, Inspection::RECHAZADO])->count();

                return [
                    'nombre' => $grupo->first()->machine?->code ?? 'sin maquina',
                    'total' => $total,
                    'desvios' => $desvios,
                    'porcentaje' => round($desvios / $total * 100, 1),
                ];
            })
            ->sortByDesc('desvios')
            ->take(10)
            ->values();
    }

    private function porProducto($desde, $hasta)
    {
        return Inspection::with('lot.product')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', Inspection::PENDIENTE)
            ->get()
            ->filter(fn (Inspection $i) => $i->lot->product !== null)
            ->groupBy(fn (Inspection $i) => $i->lot->product->code)
            ->map(function ($grupo, $codigo) {
                $total = $grupo->count();
                $desvios = $grupo->whereIn('estado', [Inspection::OBSERVADO, Inspection::RECHAZADO])->count();
                $unidades = (int) $grupo->sum('total_unidades');
                $falladas = (int) $grupo->sum('total_falladas');

                return [
                    'nombre' => $codigo,
                    'total' => $total,
                    'desvios' => $desvios,
                    'porcentaje' => round($desvios / $total * 100, 1),
                    'falladas' => $unidades > 0 ? round($falladas / $unidades * 100, 2) : null,
                ];
            })
            ->sortByDesc('desvios')
            ->take(10)
            ->values();
    }

    /**
     * Que caracteristica se sale de especificacion mas seguido. Es la pregunta
     * mas util para decidir donde intervenir el proceso.
     */
    private function desviosPorCaracteristica($desde, $hasta)
    {
        $inspecciones = Inspection::whereBetween('fecha', [$desde, $hasta])->pluck('id');

        if ($inspecciones->isEmpty()) {
            return collect();
        }

        return Measurement::with('parameter.template.process')
            ->whereIn('inspection_id', $inspecciones)
            ->whereNotNull('en_especificacion')
            ->select('test_parameter_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when en_especificacion = 0 then 1 else 0 end) as fuera')
            ->groupBy('test_parameter_id')
            ->get()
            ->map(function (Measurement $m) {
                // El nombre lleva el proceso: "Ancho" existe en Tejido y en Corte
                // y Costura, y sin el proceso la fila no se puede interpretar.
                $proceso = $m->parameter?->template?->process?->name;
                $etiqueta = $m->parameter?->etiqueta ?? 'sin parametro';

                return [
                    'nombre' => $proceso ? "{$etiqueta} · {$proceso}" : $etiqueta,
                    'total' => (int) $m->total,
                    'fuera' => (int) $m->fuera,
                    'porcentaje' => $m->total > 0 ? round($m->fuera / $m->total * 100, 1) : 0,
                ];
            })
            ->filter(fn (array $f) => $f['fuera'] > 0)
            ->sortByDesc('fuera')
            ->take(8)
            ->values();
    }
}
