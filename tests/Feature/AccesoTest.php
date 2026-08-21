<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_sistema_exige_iniciar_sesion(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('lotes.index'))->assertRedirect(route('login'));
        $this->get(route('inspecciones.index'))->assertRedirect(route('login'));
    }

    public function test_la_pantalla_de_ingreso_es_publica(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Control de Calidad');
    }

    public function test_un_usuario_activo_ingresa_y_llega_al_tablero(): void
    {
        $usuario = User::factory()->create([
            'email' => 'calidad@plasticoscarmen.com',
            'password' => 'clave-larga-123',
            'role' => User::CALIDAD,
            'active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => 'clave-larga-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_una_contrasena_incorrecta_no_deja_pasar(): void
    {
        User::factory()->create([
            'email' => 'calidad@plasticoscarmen.com',
            'password' => 'clave-larga-123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'calidad@plasticoscarmen.com',
            'password' => 'equivocada',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_token_de_certificado_inexistente_devuelve_404(): void
    {
        $this->get(route('certificado', 'tokenquenoexiste123'))->assertNotFound();
    }
}
