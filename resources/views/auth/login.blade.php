<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full antialiased text-slate-800">

<div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto w-full max-w-sm">

        <div class="text-center">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-pc-700 text-2xl font-extrabold text-white">
                PC
            </span>
            <h1 class="mt-5 text-xl font-bold tracking-tight text-slate-900">
                Control de Calidad
            </h1>
            <p class="mt-1 text-sm text-slate-500">Plasticos Carmen S.R.L.</p>
        </div>

        <div class="tarjeta mt-8 p-6 sm:p-8">
            @if ($errors->any())
                <div class="mb-5 rounded-lg bg-red-50 p-3 ring-1 ring-red-200">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm font-medium text-red-800">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="etiqueta">Correo</label>
                    <input id="email" name="email" type="email" autocomplete="username" required autofocus
                           value="{{ old('email') }}" class="campo" placeholder="usuario@plasticoscarmen.com">
                </div>

                <div>
                    <label for="password" class="etiqueta">Contrasena</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="campo" placeholder="********">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1"
                           class="h-4 w-4 rounded border-slate-300 text-pc-600 focus:ring-pc-500">
                    Mantener la sesion abierta
                </label>

                <button type="submit" class="btn-primario w-full">Ingresar</button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            El certificado de calidad de un lote se consulta escaneando su codigo QR,
            sin necesidad de ingresar al sistema.
        </p>
    </div>
</div>

</body>
</html>
