<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\Process;
use App\Models\Product;
use App\Models\Sector;
use App\Models\TestParameter as TP;
use App\Models\TestTemplate;
use Illuminate\Database\Seeder;

/**
 * Carga el sector Rafia con los procesos, catalogos y plantillas de ensayo
 * relevados de las planillas reales de Control de Calidad:
 *   TEJIDOS.xlsx, CORTE Y COSTURA.xlsx, ANALISIS DENIER 2026.xlsx, IMPRESIONES.xlsx
 *
 * Todo lo que se carga aca es DATO, no codigo: Calidad lo puede corregir desde
 * la pantalla de plantillas sin tocar el sistema.
 *
 * Ningun parametro se carga como "critico" (requerido). En las planillas, un
 * desvio se registra como P.OBS y el rechazo no aparece nunca: el criterio de
 * rechazo es una decision del inspector, no automatica. Si Calidad decide que
 * alguna caracteristica debe rechazar el lote por si sola, marca ese parametro
 * como critico desde Configuracion y el sistema sugiere RECHAZADO.
 */
class RafiaSeeder extends Seeder
{
    public function run(): void
    {
        $sector = Sector::updateOrCreate(
            ['code' => 'RAFIA'],
            ['name' => 'Rafia', 'prefijo_lote' => 'RAF', 'active' => true]
        );

        $procesos = $this->procesos($sector);
        $this->maquinas($sector);
        $this->productos($sector);

        $this->plantillaExtrusion($procesos['EXT']);
        $this->plantillaTejido($procesos['IT']);
        $this->plantillaImpresion($procesos['IMP']);
        $this->plantillaCorteCostura($procesos['ICC']);
    }

    /**
     * Cadena productiva de Rafia. El orden define la trazabilidad y el bloqueo.
     *
     * @return array<string, Process>
     */
    private function procesos(Sector $sector): array
    {
        // Solo Tejido y Corte y Costura son puertas de calidad del rollo.
        // Extrusion se controla por bobina y por maquina, en su propio registro,
        // no sobre el lote de tejido: por eso no bloquea. Impresion tampoco,
        // porque no todos los productos se imprimen.
        //
        // Las etiquetas replican palabra por palabra los formularios preimpresos
        // COD.02 y COD.03, para que la boleta del sistema se lea igual que la de papel.
        $definicion = [
            [
                'code' => 'EXT', 'name' => 'Extrusion', 'boleta' => null,
                'orden' => 1, 'bloquea' => false,
                'etiquetas' => [
                    'titulo' => 'INSPECCION DE EXTRUSION (IE)',
                    'titulo_estado' => 'ESTADO DE INSPECCION DE CINTA',
                    'fecha' => 'Fecha de extrusion',
                    'producto' => 'Codigo',
                    'maquina' => 'Extrusora',
                    'peso' => 'Peso de bobina',
                    'unidades' => 'Total de bobinas',
                    'falladas' => 'Falladas',
                    'buenas' => 'Total de bobinas buenas',
                ],
            ],
            [
                'code' => 'IT', 'name' => 'Tejido', 'boleta' => 'COD.02',
                'orden' => 2, 'bloquea' => true,
                'etiquetas' => [
                    'titulo' => 'INSPECCION DE TEJIDO (IT)',
                    'titulo_estado' => 'ESTADO DE INSPECCION DE TEJIDO',
                    'fecha' => 'Fecha de corte de telar',
                    'producto' => 'Codigo',
                    'maquina' => 'Telar',
                    'peso' => 'Peso del rollo',
                    'tarjeta' => 'N. de Tarjeta',
                    'unidades' => 'Total de rollos',
                    'falladas' => 'Fallados',
                    'buenas' => 'Total de rollos buenos',
                ],
            ],
            [
                'code' => 'IMP', 'name' => 'Impresion', 'boleta' => null,
                'orden' => 3, 'bloquea' => false,
                'etiquetas' => [
                    'titulo' => 'INSPECCION DE IMPRESION (II)',
                    'titulo_estado' => 'ESTADO DE INSPECCION DE IMPRESION',
                    'fecha' => 'Fecha de impresion',
                    'producto' => 'Codigo de bolsa',
                    'maquina' => 'Maquina',
                    'tarjeta' => 'N. de Rollo',
                    'unidades' => 'Cantidad de bolsas',
                    'falladas' => 'Falladas',
                    'buenas' => 'Total de bolsas buenas',
                ],
            ],
            [
                'code' => 'ICC', 'name' => 'Corte y Costura', 'boleta' => 'COD.03',
                'orden' => 4, 'bloquea' => true,
                'etiquetas' => [
                    'titulo' => 'INSPECCION DE CORTE Y COSTURA (IC/C)',
                    'titulo_estado' => 'ESTADO DE INSPECCION DE BOLSAS',
                    'fecha' => 'Fecha de corte de rollo',
                    'producto' => 'Codigo de bolsa',
                    'maquina' => 'Maquina',
                    'peso' => 'Peso de rollo',
                    'tarjeta' => 'N. de Tarjeta',
                    'unidades' => 'Total de bolsas',
                    'falladas' => 'Falladas',
                    'buenas' => 'Total de bolsas buenas',
                ],
            ],
        ];

        $procesos = [];

        foreach ($definicion as $p) {
            $procesos[$p['code']] = Process::updateOrCreate(
                ['sector_id' => $sector->id, 'code' => $p['code']],
                [
                    'name' => $p['name'],
                    'boleta_code' => $p['boleta'],
                    'etiquetas' => $p['etiquetas'],
                    'orden' => $p['orden'],
                    'bloquea_siguiente' => $p['bloquea'],
                    'active' => true,
                ]
            );
        }

        return $procesos;
    }

