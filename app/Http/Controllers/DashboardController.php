<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Lot;
use App\Models\Sector;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $sectores = Sector::where('active', true)->orderBy('name')->get();

        $hoy = today();
        $desde = $hoy->copy()->subDays(29);

        $delPeriodo = Inspection::whereBetween('fecha', [$desde, $hoy]);

        $porEstado = (clone $delPeriodo)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $cerradas = $porEstado->except(Inspection::PENDIENTE)->sum();
        $conformes = $porEstado->get(Inspection::CONFORME, 0);

        return view('dashboard', [
            'sectores' => $sectores,

            'inspeccionesHoy' => Inspection::whereDate('fecha', $hoy)->count(),
            'lotesAbiertos' => Lot::where('estado', Lot::ABIERTO)->count(),
            'lotesBloqueados' => Lot::where('estado', Lot::BLOQUEADO)->count(),
            'pendientes' => Inspection::where('estado', Inspection::PENDIENTE)->count(),

            // Porcentaje de conformidad de los ultimos 30 dias, sobre inspecciones cerradas.
            'conformidad' => $cerradas > 0 ? round($conformes / $cerradas * 100, 1) : null,
            'porEstado' => $porEstado,
            'periodo' => [$desde, $hoy],

            'ultimas' => Inspection::with(['lot.product', 'process', 'machine'])
                ->latest('id')
                ->limit(10)
                ->get(),

            'bloqueados' => Lot::with(['product', 'sector'])
                ->where('estado', Lot::BLOQUEADO)
                ->latest('fecha')
                ->limit(5)
                ->get(),
        ]);
    }
}
