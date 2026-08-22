<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Control de Calidad') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('estilos')
</head>
<body class="h-full antialiased text-slate-800">

@php
    use App\Support\Permisos;

    $yo = auth()->user();
    $avisos = $yo->puede(Permisos::INSPECCIONES_VER) ? \App\Support\Avisos::todos() : collect();
    $cantidadAvisos = $avisos->sum('cantidad');
    $avisosUrgentes = $avisos->where('tono', 'rojo')->sum('cantidad');
@endphp

<div x-data="{ menu: false }" class="min-h-full">

    {{-- ===== Barra superior ===== --}}
    <nav class="bg-pc-700 no-imprimir">
        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">

                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2.5">
                        {{-- El simbolo va sobre placa blanca, como se usa la marca sobre fondo de color --}}
                        <span class="inline-flex items-center rounded-lg bg-white px-2 py-1.5">
                            <x-logo variante="marca" alto="h-6" />
                        </span>
                        <span class="hidden text-white sm:block">
                            <span class="block text-sm font-semibold leading-tight">Control de Calidad</span>
                            <span class="block text-[11px] leading-tight text-pc-200">Plásticos Carmen</span>
                        </span>
                    </a>

                    {{-- Navegacion en pantallas medianas y grandes --}}
                    <div class="hidden md:ml-4 md:flex md:items-center md:gap-1">
                        <x-nav-link :href="route('dashboard')" :activo="request()->routeIs('dashboard')">
                            Tablero
                        </x-nav-link>

                        @if ($yo->puede(Permisos::TABLERO_GERENCIA))
                            <x-nav-link :href="route('gerencia')" :activo="request()->routeIs('gerencia')">
                                Gerencia
                            </x-nav-link>
                        @endif

                        @if ($yo->puede(Permisos::LOTES_VER))
                            <x-nav-link :href="route('lotes.index')" :activo="request()->routeIs('lotes.*')">
                                Lotes
                            </x-nav-link>
                        @endif

                        @if ($yo->puede(Permisos::INSPECCIONES_VER))
                            <x-nav-link :href="route('inspecciones.index')" :activo="request()->routeIs('inspecciones.*')">
                                Inspecciones
                            </x-nav-link>
                        @endif

                        @if ($yo->puedeConfigurar())
                            <x-nav-link :href="$yo->puede(Permisos::PLANTILLAS_VER) ? route('admin.plantillas.index') : route('admin.productos.index')"
                                        :activo="request()->routeIs('admin.*')">
                                Configuración
                            </x-nav-link>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-1.5">

                    {{-- Campana de avisos --}}
                    @if ($yo->puede(Permisos::INSPECCIONES_VER))
                        <a href="{{ route('avisos') }}"
                           class="relative rounded-lg p-2.5 text-pc-100 hover:bg-pc-600 hover:text-white"
                           title="{{ $cantidadAvisos > 0 ? $cantidadAvisos.' avisos' : 'Sin avisos' }}">
                            {{-- La campana se mueve una sola vez, y solo si hay algo urgente --}}
                            <svg @class(['h-5 w-5', 'anim-campana' => $avisosUrgentes > 0])
                                 fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M14.857 17.082a24 24 0 0 0 5.454-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24 24 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                            </svg>
                            @if ($cantidadAvisos > 0)
                                <span @class([
                                    'absolute -right-0.5 -top-0.5 grid min-w-[1.15rem] place-items-center rounded-full px-1 text-[10px] font-bold text-white ring-2 ring-pc-700',
                                    'bg-rojo-500 anim-latido' => $avisosUrgentes > 0,
                                    'bg-amber-500' => $avisosUrgentes === 0,
                                ])>{{ $cantidadAvisos > 99 ? '99+' : $cantidadAvisos }}</span>
                            @endif
                        </a>
                    @endif

                    {{-- Usuario, solo en escritorio --}}
                    <div class="hidden md:flex md:items-center md:gap-3">
                        <span class="text-right text-xs text-pc-100">
                            <span class="block font-semibold text-white">{{ $yo->name }}</span>
                            <span class="block">{{ $yo->role_label }}</span>
                        </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg px-3 py-1.5 text-xs font-semibold text-pc-100 ring-1 ring-inset ring-pc-500 hover:bg-pc-600 hover:text-white">
                                Salir
                            </button>
                        </form>
                    </div>

                    {{-- Boton de menu en celular --}}
                    <button type="button" @click="menu = !menu"
                            class="inline-flex items-center justify-center rounded-lg p-2.5 text-pc-100 hover:bg-pc-600 hover:text-white md:hidden"
                            :aria-expanded="menu.toString()" aria-label="Abrir menú">
                        <svg x-show="!menu" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                        <svg x-show="menu" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Menu desplegable en celular --}}
        <div x-show="menu" x-cloak x-transition class="border-t border-pc-600 md:hidden">
            <div class="space-y-1 px-3 py-3">
                <x-nav-link-movil :href="route('dashboard')" :activo="request()->routeIs('dashboard')">Tablero</x-nav-link-movil>

                @if ($yo->puede(Permisos::TABLERO_GERENCIA))
                    <x-nav-link-movil :href="route('gerencia')" :activo="request()->routeIs('gerencia')">Gerencia</x-nav-link-movil>
                @endif
                @if ($yo->puede(Permisos::LOTES_VER))
                    <x-nav-link-movil :href="route('lotes.index')" :activo="request()->routeIs('lotes.*')">Lotes</x-nav-link-movil>
                @endif
                @if ($yo->puede(Permisos::INSPECCIONES_VER))
                    <x-nav-link-movil :href="route('inspecciones.index')" :activo="request()->routeIs('inspecciones.*')">Inspecciones</x-nav-link-movil>
                @endif
                @if ($yo->puede(Permisos::INSPECCIONES_VER))
                    <x-nav-link-movil :href="route('avisos')" :activo="request()->routeIs('avisos')">
                        Avisos @if ($cantidadAvisos > 0) ({{ $cantidadAvisos }}) @endif
                    </x-nav-link-movil>
                @endif
                @if ($yo->puedeConfigurar())
                    <x-nav-link-movil :href="$yo->puede(Permisos::PLANTILLAS_VER) ? route('admin.plantillas.index') : route('admin.productos.index')"
                                      :activo="request()->routeIs('admin.*')">Configuración</x-nav-link-movil>
                @endif
            </div>
            <div class="flex items-center justify-between border-t border-pc-600 px-4 py-3">
                <span class="text-sm">
                    <span class="block font-semibold text-white">{{ $yo->name }}</span>
                    <span class="block text-xs text-pc-200">{{ $yo->role_label }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-2 text-xs font-semibold text-pc-100 ring-1 ring-inset ring-pc-500">
                        Salir
                    </button>
                </form>
            </div>
        </div>
    </nav>

    {{-- ===== Encabezado de pagina ===== --}}
    @hasSection('encabezado')
        <header class="bg-white shadow-sm no-imprimir">
            <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                @yield('encabezado')
            </div>
        </header>
    @endif

    {{-- ===== Contenido ===== --}}
    <main class="anim-entrada mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-6 lg:px-8">
        <x-avisos />
        @yield('contenido')
    </main>
</div>

</body>
</html>