    private function maquinas(Sector $sector): void
    {
        $maquinas = [];

        // Telares vistos en las planillas: T-2, T-3, T-4, T-6, T-8, T-9.
        foreach (range(1, 10) as $n) {
            $maquinas[] = ["T-{$n}", "Telar {$n}", 'telar'];
        }

        foreach (range(1, 6) as $n) {
            $maquinas[] = ["EXT-{$n}", "Extrusora {$n}", 'extrusora'];
        }

        foreach (range(1, 3) as $n) {
            $maquinas[] = ["MK-{$n}", "Corte y costura MK-{$n}", 'corte'];
        }

        foreach (range(1, 5) as $n) {
            $maquinas[] = ["IMP-{$n}", "Impresora {$n}", 'impresora'];
        }

        foreach ($maquinas as [$code, $name, $tipo]) {
            Machine::updateOrCreate(
                ['sector_id' => $sector->id, 'code' => $code],
                ['name' => $name, 'tipo' => $tipo, 'active' => true]
            );
        }
    }

    /**
     * Productos relevados de las planillas.
     *
     * En el codigo "Bl 65x104/66": Bl = color, 65 = ancho cm, 104 = largo cm,
     * 66 = gramaje nominal g/m2. Verificado contra las lecturas de Tejido.
     *
     * peso_nominal es el peso esperado de la bolsa terminada. Donde Calidad lo
     * dejo escrito en las observaciones de la planilla se usa ese valor (92, 79,
     * 131 g). El resto es una estimacion geometrica y esta marcada para revision.
     */
    private function productos(Sector $sector): void
    {
        $productos = [
            // code, color, ancho, largo, gramaje, peso_nominal (null = estimar)
            ['Bl 56x96/66', 'Blanco', 56, 96, 66, null],
            ['Bl 56x100/66', 'Blanco', 56, 100, 66, null],
            ['Bl 61x98/66', 'Blanco', 61, 98, 66, null],
            ['Bl 65x103/65', 'Blanco', 65, 103, 65, null],
            ['Bl 65x104/66', 'Blanco', 65, 104, 66, 92],    // "92 Grs. de acuerdo al calculo del codigo"
            ['Bl 65x108/66', 'Blanco', 65, 108, 66, null],
            ['Az 56x102/66', 'Azul', 56, 102, 66, 79],      // "79 Grs. de acuerdo al calculo del codigo"
            ['Az 56x106/66', 'Azul', 56, 106, 66, null],
            ['Am 65x104/66', 'Amarillo', 65, 104, 66, 92],  // misma geometria que Bl 65x104/66
            ['Am 65x108/66', 'Amarillo', 65, 108, 66, null],
            ['Cl 80x120/66', 'Cristal', 80, 120, 66, 131],  // "131 Grs."
            ['V.Cl 56x96/64', 'Verde claro', 56, 96, 64, null],
            ['Leno Rj 65x50', 'Rojo', 65, null, 50, null],  // tejido leno (malla)
        ];

        foreach ($productos as [$code, $color, $ancho, $largo, $gramaje, $peso]) {
            Product::updateOrCreate(
                ['sector_id' => $sector->id, 'code' => $code],
                [
                    'name' => trim("Saco {$color} {$ancho}x{$largo}"),
                    'color' => $color,
                    'ancho_nominal' => $ancho,
                    'largo_nominal' => $largo,
                    'gramaje_nominal' => $gramaje,
                    'peso_nominal' => $peso ?? $this->pesoEstimado($ancho, $largo, $gramaje),
                    'active' => true,
                ]
            );
        }
    }

