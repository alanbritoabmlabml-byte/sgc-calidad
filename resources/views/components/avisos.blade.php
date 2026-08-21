{{-- Mensajes de exito, error y validacion. --}}

@if (session('ok'))
    <div class="mb-4 rounded-lg bg-emerald-50 p-4 ring-1 ring-emerald-200 no-imprimir">
        <p class="text-sm font-medium text-emerald-800">{{ session('ok') }}</p>
    </div>
@endif

@if (session('error'))
    <div class="mb-4 rounded-lg bg-red-50 p-4 ring-1 ring-red-200 no-imprimir">
        <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-50 p-4 ring-1 ring-red-200 no-imprimir">
        <p class="text-sm font-semibold text-red-800">
            Revisa los siguientes datos ({{ $errors->count() }}):
        </p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
