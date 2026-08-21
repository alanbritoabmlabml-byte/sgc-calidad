@props(['titulo', 'valor' => null, 'mono' => false, 'ancho' => 'auto'])

{{--
    Campo en linea con linea de puntos, como en el formulario preimpreso:
    "Fecha de corte de telar: 13/08/2026 ................"
--}}
<div class="flex items-baseline gap-1.5 {{ $ancho === 'full' ? 'sm:col-span-2 lg:col-span-3' : '' }}">
    <span class="shrink-0 text-[10px] text-slate-600">{{ $titulo }}:</span>
    <span class="min-w-0 flex-1 border-b border-dotted border-slate-500 text-[11px] font-bold text-slate-900 {{ $mono ? 'font-mono' : '' }}">
        {{ filled($valor) ? $valor : '' }}
    </span>
</div>