    /**
     * Estimacion del peso de la bolsa: tejido tubular (doble capa) mas 4 cm de
     * largo por el dobladillo y la costura de fondo. Es un punto de partida;
     * Calidad debe confirmar el valor por producto.
     */
    private function pesoEstimado(?float $ancho, ?float $largo, ?float $gramaje): ?float
    {
        if (! $ancho || ! $largo || ! $gramaje) {
            return null;
        }

        return round(2 * $ancho * ($largo + 4) * $gramaje / 10000, 1);
    }

    /** Fuente: ANALISIS DENIER 2026.xlsx y EXTRUSORA MARZO.xlsx */
    private function plantillaExtrusion(Process $process): void
    {
        $plantilla = $this->plantilla($process, 'Ensayos de calidad de cintas de Polipropileno', '1', 12, 12);

        $this->parametros($plantilla, [
            [
                'code' => 'color', 'label' => 'Color', 'tipo' => 'text',
                'spec_modo' => TP::MODO_LIBRE, 'muestras' => 12, 'promediar' => false,
            ],
            [
                'code' => 'ancho_cinta', 'label' => 'Ancho de cinta', 'unit' => 'mm',
                'spec_modo' => TP::MODO_LIBRE, 'muestras' => 12,
            ],
            [
                'code' => 'titulo', 'label' => 'Titulo', 'unit' => 'Den',
                'spec_modo' => TP::MODO_OBJETIVO_PCT,
                'spec_objetivo' => 750, 'spec_tolerancia_pct' => 3.5,
                'spec_desde_producto' => 'denier_nominal',
                'spec_label' => '+/- 3,5%',
                'muestras' => 12,
            ],
            // Condiciones de proceso: un solo valor por inspeccion.
            ['code' => 'velocidad', 'label' => 'Velocidad', 'unit' => 'm/min', 'spec_modo' => TP::MODO_LIBRE],
            ['code' => 't_horno', 'label' => 'Temperatura de horno', 'unit' => 'C', 'spec_modo' => TP::MODO_LIBRE],
            ['code' => 't_tanque', 'label' => 'Temperatura de tanque', 'unit' => 'C', 'spec_modo' => TP::MODO_LIBRE],
            ['code' => 'chiller', 'label' => 'Chiller', 'unit' => 'C', 'spec_modo' => TP::MODO_LIBRE],
            ['code' => 'reciclado', 'label' => 'Reciclado utilizado', 'unit' => '%', 'spec_modo' => TP::MODO_LIBRE],
            ['code' => 'materia_prima', 'label' => 'Materia prima', 'tipo' => 'text', 'spec_modo' => TP::MODO_LIBRE, 'promediar' => false],
        ]);
    }

