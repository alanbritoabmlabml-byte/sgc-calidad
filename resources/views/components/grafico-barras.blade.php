@props([
    // filas: [['etiqueta' => 'T-2', 'valor' => 12, 'secundario' => 40], ...]
    'filas' => [],
    'sufijo' => '',
    'titulo' => null,
    'detalle' => null,
    // Etiqueta de la columna de valor
    'columna' => 'Valor',
    'columnaSecundaria' => null,
    'tono' => 'pc',
])

@php
    $filas = collect($filas);
    $max = (float) ($filas->max('valor') ?: 1);

    $color = match ($tono) {
        'rojo' => 'bg-rojo-500',
        'ambar' => 'bg-amber-500',
        'verde' => 'bg-emerald-500',
        default => 'bg-pc-600',
    };
@endphp

{{--
    Barras horizontales en HTML puro, sin libreria de graficos: se imprimen
    bien, no dependen de JavaScript y en un ranking de 8 a 10 filas se leen
    igual o mejor que un grafico de verdad.
--}}
<div {{ $attributes->merge(['class' => 'tarjeta overflow-hidden']) }}>
    @if ($titulo)
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <h3 class="text-sm font-semibold text-slate-900">{{ $titulo }}</h3>
            @if ($detalle)
                <p class="mt-0.5 text-xs text-slate-500">{{ $detalle }}</p>
            @endif
        </div>
    @endif

    @if ($filas->isEmpty())
        <p class="px-4 py-8 text-center text-sm text-slate-500">Sin datos en el período.</p>
    @else
        <div class="divide-y divide-slate-100">
            @foreach ($filas as $fila)
                @php $ancho = round(($fila['valor'] / $max) * 100, 1); @endphp
                <div class="px-4 py-2.5 sm:px-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 truncate text-sm font-medium text-slate-800">
                            {{ $fila['etiqueta'] }}
                        </span>
                        <span class="shrink-0 text-sm font-bold tabular-nums text-slate-900">
                            {{ is_float($fila['valor']) ? number_format($fila['valor'], 1, ',', '.') : number_format($fila['valor'], 0, ',', '.') }}{{ $sufijo }}
                            @if ($columnaSecundaria && isset($fila['secundario']) && $fila['secundario'] !== null)
                                <span class="ml-1 text-xs font-normal text-slate-500">
                                    ({{ number_format($fila['secundario'], 1, ',', '.') }}%)
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $color }}" style="width: {{ $ancho }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
