<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ProductoController;
use App\Models\Inspection;
use App\Models\Lot;
use App\Models\Machine;
use App\Models\Process;
use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use App\Support\FormatoEtiqueta;
use Database\Seeders\RafiaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Reglas de carga: fechas que no pueden estar en el futuro, nombres de persona
 * sin numeros, responsable tomado del usuario con la sesion abierta, y la
 * regla central: una boleta no se emite con datos en blanco.
 */
class ValidacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $calidad;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RafiaSeeder::class);

        $this->calidad = User::factory()->create([
            'name' => 'María Fernández Ñuñez',
            'role' => User::CALIDAD,
            'active' => true,
        ]);

        $this->sector = Sector::where('code', 'RAFIA')->firstOrFail();
    }

    private function proceso(string $code): Process
    {
        return Process::where('sector_id', $this->sector->id)->where('code', $code)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function datosLote(array $extra = []): array
    {
        return array_merge([
            'sector_id' => $this->sector->id,
            'product_id' => Product::where('code', 'Bl 65x108/66')->value('id'),
            'machine_id' => Machine::where('code', 'T-2')->value('id'),
            'nro_tarjeta' => '7',
            'fecha' => today()->format('Y-m-d'),
            'hora' => '08:00',
        ], $extra);
    }

    // =====================================================================
    // Fecha y hora no futuras
    // =====================================================================

    public function test_un_lote_no_se_puede_crear_con_fecha_futura(): void
    {
        $this->actingAs($this->calidad)
            ->post(route('lotes.store'), $this->datosLote([
                'fecha' => today()->addDay()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('fecha');

        $this->assertSame(0, Lot::count());
    }

    public function test_un_lote_no_se_puede_crear_con_hora_futura_del_dia_de_hoy(): void
    {
        // Si ya pasaron las 23:00 no hay hora futura posible dentro de hoy.
        if (now()->format('H:i') >= '23:00') {
            $this->markTestSkipped('A esta hora no queda margen para probar una hora futura.');
        }

        $this->actingAs($this->calidad)
            ->post(route('lotes.store'), $this->datosLote([
                'fecha' => today()->format('Y-m-d'),
                'hora' => '23:59',
            ]))
            ->assertSessionHasErrors('hora');
    }

    public function test_una_hora_pasada_de_hoy_se_acepta(): void
    {
        $this->actingAs($this->calidad)
            ->post(route('lotes.store'), $this->datosLote([
                'fecha' => today()->format('Y-m-d'),
                'hora' => '00:01',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Lot::count());
    }

    public function test_una_hora_cualquiera_de_un_dia_anterior_se_acepta(): void
    {
        $this->actingAs($this->calidad)
            ->post(route('lotes.store'), $this->datosLote([
                'fecha' => today()->subDay()->format('Y-m-d'),
                'hora' => '23:50',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_una_inspeccion_no_se_puede_registrar_con_fecha_futura(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => today()->addDays(3)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('fecha');
    }

    // =====================================================================
    // Nombres de persona
    // =====================================================================

    /** @return array<string, array{0: string}> */
    public static function nombresInvalidos(): array
    {
        return [
            'con numero' => ['Juan 2'],
            'solo numeros' => ['12345'],
            'con arroba' => ['juan@perez'],
            'con parentesis' => ['Juan (turno noche)'],
            'con barra' => ['Juan/Pedro'],
            'con almohadilla' => ['Operador #3'],
        ];
    }

    #[DataProvider('nombresInvalidos')]
    public function test_el_nombre_del_operador_rechaza_numeros_y_simbolos(string $nombre): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => today()->format('Y-m-d'),
                'operador' => $nombre,
            ])
            ->assertSessionHasErrors('operador');
    }

    /** @return array<string, array{0: string}> */
    public static function nombresValidos(): array
    {
        return [
            'simple' => ['Moises Gongora'],
            'con enie' => ['María Ñuñez'],
            'con tildes' => ['José Andrés Pérez'],
            'compuesto con guion' => ['Ana García-López'],
            'con apostrofo' => ["Juan D'Angelo"],
        ];
    }

    #[DataProvider('nombresValidos')]
    public function test_el_nombre_del_operador_acepta_letras_enie_y_tildes(string $nombre): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => today()->format('Y-m-d'),
                'operador' => $nombre,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($nombre, Inspection::latest('id')->first()->operador);
    }

    // =====================================================================
    // Responsable de calidad
    // =====================================================================

    public function test_el_responsable_es_siempre_el_usuario_con_la_sesion_abierta(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => today()->format('Y-m-d'),
                'operador' => 'Moises Gongora',
                // Se intenta atribuir la boleta a otra persona.
                'responsable' => 'Otra Persona Cualquiera',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'María Fernández Ñuñez',
            Inspection::latest('id')->first()->responsable,
            'El responsable no puede tomarse de lo que llegue en el formulario.'
        );
    }

    // =====================================================================
    // Emision con datos completos
    // =====================================================================

    public function test_una_boleta_con_datos_faltantes_no_se_puede_emitir(): void
    {
        $lote = $this->crearLote(['nro_tarjeta' => null]);

        // Inspeccion casi vacia: sin maquina, sin operador y sin mediciones.
        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => today()->format('Y-m-d'),
                'estado' => Inspection::CONFORME,
            ]);

        $inspeccion = Inspection::latest('id')->firstOrFail();
        $faltan = $inspeccion->datosFaltantes();

        $this->assertNotEmpty($faltan);
        $this->assertFalse($inspeccion->puedeEmitirse());

        // El detalle tiene que nombrar lo que falta, en el lenguaje de Calidad.
        $texto = implode(' | ', $faltan);
        $this->assertStringContainsString('Nombre del operador', $texto);
        $this->assertStringContainsString('Telar', $texto);
        $this->assertStringContainsString('Tarjeta', $texto);
        $this->assertStringContainsString('Gramaje', $texto);

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.publicar', $inspeccion))
            ->assertSessionHas('error');

        $this->assertNull($inspeccion->fresh()->published_at);
    }

    public function test_falta_una_sola_medicion_y_la_boleta_no_se_emite(): void
    {
        $lote = $this->crearLote();
        $plantilla = $this->proceso('IT')->activeTemplate;

        // Todas las mediciones menos la elongacion de trama.
        $m = [];

        foreach ($plantilla->parameters as $p) {
            if ($p->code === 'elongacion' && $p->grupo === 'Trama') {
                continue;
            }
            $m[$p->id] = [1 => '20'];
        }

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => today()->format('Y-m-d'),
                'hora' => '08:00',
                'machine_id' => Machine::where('code', 'T-2')->value('id'),
                'operador' => 'Moises Gongora',
                'estado' => Inspection::CONFORME,
                'm' => $m,
            ]);

        $inspeccion = Inspection::latest('id')->firstOrFail();

        $this->assertFalse($inspeccion->puedeEmitirse());
        $this->assertStringContainsString(
            'Elongacion',
            implode(' | ', $inspeccion->datosFaltantes())
        );
    }

    public function test_corte_y_costura_exige_las_cantidades_antes_de_emitir(): void
    {
        // Corte y Costura lleva conteo de bolsas; Tejido no.
        $this->assertTrue($this->proceso('ICC')->requiere_cantidades);
        $this->assertFalse($this->proceso('IT')->requiere_cantidades);
    }

    // =====================================================================
    // Etiquetas
    // =====================================================================

    public function test_el_estado_se_escribe_completo_y_en_mayusculas(): void
    {
        $this->assertSame('PRODUCTO CONFORME', Inspection::ESTADOS_COMPLETOS[Inspection::CONFORME]);
        $this->assertSame('PRODUCTO CON OBSERVACION', Inspection::ESTADOS_COMPLETOS[Inspection::OBSERVADO]);
        $this->assertSame('PRODUCTO NO CONFORME', Inspection::ESTADOS_COMPLETOS[Inspection::RECHAZADO]);
    }

    public function test_los_formatos_de_etiqueta_tienen_las_medidas_de_la_zebra(): void
    {
        $z50 = FormatoEtiqueta::resolver(FormatoEtiqueta::ZEBRA_50X30);
        $this->assertSame(50, $z50['ancho']);
        $this->assertSame(30, $z50['alto']);
        $this->assertTrue($z50['termica']);

        $z40 = FormatoEtiqueta::resolver(FormatoEtiqueta::ZEBRA_40X25);
        $this->assertSame(40, $z40['ancho']);
        $this->assertSame(25, $z40['alto']);
    }

    public function test_el_formato_personalizado_respeta_las_medidas_y_acota_los_extremos(): void
    {
        $medido = FormatoEtiqueta::resolver(FormatoEtiqueta::PERSONALIZADO, 70, 45);
        $this->assertSame(70.0, $medido['ancho']);
        $this->assertSame(45.0, $medido['alto']);

        // Fuera de rango se acota, no se acepta un valor absurdo.
        $enorme = FormatoEtiqueta::resolver(FormatoEtiqueta::PERSONALIZADO, 5000, 5000);
        $this->assertSame((float) FormatoEtiqueta::MAX_MM, $enorme['ancho']);

        $minimo = FormatoEtiqueta::resolver(FormatoEtiqueta::PERSONALIZADO, 1, 1);
        $this->assertSame((float) FormatoEtiqueta::MIN_MM, $minimo['alto']);
    }

    /**
     * La boleta es media carta apaisada: 215,9 x 139,7 mm, con 203,9 mm de ancho
     * util. Con 13 muestras la tabla llega a 17 columnas (caracteristica,
     * especificacion, M1 a M13, promedio y veredicto). Es el caso limite del
     * ancho: si alguien agrega una columna mas, la tabla se desborda del papel.
     */
    public function test_la_boleta_soporta_el_caso_maximo_de_trece_muestras(): void
    {
        $lote = $this->crearLote();

        // Corte y Costura no se habilita sin Tejido aprobado.
        $tejido = $this->proceso('IT');
        $mTejido = [];

        foreach ($tejido->activeTemplate->parameters as $p) {
            $mTejido[$p->id] = [1 => '66'];
        }

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $tejido]), [
                'fecha' => today()->format('Y-m-d'),
                'hora' => '07:00',
                'machine_id' => Machine::where('code', 'T-2')->value('id'),
                'operador' => 'Moises Gongora',
                'm' => $mTejido,
            ])
            ->assertRedirect();

        $corte = $this->proceso('ICC');
        $plantilla = $corte->activeTemplate;

        $this->assertSame(13, $plantilla->muestras_max);

        $m = [];

        foreach ($plantilla->parameters as $p) {
            for ($n = 1; $n <= 13; $n++) {
                $m[$p->id][$n] = $p->tipo === 'select' ? 'B' : '65.4';
            }
        }

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $corte]), [
                'fecha' => today()->format('Y-m-d'),
                'hora' => '07:30',
                'machine_id' => Machine::where('code', 'MK-1')->value('id'),
                'operador' => 'Javier Pastedo',
                'total_unidades' => 2598,
                'total_falladas' => 61,
                'm' => $m,
            ])
            ->assertRedirect();

        $inspeccion = Inspection::latest('id')->firstOrFail();

        $this->assertSame(13, (int) $inspeccion->measurements->max('muestra'));

        $html = $this->actingAs($this->calidad)
            ->get(route('inspecciones.show', $inspeccion))
            ->assertOk()
            ->getContent();

        // Las trece cabeceras de muestra tienen que estar impresas.
        for ($n = 1; $n <= 13; $n++) {
            $this->assertStringContainsString(">M{$n}<", $html, "Falta la columna M{$n} en la boleta.");
        }

        // Y el tamano de pagina tiene que ser el de media carta apaisada.
        $this->assertStringContainsString('size: 215.9mm 139.7mm', $html);
    }

    public function test_una_boleta_sin_emitir_no_genera_etiquetas(): void
    {
        $lote = $this->crearLote();

        $this->actingAs($this->calidad)
            ->post(route('inspecciones.store', [$lote, $this->proceso('IT')]), [
                'fecha' => today()->format('Y-m-d'),
                'operador' => 'Moises Gongora',
            ]);

        $inspeccion = Inspection::latest('id')->firstOrFail();

        // Sin emitir el QR no resuelve, asi que imprimir la etiqueta no sirve.
        $this->actingAs($this->calidad)
            ->get(route('inspecciones.etiquetas', $inspeccion))
            ->assertStatus(422);
    }

    // =====================================================================
    // Alta rapida de producto
    // =====================================================================

    public function test_el_alta_rapida_deduce_los_nominales_del_codigo_de_producto(): void
    {
        $admin = User::factory()->create(['role' => User::ADMIN, 'active' => true]);

        $this->actingAs($admin)
            ->postJson(route('admin.productos.rapido'), [
                'sector_id' => $this->sector->id,
                'code' => 'Vd 70x110/68',
                'name' => 'Saco Verde 70x110',
            ])
            ->assertCreated();

        $producto = Product::where('code', 'Vd 70x110/68')->firstOrFail();

        $this->assertSame('70.00', $producto->ancho_nominal);
        $this->assertSame('110.00', $producto->largo_nominal);
        $this->assertSame('68.00', $producto->gramaje_nominal);

        // El peso de bolsa no se puede deducir del codigo: queda pendiente.
        $this->assertNull($producto->peso_nominal);
        $this->assertContains('peso de bolsa', $producto->completitudNominales()['faltan']);
    }

    public function test_el_deductor_de_codigos_ignora_lo_que_no_reconoce(): void
    {
        $this->assertSame(
            ['ancho_nominal' => null, 'largo_nominal' => null, 'gramaje_nominal' => null],
            ProductoController::deducirDelCodigo('LENO ROJO ESPECIAL')
        );

        // Sin gramaje declarado, deduce solo las medidas.
        $this->assertSame(
            ['ancho_nominal' => 56.0, 'largo_nominal' => 96.0, 'gramaje_nominal' => null],
            ProductoController::deducirDelCodigo('Bl 56x96')
        );
    }

    public function test_el_nombre_de_la_etiqueta_lleva_el_gramaje(): void
    {
        $producto = Product::where('code', 'Bl 65x104/66')->firstOrFail();

        $this->assertSame('Saco Blanco 65x104 - 66 g/m2', $producto->nombre_con_gramaje);
    }

    private function crearLote(array $extra = []): Lot
    {
        $this->actingAs($this->calidad)->post(route('lotes.store'), $this->datosLote($extra));

        return Lot::latest('id')->firstOrFail();
    }
}
