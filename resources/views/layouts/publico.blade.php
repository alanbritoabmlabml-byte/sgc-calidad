<!DOCTYPE html>
<html lang="es" class="bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo') &middot; Plásticos Carmen</title>
    @vite(['resources/css/app.css'])

    {{--
        La vista hija empuja aca su tamano de pagina. Tiene que ser un layout y
        no un archivo suelto: en un archivo suelto el @stack del head se resuelve
        antes de que el @push del cuerpo se ejecute, y los estilos no salen.
    --}}
    @stack('estilos')
</head>
<body class="antialiased text-slate-800">
    @yield('contenido')
</body>
</html>
