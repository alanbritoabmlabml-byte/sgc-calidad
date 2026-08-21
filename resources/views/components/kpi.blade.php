@props([
    'titulo',
    'valor',
    // neutro | bien | alerta | mal
    'tono' => 'neutro',
])

@php
    $color = match ($tono) {
        'bien' => 'text-emerald-700',
        'alerta' => 'text-amber-700',
        'mal' => 'text-red-700',
        default => 'text-slate-900',
    };
@endphp

<div {{ $attributes->merge(['class' => 'tarjeta p-4']) }}>
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $titulo }}</p>
    <p class="mt-1 text-2xl font-bold tracking-tight {{ $color }}">{{ $valor }}</p>
</div>
