<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Control de Calidad') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased text-slate-800">

<div x-data="{ menu: false }" class="min-h-full">

    {{-- ===== Barra superior ===== --}}
    <nav class="bg-pc-700 no-imprimir">
        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">

                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 shrink-0">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-white font-extrabold text-pc-700 text-sm">
                            PC
                        </span>
                        <span class="hidden text-white sm:block">
                            <span class="block text-sm font-semibold leading-tight">Control de Calidad</span>
                            <span class="block text-[11px] leading-tight text-pc-200">Plasticos Carmen</span>
                        </span>
                    </a>

                    {{-- Navegacion en pantallas medianas y grandes --}}
                    <div class="hidden md:ml-6 md:flex md:items-center md:gap-1">
                        <x-nav-link :href="route('dashboard')" :activo="request()->routeIs('dashboard')">
                            Tablero
                        </x-nav-link>
                        <x-nav-link :href="route('lotes.index')" :activo="request()->routeIs('lotes.*')">
                            Lotes
                        </x-nav-link>
                        <x-nav-link :href="route('inspecciones.index')" :activo="request()->routeIs('inspecciones.*')">
                            Inspecciones
                        </x-nav-link>
                        @if (auth()->user()->esAdmin())
                            <x-nav-link :href="route('admin.plantillas.index')" :activo="request()->routeIs('admin.*')">
                                Configuracion
                            </x-nav-link>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Usuario, solo en escritorio --}}
                    <div class="hidden md:flex md:items-center md:gap-3">
                        <span class="text-right text-xs text-pc-100">
                            <span class="block font-semibold text-white">{{ auth()->user()->name }}</span>
                            <span class="block">{{ auth()->user()->role_label }}</span>
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
                            :aria-expanded="menu.toString()" aria-label="Abrir menu">
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
                <x-nav-link-movil :href="route('lotes.index')" :activo="request()->routeIs('lotes.*')">Lotes</x-nav-link-movil>
                <x-nav-link-movil :href="route('inspecciones.index')" :activo="request()->routeIs('inspecciones.*')">Inspecciones</x-nav-link-movil>
                @if (auth()->user()->esAdmin())
                    <x-nav-link-movil :href="route('admin.plantillas.index')" :activo="request()->routeIs('admin.*')">Configuracion</x-nav-link-movil>
                @endif
            </div>
            <div class="flex items-center justify-between border-t border-pc-600 px-4 py-3">
                <span class="text-sm">
                    <span class="block font-semibold text-white">{{ auth()->user()->name }}</span>
                    <span class="block text-xs text-pc-200">{{ auth()->user()->role_label }}</span>
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
    <main class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-6 lg:px-8">
        <x-avisos />
        @yield('contenido')
    </main>
</div>

</body>
</html>
