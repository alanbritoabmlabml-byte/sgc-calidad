<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>

{{--
    Pantalla de ingreso con la identidad de Plasticos Carmen: el azul de la
    marca como fondo y el rojo reservado para el acento, en la misma proporcion
    que el logo (el azul domina, el rojo puntua).
--}}
<body class="h-full antialiased text-slate-800">

<div class="flex min-h-full flex-col lg:flex-row">

    {{-- Panel de marca. En celular se reduce a una franja superior. --}}
    <div class="relative overflow-hidden bg-pc-700 px-6 py-10 lg:flex lg:w-1/2 lg:flex-col lg:justify-center lg:px-16 lg:py-0">

        {{--
            Marca de agua: la marca completa, en blanco y entera.

            Antes habia una "c" dibujada a mano y recortada por el borde, que se
            leia como una letra suelta sin relacion con la marca. Ahora es el
            logo oficial pasado a blanco con un filtro (brightness 0 lo vuelve
            negro conservando la transparencia, invert lo pasa a blanco), a muy
            baja opacidad y contenido dentro del panel.
        --}}
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
            <x-logo variante="marca" alto=""
                    class="w-[65%] max-w-none opacity-[0.06] [filter:brightness(0)_invert(1)]" />
        </div>

        <div class="relative mx-auto max-w-md text-center lg:mx-0 lg:text-left">
            {{-- El simbolo va sobre una placa blanca: es como se usa la marca sobre fondo de color --}}
            <span class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-4 shadow-lg">
                <x-logo variante="marca" alto="h-12 sm:h-14" />
            </span>

            <p class="mt-5 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
                PLÁSTICOS CARMEN
            </p>
            <p class="mt-0.5 text-sm tracking-[0.2em] text-pc-200">
                tecnología en plásticos
            </p>

            <div class="mt-6 h-1 w-16 rounded-full bg-rojo-500 lg:mx-0 mx-auto"></div>

            <h1 class="mt-6 text-lg font-bold text-white sm:text-xl">
                Sistema de Gestión de Calidad
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-pc-100">
                Control de calidad por lotes, trazabilidad por proceso y emisión
                de certificados con código QR.
            </p>
        </div>
    </div>

    {{-- Formulario --}}
    <div class="flex flex-1 items-center justify-center bg-slate-50 px-4 py-12 sm:px-6 lg:w-1/2">
        <div class="w-full max-w-sm">

            <h2 class="text-xl font-bold tracking-tight text-slate-900">Ingresar</h2>
            <p class="mt-1 text-sm text-slate-500">Usá tu cuenta de la empresa.</p>

            @if ($errors->any())
                <div class="mt-6 rounded-lg border-l-4 border-rojo-500 bg-rojo-50 p-3">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm font-medium text-rojo-800">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="email" class="etiqueta">Correo</label>
                    <input id="email" name="email" type="email" autocomplete="username" required autofocus
                           value="{{ old('email') }}" class="campo" placeholder="usuario@plasticoscarmen.com">
                </div>

                <div>
                    <label for="password" class="etiqueta">Contraseña</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="campo" placeholder="••••••••">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1"
                           class="h-4 w-4 rounded border-slate-300 text-pc-700 focus:ring-pc-500">
                    Mantener la sesión abierta
                </label>

                <button type="submit" class="btn-primario w-full">Ingresar</button>
            </form>

            <div class="mt-8 rounded-lg bg-white p-4 ring-1 ring-slate-200">
                <p class="text-xs leading-relaxed text-slate-500">
                    El <strong class="text-slate-700">certificado de calidad</strong> de un lote se
                    consulta escaneando su código QR, sin necesidad de ingresar al sistema.
                </p>
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">
                Plásticos Carmen S.R.L. &middot; Control de Calidad
            </p>
        </div>
    </div>
</div>

</body>
</html>
