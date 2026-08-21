@props(['titulo', 'valor' => null])

{{-- Fila de una lista de definiciones. Muestra un guion cuando no hay dato. --}}
<div class="flex items-baseline justify-between gap-3 py-2">
    <dt class="shrink-0 text-xs text-slate-500">{{ $titulo }}</dt>
    <dd class="text-right text-sm font-medium text-slate-800">
        {{ filled($valor) ? $valor : '-' }}
    </dd>
</div>
