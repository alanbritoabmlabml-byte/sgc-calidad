<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nombre de persona: letras, espacios y los signos que aparecen en nombres
 * reales. No admite numeros ni caracteres especiales.
 *
 * Acepta la enie y las vocales acentuadas, que son parte del idioma, ademas
 * del apostrofo y el guion de nombres compuestos (D'Angelo, Garcia-Lopez).
 */
class NombrePersona implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('El campo :attribute debe ser un texto.');

            return;
        }

        if (preg_match('/\d/u', $value)) {
            $fail('El campo :attribute no puede contener numeros.');

            return;
        }

        // Letras con marcas diacriticas, espacios, apostrofo y guion.
        if (! preg_match("/^[\p{L}\p{M}\s'’\-\.]+$/u", $value)) {
            $fail('El campo :attribute solo admite letras, espacios, apostrofo y guion.');

            return;
        }

        // Al menos dos letras seguidas: descarta entradas como "-" o "a a".
        if (! preg_match('/\p{L}{2}/u', $value)) {
            $fail('El campo :attribute no parece un nombre valido.');
        }
    }
}
