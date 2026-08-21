<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sector;
use App\Models\TestParameter;
use App\Models\TestTemplate;
use Database\Seeders\RafiaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica el evaluador de especificaciones contra lecturas reales de las
 * planillas de Calidad del 13-08 y 14-08, y el veredicto que ellos anotaron.
 */
class EspecificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RafiaSeeder::class);
    }

    private function parametro(string $proceso, string $code, ?string $grupo = null): TestParameter
    {
        $plantilla = TestTemplate::whereRelation('process', 'code', $proceso)
            ->where('active', true)
            ->firstOrFail();

        return $plantilla->parameters()
            ->where('code', $code)
            ->where('grupo', $grupo)
            ->firstOrFail();
    }

    private function producto(string $code): Product
    {
        return Product::where('code', $code)->firstOrFail();
    }

    public function test_el_codigo_de_producto_define_el_gramaje_nominal(): void
    {
        // En "Bl 65x104/66" el 66 es el gramaje nominal en g/m2.
        $this->assertSame('66.00', $this->producto('Bl 65x104/66')->gramaje_nominal);
        $this->assertSame('65.00', $this->producto('Bl 65x104/66')->ancho_nominal);
        $this->assertSame('104.00', $this->producto('Bl 65x104/66')->largo_nominal);
    }

    /**
     * Tejido, gramaje 66 +-2 (64 a 68).
     * Planilla 13-08: Bl 65x108/66 leyo 62 y Calidad marco P.OBS.
     */
    public function test_gramaje_de_tejido_toma_la_tolerancia_del_producto(): void
    {
        $gramaje = $this->parametro('IT', 'gramaje');
        $producto = $this->producto('Bl 65x108/66');

        $this->assertSame([64.0, 68.0], $gramaje->limites($producto));

        $this->assertFalse($gramaje->evaluar(62, $producto), 'gramaje 62 esta fuera de 64-68');
        $this->assertTrue($gramaje->evaluar(66, $producto));
        $this->assertFalse($gramaje->evaluar(63, $producto), 'AM 65x108/66 leyo 63 -> P.OBS');
    }

    /**
     * Tejido, ancho +-1.
     * Planilla 14-08: Bl 56x100/66 leyo 57,3 de ancho y Calidad marco P.OBS.
     */
    public function test_ancho_de_tejido_detecta_el_desvio_registrado_por_calidad(): void
    {
        $ancho = $this->parametro('IT', 'ancho');
        $producto = $this->producto('Bl 56x100/66');

        $this->assertSame([55.0, 57.0], $ancho->limites($producto));
        $this->assertFalse($ancho->evaluar(57.3, $producto));
        $this->assertTrue($ancho->evaluar(56.3, $producto), 'Az 56x102/66 leyo 56,3 -> dentro');
    }

    /** Tejido, tension minimo 60 kgf: sin techo superior. */
    public function test_tension_solo_exige_minimo(): void
    {
        $tension = $this->parametro('IT', 'tension', 'U');

        $this->assertSame([60.0, null], $tension->limites());
        $this->assertTrue($tension->evaluar(107.9));
        $this->assertTrue($tension->evaluar(60));
        $this->assertFalse($tension->evaluar(45.55), 'Leno leyo 45,55 kgf');
    }

    /** Tejido, elongacion 23 +- 5 (18 a 28). */
    public function test_elongacion_usa_objetivo_y_tolerancia_fija(): void
    {
        $elongacion = $this->parametro('IT', 'elongacion', 'T');

        $this->assertSame([18.0, 28.0], $elongacion->limites());
        $this->assertTrue($elongacion->evaluar(19.44));
        $this->assertFalse($elongacion->evaluar(16.46));
        $this->assertFalse($elongacion->evaluar(29.2));
    }

    /**
     * Corte y Costura, peso +-3% sobre el peso nominal del producto.
     * Planilla 13-08, Bl 65x104/66 (nominal 92 g):
     *   promedio 96,30 -> Calidad marco P.OBS
     *   promedio 94,51 -> Calidad marco P.C
     */
    public function test_peso_de_bolsa_reproduce_los_veredictos_de_la_planilla(): void
    {
        $peso = $this->parametro('ICC', 'peso');
        $producto = $this->producto('Bl 65x104/66');

        [$min, $max] = $peso->limites($producto);
        $this->assertSame(89.24, round($min, 2));
        $this->assertSame(94.76, round($max, 2));

        $this->assertFalse($peso->evaluar(96.30, $producto), 'promedio 96,30 -> P.OBS');
        $this->assertTrue($peso->evaluar(94.51, $producto), 'promedio 94,51 -> P.C');
        $this->assertTrue($peso->evaluar(92.79, $producto), 'Am 65x104/66 promedio 92,79 -> P.C');
    }

    /** Corte y Costura, corte: solo "B" es conforme. */
    public function test_corte_evalua_por_lista_de_valores_conformes(): void
    {
        $corte = $this->parametro('ICC', 'corte');

        $this->assertTrue($corte->evaluar('B'));
        $this->assertFalse($corte->evaluar('M'));
        $this->assertNull($corte->evaluar(''), 'sin dato no es un rechazo');
    }

    /** Extrusion, titulo 750 Den +- 3,5%. */
    public function test_denier_usa_tolerancia_porcentual(): void
    {
        $titulo = $this->parametro('EXT', 'titulo');

        [$min, $max] = $titulo->limites();
        $this->assertSame(723.75, round($min, 2));
        $this->assertSame(776.25, round($max, 2));

        $this->assertTrue($titulo->evaluar(740));
        $this->assertFalse($titulo->evaluar(1180), 'lectura atipica de la planilla de denier');
    }

    /** Un parametro sin especificacion nunca marca fuera de rango. */
    public function test_parametro_libre_no_emite_veredicto(): void
    {
        $densidad = $this->parametro('IT', 'densidad', 'T');

        $this->assertSame(TestParameter::MODO_LIBRE, $densidad->spec_modo);
        $this->assertNull($densidad->evaluar(34));
    }

    public function test_el_sector_rafia_queda_con_los_cuatro_procesos_en_orden(): void
    {
        $procesos = Sector::where('code', 'RAFIA')->firstOrFail()->processes;

        $this->assertSame(['EXT', 'IT', 'IMP', 'ICC'], $procesos->pluck('code')->all());

        // Las unicas puertas de calidad del rollo son Tejido y Corte y Costura.
        $this->assertTrue($procesos->firstWhere('code', 'IT')->bloquea_siguiente);
        $this->assertTrue($procesos->firstWhere('code', 'ICC')->bloquea_siguiente);

        // Extrusion se controla por bobina y Impresion es opcional: no bloquean.
        $this->assertFalse($procesos->firstWhere('code', 'EXT')->bloquea_siguiente);
        $this->assertFalse($procesos->firstWhere('code', 'IMP')->bloquea_siguiente);
    }
}
