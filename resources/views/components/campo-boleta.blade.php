@props(['titulo', 'valor' => null, 'mono' => false, 'ancho' => 'auto'])

{{--
    Campo en linea con linea de puntos, como en el formulario preimpreso:
    "Fecha de corte de telar: 13/08/2026 ................"
    Tamanos ajustados a la boleta de media carta.
--}}
<div class="flex items-baseline gap-1 {{ $ancho === 'full' ? 'col-span-2' : '' }}">
    <span class="shrink-0 text-[6pt] leading-tight text-slate-600">{{ $titulo }}:</span>
    <span class="min-w-0 flex-1 truncate border-b border-dotted border-slate-500 text-[7pt] font-bold leading-tight text-slate-900 {{ $mono ? 'font-mono' : '' }}">
        {{ filled($valor) ? $valor : '' }}
    </span>
</div>