    /**
     * Fuente: TEJIDOS.xlsx, encabezado del formulario.
     * "Gramaje Promedio (g/m2) +-2 | Ancho (cm) +-1 | Densidad (cintas/10cm) T/U |
     *  Peso | Tension (kgf) MINIMO 60Kgrsf T/U | Elongacion (%) 23 +- 5 T/U"
     */
    private function plantillaTejido(Process $process): void
    {
        $plantilla = $this->plantilla($process, 'Ensayo de calidad en tejido de polipropileno', '1', 1, 1);

        $this->parametros($plantilla, [
            [
                'code' => 'gramaje', 'label' => 'Gramaje promedio', 'unit' => 'g/m2',
                'spec_modo' => TP::MODO_OBJETIVO_TOL,
                'spec_tolerancia' => 2,
                'spec_desde_producto' => 'gramaje_nominal',
                'spec_label' => '+/- 2',
            ],
            [
                'code' => 'ancho', 'label' => 'Ancho', 'unit' => 'cm',
                'spec_modo' => TP::MODO_OBJETIVO_TOL,
                'spec_tolerancia' => 1,
                'spec_desde_producto' => 'ancho_nominal',
                'spec_label' => '+/- 1',
            ],
            // Densidad de cintas: la boleta fisica COD.02 pide trama y urdimbre.
            ['code' => 'densidad', 'label' => 'Densidad', 'unit' => 'cintas/10cm', 'grupo' => 'Trama', 'spec_modo' => TP::MODO_LIBRE],
            ['code' => 'densidad', 'label' => 'Densidad', 'unit' => 'cintas/10cm', 'grupo' => 'Urdimbre', 'spec_modo' => TP::MODO_LIBRE],

            ['code' => 'peso_muestra', 'label' => 'Peso de muestra', 'unit' => 'g', 'spec_modo' => TP::MODO_LIBRE],

            [
                'code' => 'tension', 'label' => 'Tension', 'unit' => 'kgf', 'grupo' => 'Trama',
                'spec_modo' => TP::MODO_MINIMO, 'spec_min' => 60,
                'spec_label' => 'minimo 60',
            ],
            [
                'code' => 'tension', 'label' => 'Tension', 'unit' => 'kgf', 'grupo' => 'Urdimbre',
                'spec_modo' => TP::MODO_MINIMO, 'spec_min' => 60,
                'spec_label' => 'minimo 60',
            ],
            [
                'code' => 'elongacion', 'label' => 'Elongacion', 'unit' => '%', 'grupo' => 'Trama',
                'spec_modo' => TP::MODO_OBJETIVO_TOL,
                'spec_objetivo' => 23, 'spec_tolerancia' => 5,
                'spec_label' => '23 +/- 5',
            ],
            [
                'code' => 'elongacion', 'label' => 'Elongacion', 'unit' => '%', 'grupo' => 'Urdimbre',
                'spec_modo' => TP::MODO_OBJETIVO_TOL,
                'spec_objetivo' => 23, 'spec_tolerancia' => 5,
                'spec_label' => '23 +/- 5',
            ],
        ]);
    }

