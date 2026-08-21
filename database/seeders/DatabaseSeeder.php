<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@plasticoscarmen.com'],
            [
                'name' => 'Administrador',
                'password' => 'calidad2026',
                'role' => User::ADMIN,
                'active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'calidad@plasticoscarmen.com'],
            [
                'name' => 'Control de Calidad',
                'password' => 'calidad2026',
                'role' => User::CALIDAD,
                'active' => true,
            ]
        );

        $this->call(RafiaSeeder::class);
    }
}
