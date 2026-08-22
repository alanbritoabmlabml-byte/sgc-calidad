@props(['titulo', 'valor' => null, 'mono' => false, 'ancho' => 'auto'])

{{--
    Campo con linea de puntos, como en el formulario preimpreso.

    En la boleta apaisada la grilla es de 6 columnas sobre 204 mm, o sea unos
    32 mm por columna: el rotulo va ARRIBA del valor, porque en linea no entran
    los dos ("Fecha de corte de rollo" solo ya ocupa la columna). Con ancho
    "doble" el campo toma dos columnas y el rotulo puede ir en linea.
--}}
@if ($ancho === 'doble')
    <div class="col-span-2 flex items-baseline gap-1">
        <span class="shrink-0 text-[5.5pt] leading-tight text-slate-600">{{ $titulo }}:</span>
        <span class="min-w-0 flex-1 truncate border-b border-dotted border-slate-500 text-[7.5pt] font-bold leading-tight text-slate-900 {{ $mono ? 'font-mono' : '' }}">
            {{ filled($valor) ? $valor : '' }}
        </span>
    </div>
@else
    <div class="min-w-0">
        <span class="block truncate text-[5pt] uppercase leading-tight tracking-wide text-slate-500">{{ $titulo }}</span>
        <span class="block truncate border-b border-dotted border-slate-500 text-[7.5pt] font-bold leading-tight text-slate-900 {{ $mono ? 'font-mono' : '' }}">
            {{ filled($valor) ? $valor : '' }}
        </span>
    </div>
@endif
