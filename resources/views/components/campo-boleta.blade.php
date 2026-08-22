@props(['titulo', 'valor' => null, 'mono' => false, 'ancho' => 'auto'])

{{--
    Campo con linea de puntos, como en el formulario preimpreso.

    En la boleta media carta a lo largo el ancho util es de 96 mm, asi que cada
    columna mide unos 47 mm: el rotulo va ARRIBA del valor y no al lado, porque
    en linea no entran los dos. En los campos de ancho completo si van en linea.
--}}
@if ($ancho === 'full')
    <div class="col-span-2 flex items-baseline gap-1">
        <span class="shrink-0 text-[5.5pt] leading-tight text-slate-600">{{ $titulo }}:</span>
        <span class="min-w-0 flex-1 truncate border-b border-dotted border-slate-500 text-[7pt] font-bold leading-tight text-slate-900 {{ $mono ? 'font-mono' : '' }}">
            {{ filled($valor) ? $valor : '' }}
        </span>
    </div>
@else
    <div class="min-w-0">
        <span class="block text-[5pt] uppercase leading-tight tracking-wide text-slate-500">{{ $titulo }}</span>
        <span class="block truncate border-b border-dotted border-slate-500 text-[7pt] font-bold leading-tight text-slate-900 {{ $mono ? 'font-mono' : '' }}">
            {{ filled($valor) ? $valor : '' }}
        </span>
    </div>
@endif
