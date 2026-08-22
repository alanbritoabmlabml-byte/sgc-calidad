<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NombrePersona;
use App\Support\Permisos;
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
            'totalPermisos' => count(Permisos::todos()),
        ]);
    }

    public function create(): View
    {
        return view('admin.usuarios.form', [
            'usuario' => new User(['active' => true, 'role' => User::CALIDAD]),
            ...$this->datosFormulario(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = User::create($this->validar($request));

        return redirect()->route('admin.usuarios.index')
            ->with('ok', "Usuario {$usuario->name} creado con ".count($usuario->permisos()).' permisos.');
    }

    public function edit(User $usuario): View
    {
        return view('admin.usuarios.form', [
            'usuario' => $usuario,
            ...$this->datosFormulario(),
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);

        // Sin contrasena nueva, se conserva la actual.
        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        // Un administrador no puede quitarse a si mismo el rol, los permisos ni
        // desactivarse: evita quedarse sin nadie que pueda configurar el sistema.
        if ($usuario->is($request->user())) {
            unset($datos['role'], $datos['permissions']);
            $datos['active'] = true;
        }

        $usuario->update($datos);

        return redirect()->route('admin.usuarios.index')
            ->with('ok', "Usuario {$usuario->name} actualizado.");
    }

    /** @return array<string, mixed> */
    private function datosFormulario(): array
    {
        return [
            'catalogo' => Permisos::catalogo(),
            // Presets por rol, para que el formulario pueda marcar las casillas
            // de golpe al elegir un rol.
            'presets' => collect(User::ROLES)
                ->keys()
                ->mapWithKeys(fn (string $rol) => [$rol => Permisos::preset($rol)])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?User $usuario = null): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120', new NombrePersona],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(Permisos::todos())],
            'password' => [
                $usuario ? 'nullable' : 'required',
                'confirmed',
                Password::min(8),
            ],
        ], [
            'email.unique' => 'Ya hay un usuario registrado con ese correo.',
            'permissions.*.in' => 'Se recibio un permiso que no existe en el catalogo.',
        ], [
            'name' => 'nombre',
            'email' => 'correo',
            'role' => 'rol',
            'password' => 'contrasena',
            'permissions' => 'permisos',
        ]);

        $datos['active'] = $request->boolean('active');

        // El administrador siempre tiene todo: no tiene sentido guardarle una
        // lista parcial que el sistema va a ignorar.
        $datos['permissions'] = $datos['role'] === User::ADMIN
            ? Permisos::todos()
            : array_values($datos['permissions'] ?? []);

        return $datos;
    }
}
