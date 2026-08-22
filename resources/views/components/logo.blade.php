@props([
    // marca = solo el simbolo pc | completo = simbolo + "PLASTICOS CARMEN"
    'variante' => 'marca',
    'alto' => 'h-10',
])

@php
    /*
     * Marca de Plasticos Carmen.
     *
     * Si existe el archivo oficial en public/img/, se usa ese. Si no, se dibuja
     * la reconstruccion vectorial de abajo. Para usar el oficial, copiar el
     * archivo a una de estas rutas (en ese orden de preferencia):
     *
     *   public/img/logo-pc.svg          <- ideal: vectorial, nitido al imprimir
     *   public/img/logo-pc.png
     *
     * Y para la version con el texto "PLASTICOS CARMEN":
     *
     *   public/img/logo-pc-completo.svg
     *   public/img/logo-pc-completo.png
     */
    $base = $variante === 'completo' ? 'logo-pc-completo' : 'logo-pc';

    $oficial = collect(['svg', 'png'])
        ->map(fn (string $ext) => "img/{$base}.{$ext}")
        ->first(fn (string $ruta) => is_file(public_path($ruta)));
@endphp

@if ($oficial)
    <img src="{{ asset($oficial) }}" alt="Plasticos Carmen"
         {{ $attributes->merge(['class' => $alto.' w-auto']) }}>
@elseif ($variante === 'completo')
    <svg {{ $attributes->merge(['class' => $alto.' w-auto']) }}
         viewBox="0 0 200 168" xmlns="http://www.w3.org/2000/svg" role="img"
         aria-label="Plasticos Carmen">
        {{-- Simbolo --}}
        <circle cx="55" cy="45" r="31.5" fill="none" stroke="#0F3C91" stroke-width="21"/>
        <rect x="13" y="56" width="21" height="56" fill="#0B2A5E"/>
        <path d="M 168.2 69.1 A 31.5 31.5 0 1 1 168.2 20.9"
              fill="none" stroke="#E4121C" stroke-width="21" stroke-linecap="butt"/>

        {{-- Nombre --}}
        <text x="100" y="152" text-anchor="middle" fill="#E4121C"
              font-family="'Instrument Sans', 'Segoe UI', system-ui, sans-serif"
              font-size="30" font-weight="800" letter-spacing="0.5">PLÁSTICOS CARMEN</text>
    </svg>
@else
    <svg {{ $attributes->merge(['class' => $alto.' w-auto']) }}
         viewBox="0 0 200 118" xmlns="http://www.w3.org/2000/svg" role="img"
         aria-label="Plasticos Carmen">
        {{-- Bowl de la "p" --}}
        <circle cx="55" cy="45" r="31.5" fill="none" stroke="#0F3C91" stroke-width="21"/>
        {{-- Descendente de la "p", en azul mas oscuro como en la marca --}}
        <rect x="13" y="56" width="21" height="56" fill="#0B2A5E"/>
        {{-- "c" abierta a la derecha --}}
        <path d="M 168.2 69.1 A 31.5 31.5 0 1 1 168.2 20.9"
              fill="none" stroke="#E4121C" stroke-width="21" stroke-linecap="butt"/>
    </svg>
@endif
