<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permisos;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Un usuario por rol, con los permisos del preset correspondiente.
        // El administrador puede ajustarlos casilla por casilla despues.
        //
        // Estas son credenciales de instalacion: hay que cambiarlas antes de
        // dar acceso a nadie, desde Configuracion > Usuarios.
        $usuarios = [
            ['Administrador del Sistema', 'admin@plasticoscarmen.com', User::ADMIN, 'PC-Admin-2026'],
            ['Control de Calidad', 'calidad@plasticoscarmen.com', User::CALIDAD, 'PC-Calidad-2026'],
            ['Gerencia', 'gerencia@plasticoscarmen.com', User::GERENCIA, 'PC-Gerencia-2026'],
        ];

        foreach ($usuarios as [$nombre, $correo, $rol, $clave]) {
            User::updateOrCreate(
                ['email' => $correo],
                [
                    'name' => $nombre,
                    'password' => $clave,
                    'role' => $rol,
                    'permissions' => Permisos::preset($rol),
                    'active' => true,
                ]
            );
        }

        $this->call(RafiaSeeder::class);
    }
}
