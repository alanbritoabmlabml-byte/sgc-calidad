<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Intentos fallidos permitidos antes de bloquear temporalmente. */
    private const MAX_INTENTOS = 5;

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $llave = $this->llaveIntentos($request);

        // Se limitan los intentos por correo e IP para frenar fuerza bruta.
        if (RateLimiter::tooManyAttempts($llave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($llave);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos fallidos. Vuelve a probar en {$segundos} segundos.",
            ]);
        }

        if (! Auth::attempt($datos, $request->boolean('remember'))) {
            RateLimiter::hit($llave);

            throw ValidationException::withMessages([
                'email' => 'El correo o la contrasena no son correctos.',
            ]);
        }

        if (! Auth::user()->active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Este usuario esta desactivado. Consulta con el administrador.',
            ]);
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function llaveIntentos(Request $request): string
    {
        return 'login:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip();
    }
}