    /** Fuente: IMPRESIONES.xlsx */
    private function plantillaImpresion(Process $process): void
    {
        $plantilla = $this->plantilla($process, 'Inspeccion de calidad en bolsas impresas', '1', 1, 1);

        $conforme = ['valores' => ['B', 'M'], 'conformes' => ['B']];

        $this->parametros($plantilla, [
            [
                'code' => 'adherencia', 'label' => 'Adherencia', 'tipo' => 'select',
                'spec_modo' => TP::MODO_OPCIONES, 'opciones' => $conforme,
                'promediar' => false,
            ],
            [
                'code' => 'tonalidad', 'label' => 'Tonalidad', 'tipo' => 'select',
                'spec_modo' => TP::MODO_OPCIONES, 'opciones' => $conforme,
                'promediar' => false,
            ],
            [
                'code' => 'contenido_sello', 'label' => 'Contenido de sello', 'tipo' => 'select',
                'spec_modo' => TP::MODO_OPCIONES, 'opciones' => $conforme,
                'promediar' => false,
            ],
        ]);
    }

    /**
     * Fuente: CORTE Y COSTURA.xlsx (Rev:2).
     * Caracteristicas medidas sobre hasta 13 muestras con promedio.
     */
    private function plantillaCorteCostura(Process $process): void
    {
        $plantilla = $this->plantilla($process, 'Ensayos de Calidad en Bolsas de Polipropileno', '2', 8, 13);

        $this->parametros($plantilla, [
            [
                'code' => 'ancho', 'label' => 'Ancho', 'unit' => 'cm',
                'spec_modo' => TP::MODO_OBJETIVO_TOL,
                'spec_tolerancia' => 1,
                'spec_desde_producto' => 'ancho_nominal',
                'spec_label' => '+/- 1',
                'muestras' => 13,
            ],
            [
                'code' => 'largo_util', 'label' => 'Largo util', 'unit' => 'cm',
                'spec_modo' => TP::MODO_OBJETIVO_TOL,
                'spec_tolerancia' => 1,
                'spec_desde_producto' => 'largo_nominal',
                'spec_label' => '+/- 1',
                'muestras' => 13,
            ],
            [
                // Tolerancia por defecto 3%: reproduce los veredictos P.C / P.OBS
                // que Calidad registro en la planilla. A confirmar por Calidad.
                'code' => 'peso', 'label' => 'Peso', 'unit' => 'g',
                'spec_modo' => TP::MODO_OBJETIVO_PCT,
                'spec_tolerancia_pct' => 3,
                'spec_desde_producto' => 'peso_nominal',
                'spec_label' => '+/- 3%',
                'muestras' => 13,
            ],
            [
                'code' => 'corte', 'label' => 'Corte', 'tipo' => 'select',
                'spec_modo' => TP::MODO_OPCIONES,
                'opciones' => ['valores' => ['B', 'M'], 'conformes' => ['B']],
                'muestras' => 13, 'promediar' => false,
            ],
            [
                // Rango tomado del historico observado (2,4 a 3,0). A confirmar por Calidad.
                'code' => 'costura', 'label' => 'Costura', 'unit' => 'cm',
                'spec_modo' => TP::MODO_RANGO,
                'spec_min' => 2, 'spec_max' => 3,
                'muestras' => 13,
            ],
        ]);
    }

    private function plantilla(Process $process, string $name, string $revision, int $default, int $max): TestTemplate
    {
        $plantilla = TestTemplate::updateOrCreate(
            ['process_id' => $process->id, 'revision' => $revision],
            [
                'name' => $name,
                'muestras_default' => $default,
                'muestras_max' => $max,
                'active' => true,
            ]
        );

        $plantilla->activar();

        return $plantilla;
    }

    /** @param  array<int, array<string, mixed>>  $parametros */
    private function parametros(TestTemplate $plantilla, array $parametros): void
    {
        foreach ($parametros as $i => $parametro) {
            TP::updateOrCreate(
                [
                    'test_template_id' => $plantilla->id,
                    'code' => $parametro['code'],
                    'grupo' => $parametro['grupo'] ?? null,
                ],
                array_merge([
                    'unit' => null,
                    'tipo' => 'numeric',
                    'spec_modo' => TP::MODO_LIBRE,
                    'muestras' => 1,
                    'promediar' => true,
                    'requerido' => false,
                    'orden' => $i + 1,
                ], $parametro)
            );
        }
    }
}
