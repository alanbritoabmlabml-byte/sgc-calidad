<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('admin.usuarios.index', [
            'usuarios' => User::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.usuarios.form', [
            'usuario' => new User(['active' => true, 'role' => User::CALIDAD]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        User::create($datos);

        return redirect()->route('admin.usuarios.index')->with('ok', 'Usuario creado.');
    }

    public function edit(User $usuario): View
    {
        return view('admin.usuarios.form', ['usuario' => $usuario]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);

        // Sin contrasena nueva, se conserva la actual.
        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        // Un administrador no puede quitarse a si mismo el rol ni desactivarse:
        // evita quedarse sin nadie que pueda configurar el sistema.
        if ($usuario->is($request->user())) {
            $datos['role'] = $usuario->role;
            $datos['active'] = true;
        }

        $usuario->update($datos);

        return redirect()->route('admin.usuarios.index')->with('ok', "Usuario {$usuario->name} actualizado.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?User $usuario = null): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => ['nullable', 'boolean'],
            'password' => [
                $usuario ? 'nullable' : 'required',
                'confirmed',
                Password::min(8),
            ],
        ], [
            'email.unique' => 'Ya hay un usuario registrado con ese correo.',
        ], [
            'name' => 'nombre',
            'email' => 'correo',
            'role' => 'rol',
            'password' => 'contrasena',
        ]);

        $datos['active'] = $request->boolean('active');

        return $datos;
    }
}
