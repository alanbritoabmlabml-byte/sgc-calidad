<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * La hora no puede ser posterior al momento actual cuando la fecha es hoy.
 *
 * Una fecha futura ya la rechaza "before_or_equal:today", pero eso deja pasar
 * una hora futura del dia de hoy: un corte de telar no puede registrarse a las
 * 18:00 si son las 10:00.
 */
class HoraNoFutura implements ValidationRule
{
    public function __construct(private readonly ?string $fecha) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || blank($this->fecha)) {
            return;
        }

        try {
            $fecha = Carbon::parse($this->fecha);
        } catch (\Throwable) {
            // La fecha invalida la reporta su propia regla.
            return;
        }

        if (! $fecha->isToday()) {
            return;
        }

        // Se compara solo hora y minuto: el campo del formulario no tiene segundos.
        if (mb_substr((string) $value, 0, 5) > now()->format('H:i')) {
            $fail('La hora no puede ser posterior a la hora actual ('.now()->format('H:i').').');
        }
    }
}
