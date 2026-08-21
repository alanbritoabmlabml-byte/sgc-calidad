@props(['titulo', 'valor' => null, 'mono' => false])

{{-- Campo de la boleta impresa: rotulo arriba, valor subrayado, como en el formulario en papel. --}}
<div>
    <dt class="text-[9px] font-medium uppercase leading-tight tracking-wide text-slate-500">{{ $titulo }}</dt>
    <dd class="border-b border-dotted border-slate-400 pb-0.5 text-[11px] font-semibold text-slate-900 {{ $mono ? 'font-mono' : '' }}">
        {{ filled($valor) ? $valor : '-' }}
    </dd>
</div>
