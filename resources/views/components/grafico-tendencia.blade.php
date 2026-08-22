@props([
    // serie: [['etiqueta' => 'ene 26', 'conformidad' => 88.5, 'total' => 42], ...]
    'serie' => [],
    'titulo' => null,
    'detalle' => null,
])

@php
    $serie = collect($serie);
    $conDatos = $serie->filter(fn (array $p) => $p['conformidad'] !== null);

    // Geometria del SVG. El area de dibujo deja margen para los ejes.
    $ancho = 720;
    $alto = 220;
    $izq = 34;
    $der = 8;
    $arriba = 12;
    $abajo = 30;

    $anchoUtil = $ancho - $izq - $der;
    $altoUtil = $alto - $arriba - $abajo;

    $n = max($serie->count() - 1, 1);

    // Coordenadas. El eje Y va de 0 a 100 por ser un porcentaje.
    $x = fn (int $i): float => $izq + ($anchoUtil * $i / $n);
    $y = fn (float $v): float => $arriba + $altoUtil * (1 - $v / 100);

    $puntos = $serie
        ->map(fn (array $p, int $i) => $p['conformidad'] === null
            ? null
            : ['x' => $x($i), 'y' => $y((float) $p['conformidad']), 'p' => $p])
        ->filter()
        ->values();

    $linea = $puntos->map(fn (array $p) => round($p['x'], 1).','.round($p['y'], 1))->implode(' ');

    $promedio = $conDatos->avg('conformidad');
@endphp

<div {{ $attributes->merge(['class' => 'tarjeta overflow-hidden']) }}>
    @if ($titulo)
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 px-4 py-3 sm:px-5">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">{{ $titulo }}</h3>
                @if ($detalle)
                    <p class="mt-0.5 text-xs text-slate-500">{{ $detalle }}</p>
                @endif
            </div>
            @if ($promedio !== null)
                <p class="text-xs text-slate-500">
                    Promedio del período
                    <span class="font-bold text-slate-900">{{ number_format($promedio, 1, ',', '.') }}%</span>
                </p>
            @endif
        </div>
    @endif

    @if ($puntos->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-slate-500">Sin inspecciones cerradas en el período.</p>
    @else
        <div class="overflow-x-auto p-3">
            <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" class="h-56 w-full min-w-[540px]"
                 role="img" aria-label="{{ $titulo }}">

                {{-- Lineas de referencia cada 25% --}}
                @foreach ([0, 25, 50, 75, 100] as $marca)
                    <line x1="{{ $izq }}" y1="{{ $y($marca) }}" x2="{{ $ancho - $der }}" y2="{{ $y($marca) }}"
                          stroke="#e2e8f0" stroke-width="1"
                          @if ($marca === 0) stroke="#cbd5e1" @endif />
                    <text x="{{ $izq - 6 }}" y="{{ $y($marca) + 3.5 }}" text-anchor="end"
                          font-size="9" fill="#94a3b8">{{ $marca }}%</text>
                @endforeach

                {{-- Banda objetivo: 90% o mas se considera bueno --}}
                <rect x="{{ $izq }}" y="{{ $y(100) }}" width="{{ $anchoUtil }}"
                      height="{{ $y(90) - $y(100) }}" fill="#10b981" opacity="0.07"/>
                <line x1="{{ $izq }}" y1="{{ $y(90) }}" x2="{{ $ancho - $der }}" y2="{{ $y(90) }}"
                      stroke="#10b981" stroke-width="1" stroke-dasharray="4 3" opacity="0.6"/>

                {{-- Linea de conformidad --}}
                <polyline class="anim-trazo" points="{{ $linea }}" fill="none" stroke="#0f3c91" stroke-width="2.5"
                          stroke-linejoin="round" stroke-linecap="round"/>

                {{-- Puntos --}}
                @foreach ($puntos as $p)
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.5" fill="#0f3c91"/>
                    <title>{{ $p['p']['etiqueta'] }}: {{ number_format($p['p']['conformidad'], 1, ',', '.') }}% ({{ $p['p']['total'] }} inspecciones)</title>
                @endforeach

                {{-- Etiquetas del eje X: una de cada dos para que no se amontonen --}}
                @foreach ($serie as $i => $punto)
                    @if ($i % 2 === 0 || $serie->count() <= 6)
                        <text x="{{ $x($i) }}" y="{{ $alto - 10 }}" text-anchor="middle"
                              font-size="9" fill="#94a3b8">{{ $punto['etiqueta'] }}</text>
                    @endif
                @endforeach
            </svg>
        </div>
    @endif
</div>
