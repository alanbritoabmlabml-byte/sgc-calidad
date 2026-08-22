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
        $usuarios = [
            ['Administrador del Sistema', 'admin@plasticoscarmen.com', User::ADMIN],
            ['Control de Calidad', 'calidad@plasticoscarmen.com', User::CALIDAD],
            ['Gerencia', 'gerencia@plasticoscarmen.com', User::GERENCIA],
        ];

        foreach ($usuarios as [$nombre, $correo, $rol]) {
            User::updateOrCreate(
                ['email' => $correo],
                [
                    'name' => $nombre,
                    'password' => 'calidad2026',
                    'role' => $rol,
                    'permissions' => Permisos::preset($rol),
                    'active' => true,
                ]
            );
        }

        $this->call(RafiaSeeder::class);
    }
}
