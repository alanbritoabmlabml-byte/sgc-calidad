<?php

namespace Tests\Feature;

use App\Models\Inspection;
use App\Models\Lot;
use App\Models\Machine;
use App\Models\Process;
use App\Models\Product;
use App\Models\Sector;
use App\Models\TestParameter;
use App\Models\User;
use Database\Seeders\RafiaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flujo completo: lote -> inspeccion de Tejido -> emision -> certificado publico
 * por QR -> etiquetas, mas las reglas de bloqueo entre procesos.
 *
 * Los valores de medicion son los de la planilla real del 13-08.
 */
class FlujoInspeccionTest extends TestCase
{
    use RefreshDatabase;

    private User $calidad;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RafiaSeeder::class);

        $this->calidad = User::factory()->create([
            'role' => User::CALIDAD,
            'active' => true,
        ]);

        $this->sector = Sector::where('code', 'RAFIA')->firstOrFail();
    }

    private function proceso(string $code): Process
    {
        return Process::where('sector_id', $this->sector->id)->where('code', $code)->firstOrFail();
    }

    private function crearLote(array $extra = []): Lot
    {
        $respuesta = $this->actingAs($this->calidad)->post(route('lotes.store'), array_merge([
            'sector_id' => $this->sector->id,
            'product_id' => Product::where('code', 'Bl 65x108/66')->value('id'),
            'machine_id' => Machine::where('code', 'T-2')->value('id'),
            'nro_tarjeta' => '7',
            'fecha' => '2026-08-13',
            'hora' => '11:15',
            'peso_neto' => 250,
        ], $extra));

        $respuesta->assertRedirect();

        return Lot::latest('id')->firstOrFail();
    }

    /** Mediciones de Tejido del 13-08: gramaje 62 (fuera de 64-68) y el resto dentro. */
    private function medicionesTejido(array $sobreescribir = []): array
    {
        $plantilla = $this->proceso('IT')->activeTemplate;

        $porCodigo = function (string $code, ?string $grupo = null) use ($plantilla): int {
            return $plantilla->parameters()
                ->where('code', $code)->where('grupo', $grupo)->value('id');
        };

        $valores = array_merge([
            'gramaje' => 62,
            'ancho' => 65.5,
            'densidad|T' => 34,
            'densidad|U' => 39,
            'peso_muestra' => 96.2,
            'tension|T' => 72.52,
            'tension|U' => 94.13,
            'elongacion|T' => 20.16,
            'elongacion|U' => 23,
        ], $sobreescribir);

        $m = [];

        foreach ($valores as $llave => $valor) {
            [$code, $grupo] = array_pad(explode('|', $llave), 2, null);
            $m[$porCodigo($code, $grupo)] = [1 => (string) $valor];
        }

        return $m;
    }

    public function test_el_primer_lote_del_sector_toma_el_correlativo_inicial(): void
    {
        $lote = $this->crearLote();

        $this->assertSame('RAF-'.now()->format('y').'-00001', $lote->code);
        $this->assertSame(Lot::ABIERTO, $lote->estado);
    }

    public function test_corte_y_costura_esta_bloqueado_sin_la_inspeccion_de_tejido(): void
    {
        $lote = $this->crearLote();

        $puerta = $lote->puedeInspeccionar($this->proceso('ICC'));

        $this->assertFalse($puerta['permitido']);
        $this->assertStringContainsString('Falta la inspeccion de Tejido', $puerta['motivo']);

        // Y la ruta tampoco se puede forzar a mano.
        $this->actingAs($this->calidad)
            ->get(route('inspecciones.create', [$lote, $this->proceso('ICC')]))
            ->assertStatus(422);
    }

    public function test_extrusion_e_impresion_no_bloquean_porque_no_son_puertas_del_rollo(): void
    {
        $lote = $this->crearLote();

        $this->assertTrue($lote->puedeInspeccionar($this->proceso('EXT'))['permitido']);
        $this->assertTrue($lote->puedeInspeccionar($this->proceso('IT'))['permitido']);
    }

    public function test_una_inspeccion_de_tejido_con_gramaje_fuera_de_spec_se_sugiere_como_observada(): void
    {
        $lote = $this->crearLote();
        $tejido = $this->proceso('IT');

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $tejido]), [
                'fecha' => '2026-08-13',
                'hora' => '11:15',
                'machine_id' => Machine::where('code', 'T-2')->value('id'),
                'operador' => 'Moises Gongora',
                'responsable' => 'Fabiola',
                // Sin estado: el sistema aplica su sugerencia.
                'm' => $this->medicionesTejido(),
            ])
            ->assertRedirect();

        $inspeccion = Inspection::latest('id')->firstOrFail();

        $this->assertSame('IT-000001', $inspeccion->code);
        // El gramaje 62 cae fuera de 66 +-2, y ningun parametro es critico: P.OBS.
        $this->assertSame(Inspection::OBSERVADO, $inspeccion->estado);

        // La medicion del gramaje quedo marcada como fuera de especificacion.
        $gramaje = $inspeccion->measurements
            ->first(fn ($m) => $m->parameter->code === 'gramaje');

        $this->assertFalse($gramaje->en_especificacion);

        // Las que estan dentro quedan marcadas como conformes.
        $ancho = $inspeccion->measurements->first(fn ($m) => $m->parameter->code === 'ancho');
        $this->assertTrue($ancho->en_especificacion);
    }

    public function test_con_todas_las_lecturas_dentro_de_spec_la_inspeccion_sale_conforme(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'm' => $this->medicionesTejido(['gramaje' => 66]),
            ])
            ->assertRedirect();

        $this->assertSame(Inspection::CONFORME, Inspection::latest('id')->first()->estado);
    }

    public function test_un_parametro_marcado_como_critico_hace_que_el_sistema_sugiera_rechazo(): void
    {
        // Calidad decide que el gramaje por si solo rechaza el lote.
        TestParameter::whereRelation('template.process', 'code', 'IT')
            ->where('code', 'gramaje')
            ->update(['requerido' => true]);

        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'm' => $this->medicionesTejido(),
            ])
            ->assertRedirect();

        $this->assertSame(Inspection::RECHAZADO, Inspection::latest('id')->first()->estado);
    }

    public function test_un_tejido_rechazado_bloquea_corte_y_costura_y_bloquea_el_lote(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'estado' => Inspection::RECHAZADO,
                'm' => $this->medicionesTejido(),
            ])
            ->assertRedirect();

        $lote->refresh();

        $this->assertSame(Lot::BLOQUEADO, $lote->estado);

        $puerta = $lote->puedeInspeccionar($this->proceso('ICC'));
        $this->assertFalse($puerta['permitido']);
        $this->assertStringContainsString('RECHAZADO', $puerta['motivo']);
    }

    public function test_un_lote_de_corte_hereda_la_aprobacion_del_rollo_de_tejido_que_consume(): void
    {
        // Rollo de Tejido aprobado.
        $rollo = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$rollo, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'm' => $this->medicionesTejido(['gramaje' => 66]),
            ])
            ->assertRedirect();

        // Lote de bolsas que consume ese rollo: es el vinculo que en la planilla
        // de Corte y Costura se anota como "N. LOTE" = tarjeta del rollo de Tejido.
        $bolsas = $this->crearLote([
            'nro_tarjeta' => '2',
            'source_lot_id' => $rollo->id,
        ]);

        $puerta = $bolsas->puedeInspeccionar($this->proceso('ICC'));

        $this->assertTrue(
            $puerta['permitido'],
            'El lote de corte deberia habilitarse por la aprobacion de su rollo de origen. Motivo: '.$puerta['motivo']
        );
    }

    public function test_un_lote_de_bolsas_se_libera_con_el_tejido_heredado_y_su_propio_corte(): void
    {
        $rollo = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$rollo, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'm' => $this->medicionesTejido(['gramaje' => 66]),
            ]);

        $bolsas = $this->crearLote(['nro_tarjeta' => '2', 'source_lot_id' => $rollo->id]);

        // Con solo el Tejido heredado todavia falta su propio Corte y Costura.
        $bolsas->recalcularEstado();
        $this->assertSame(Lot::ABIERTO, $bolsas->fresh()->estado);

        $corte = $this->proceso('ICC');
        $plantilla = $corte->activeTemplate;
        $ancho = $plantilla->parameters()->where('code', 'ancho')->value('id');

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$bolsas, $corte]), [
                'fecha' => '2026-08-13',
                'total_unidades' => 2598,
                'total_falladas' => 61,
                // 65 +-1 sobre el ancho nominal del producto.
                'm' => [$ancho => [1 => '65.2', 2 => '65.4', 3 => '65.1']],
            ])
            ->assertRedirect();

        // Ahora si: Tejido heredado aprobado y Corte y Costura propio aprobado.
        $this->assertSame(Lot::LIBERADO, $bolsas->fresh()->estado);
    }

    public function test_un_rechazo_en_el_rollo_de_origen_bloquea_al_lote_de_bolsas(): void
    {
        $rollo = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$rollo, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'estado' => Inspection::RECHAZADO,
                'm' => $this->medicionesTejido(),
            ]);

        $bolsas = $this->crearLote(['nro_tarjeta' => '2', 'source_lot_id' => $rollo->id]);
        $bolsas->recalcularEstado();

        $this->assertSame(Lot::BLOQUEADO, $bolsas->fresh()->estado);
    }

    public function test_el_certificado_publico_no_resuelve_hasta_que_la_boleta_se_emite(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'observacion' => 'Variacion de gramaje en la muestra',
                'observacion_interna' => 'Revisar calibracion del telar T-2',
                'm' => $this->medicionesTejido(),
            ])
            ->assertRedirect();

        $inspeccion = Inspection::latest('id')->firstOrFail();

        // Sin emitir, el QR no debe exponer nada.
        $this->get(route('certificado', $inspeccion->public_token))->assertNotFound();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.publicar', $inspeccion))
            ->assertRedirect();

        // Emitida, la ve cualquiera sin iniciar sesion.
        $respuesta = $this->get(route('certificado', $inspeccion->fresh()->public_token));

        $respuesta->assertOk();
        $respuesta->assertSee($lote->code);
        $respuesta->assertSee('Certificado de calidad');
        $respuesta->assertSee('Variacion de gramaje en la muestra');

        // La observacion interna NUNCA sale en el certificado del cliente.
        $respuesta->assertDontSee('Revisar calibracion del telar T-2');
    }

    public function test_una_boleta_emitida_no_se_puede_editar_sin_anular_la_emision(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'm' => $this->medicionesTejido(),
            ]);

        $inspeccion = Inspection::latest('id')->firstOrFail();

        $this->actingAs($this->calidad)->post(route('inspecciones.publicar', $inspeccion));

        $this->actingAs($this->calidad)
            ->put(route('inspecciones.update', $inspeccion), [
                'fecha' => '2026-08-14',
                'm' => $this->medicionesTejido(),
            ])
            ->assertForbidden();
    }

    public function test_una_boleta_pendiente_no_se_puede_emitir(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'estado' => Inspection::PENDIENTE,
                'm' => $this->medicionesTejido(),
            ]);

        $inspeccion = Inspection::latest('id')->firstOrFail();
        $this->assertSame(Inspection::PENDIENTE, $inspeccion->estado);

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.publicar', $inspeccion))
            ->assertSessionHas('error');

        $this->assertNull($inspeccion->fresh()->published_at);
    }

    public function test_la_hoja_de_etiquetas_incluye_un_qr_por_etiqueta(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => '2026-08-13',
                'm' => $this->medicionesTejido(),
            ]);

        $inspeccion = Inspection::latest('id')->firstOrFail();
        $this->actingAs($this->calidad)->post(route('inspecciones.publicar', $inspeccion));

        $respuesta = $this->actingAs($this->calidad)
            ->get(route('inspecciones.etiquetas', ['inspeccion' => $inspeccion, 'cantidad' => 6]));

        $respuesta->assertOk();
        $respuesta->assertSee($lote->code);

        // Seis etiquetas, seis QR. Se cuenta la firma del SVG que emite el
        // generador de QR, para no contar los iconos de la interfaz.
        $this->assertSame(
            6,
            substr_count($respuesta->getContent(), '<svg xmlns="http://www.w3.org/2000/svg" version="1.1"')
        );
    }

    public function test_el_rol_de_solo_lectura_no_puede_cargar_inspecciones(): void
    {
        $lectura = User::factory()->create(['role' => User::LECTURA, 'active' => true]);
        $lote = $this->crearLote();

        $this->actingAs($lectura)
            ->get(route('inspecciones.create', [$lote, $this->proceso('IT')]))
            ->assertForbidden();

        $this->actingAs($lectura)->get(route('lotes.create'))->assertForbidden();

        // Pero si puede consultar.
        $this->actingAs($lectura)->get(route('lotes.show', $lote))->assertOk();
    }

    public function test_la_configuracion_es_solo_para_administradores(): void
    {
        $this->actingAs($this->calidad)
            ->get(route('admin.plantillas.index'))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => User::ADMIN, 'active' => true]);

        $this->actingAs($admin)->get(route('admin.plantillas.index'))->assertOk();
    }

    public function test_un_usuario_desactivado_no_puede_ingresar(): void
    {
        $inactivo = User::factory()->create([
            'email' => 'inactivo@plasticoscarmen.com',
            'password' => 'clave-larga-123',
            'active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $inactivo->email,
            'password' => 'clave-larga-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
