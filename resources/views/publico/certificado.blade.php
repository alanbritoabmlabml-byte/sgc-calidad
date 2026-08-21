<!DOCTYPE html>
<html lang="es" class="bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Certificado de calidad {{ $inspeccion->code }} &middot; Plasticos Carmen</title>
    @vite(['resources/css/app.css'])
</head>
<body class="antialiased text-slate-800">

{{--
    Vista publica del certificado. Se llega por el QR de la etiqueta del lote.
    La consultan tanto el personal interno como el cliente, sin iniciar sesion.
--}}

<div class="no-imprimir border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900">Certificado de calidad</p>
            <p class="truncate text-xs text-slate-500">
                Lote <span class="font-mono">{{ $inspeccion->lot->code }}</span>
                &middot; {{ $inspeccion->process->name }}
            </p>
        </div>
        <button type="button" onclick="window.print()" class="btn-primario">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.72 13.829q-.577.05-1.152.115m1.152-.115A24 24 0 0 1 12 13.5c1.797 0 3.564.132 5.28.386m-9.71 5.036v-4.87m0 0a48 48 0 0 0-3.478.397m3.478-.397V8.884m9.71 4.559v4.87m0-4.87c1.152.082 2.293.216 3.42.397M17.28 8.884V4.66a2.25 2.25 0 0 0-2.25-2.25H8.97a2.25 2.25 0 0 0-2.25 2.25v4.224"/>
            </svg>
            Imprimir
        </button>
    </div>
</div>

@if ($inspeccion->estado === \App\Models\Inspection::RECHAZADO)
    {{-- Un resultado no conforme nunca se oculta al que escanea. --}}
    <div class="mx-auto max-w-4xl px-3 pt-4">
        <div class="rounded-lg bg-red-50 p-4 ring-1 ring-red-300">
            <p class="text-sm font-bold text-red-900">Producto NO CONFORME</p>
            <p class="mt-1 text-sm text-red-800">
                Esta inspeccion resulto no conforme. Comunicate con el area de Control de
                Calidad de Plasticos Carmen antes de utilizar el material.
            </p>
        </div>
    </div>
@endif

<main class="mx-auto max-w-4xl px-3 py-5 print:m-0 print:max-w-none print:p-0">
    @include('inspecciones._boleta', ['publico' => true])
</main>

<footer class="no-imprimir mx-auto max-w-4xl px-4 pb-8 text-center text-xs text-slate-400">
    <p>Plasticos Carmen S.R.L. &middot; Control de Calidad</p>
    <p class="mt-1">
        Este certificado corresponde unicamente al lote y proceso identificados arriba.
    </p>
</footer>

</body>
</html>
