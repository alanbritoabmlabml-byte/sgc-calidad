<?php

namespace Database\Seeders;

use App\Models\Inspection;
use App\Models\Lot;
use App\Models\Machine;
use App\Models\Measurement;
use App\Models\Process;
use App\Models\Product;
use App\Models\Sector;
use App\Models\TestTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostracion con lecturas reales de las planillas del 13-08.
 * Sirve para recorrer el sistema con informacion conocida y comparar los
 * veredictos del sistema contra los que anoto Calidad en el Excel.
 *
 * Se ejecuta a mano:  php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $sector = Sector::where('code', 'RAFIA')->firstOrFail();
        $usuario = User::where('role', User::CALIDAD)->first() ?? User::firstOrFail();

        $tejido = Process::where('sector_id', $sector->id)->where('code', 'IT')->firstOrFail();
        $corte = Process::where('sector_id', $sector->id)->where('code', 'ICC')->firstOrFail();

        // ---- Tejido: filas de la planilla TEJIDOS.xlsx, hoja 13-08 ----
        // producto, telar, tarjeta, peso rollo, hora, gramaje, ancho, dens T, dens U,
        // peso muestra, tension T, tension U, elong T, elong U, estado que anoto Calidad
        $filasTejido = [
            ['Bl 65x108/66', 'T-2', '7', 250, '11:15', 62, 65.5, 34, 39, 96.2, 72.52, 94.13, 20.16, 23, Inspection::OBSERVADO],
            ['Az 56x106/66', 'T-6', '8', 211, '15:45', 66, 56.7, 34, 40, 81, 79.22, 89.66, 18.49, 18.99, Inspection::CONFORME],
            ['Am 65x108/66', 'T-3', '9', 278, '17:10', 63, 66.5, 33, 38, 94.1, 81.8, 91.8, 15.28, 19.9, Inspection::OBSERVADO],
            ['Bl 65x104/66', 'T-2', '12', 58, '18:35', 65, 65.5, 34, 37, 95.9, 84.82, 99.99, 29.2, 19.77, Inspection::CONFORME],
        ];

        $rollos = [];

        foreach ($filasTejido as $fila) {
            [$codigoProducto, $telar, $tarjeta, $peso, $hora] = $fila;
            $estado = $fila[14];

            $lote = $this->lote($sector, $usuario, $codigoProducto, $telar, $tarjeta, $peso, $hora);

            $inspeccion = $this->inspeccion($lote, $tejido, $usuario, [
                'hora' => $hora,
                'operador' => 'Moises Gongora',
                'responsable' => 'Fabiola',
                'estado' => $estado,
            ]);

            $this->mediciones($inspeccion, [
                'gramaje' => [$fila[5]],
                'ancho' => [$fila[6]],
                'densidad|Trama' => [$fila[7]],
                'densidad|Urdimbre' => [$fila[8]],
                'peso_muestra' => [$fila[9]],
                'tension|Trama' => [$fila[10]],
                'tension|Urdimbre' => [$fila[11]],
                'elongacion|Trama' => [$fila[12]],
                'elongacion|Urdimbre' => [$fila[13]],
            ]);

            $inspeccion->update(['published_at' => now()]);
            $lote->recalcularEstado();

            $rollos[$tarjeta] = $lote;
        }

        // ---- Corte y Costura: bloques de CORTE Y COSTURA.xlsx, hoja 13-08 ----
        // El campo "N. LOTE" de esa planilla es la tarjeta del rollo de Tejido:
        // ahi esta el vinculo de trazabilidad que el sistema formaliza.
        $filasCorte = [
            [
                'producto' => 'Bl 65x104/66', 'telar' => 'T-2', 'tarjeta' => '2', 'rolloOrigen' => '7',
                'bolsas' => 2598, 'falladas' => 61,
                'ancho' => [65.4, 65.5, 65.5, 65.5, 65.6, 66, 66, 65.5],
                'largo_util' => [103.9, 103.5, 103.6, 103.8, 104, 104, 104, 104],
                'peso' => [95.8, 96.07, 96.8, 95.8, 100.2, 100.1, 94.9, 90.7],
                'costura' => [2.5, 2.6, 2.5, 2.5, 2.5, 2.5, 2.5, 2.6],
                'observacion' => '92 Grs. De acuerdo al calculo del codigo hay variacion de peso en varias muestras',
                'estado' => Inspection::OBSERVADO,
            ],
            [
                'producto' => 'Az 56x102/66', 'telar' => 'T-6', 'tarjeta' => '3', 'rolloOrigen' => '8',
                'bolsas' => 2591, 'falladas' => 45,
                'ancho' => [56, 56.5, 56.8, 56.5, 56.8, 56.8, 56.8, 57],
                'largo_util' => [102, 102.5, 102.5, 102.5, 102.6, 102.5, 102.4, 102.4],
                'peso' => [80.2, 80.3, 80.3, 80.5, 82, 81.9, 81.9, 79.87],
                'costura' => [2.5, 2.5, 2.6, 2.6, 2.5, 2.6, 2.4, 2.5],
                'observacion' => '79 Grs. De acuerdo al calculo del codigo',
                'estado' => Inspection::CONFORME,
            ],
            [
                'producto' => 'Am 65x104/66', 'telar' => 'T-3', 'tarjeta' => '4', 'rolloOrigen' => '9',
                'bolsas' => 2997, 'falladas' => 44,
                'ancho' => [65.5, 65.5, 65.5, 65.5, 66.5, 66.5, 66.5, 66.5],
                'largo_util' => [103.5, 103.5, 103.5, 103.5, 103, 103.5, 103.5, 103.35],
                'peso' => [93.5, 93.4, 93.5, 93.5, 94.1, 91.4, 91.4, 91.5],
                'costura' => [2.5, 2.5, 2.5, 2.5, 2.5, 2.5, 2.5, 2.5],
                'observacion' => null,
                'estado' => Inspection::CONFORME,
            ],
        ];

        foreach ($filasCorte as $bloque) {
            $lote = $this->lote(
                $sector, $usuario,
                $bloque['producto'], $bloque['telar'], $bloque['tarjeta'],
                null, '07:11',
                $rollos[$bloque['rolloOrigen']] ?? null
            );

            $inspeccion = $this->inspeccion($lote, $corte, $usuario, [
                'hora' => '07:11',
                'operador' => 'Javier Pastedo',
                'responsable' => 'Fabiola',
                'total_unidades' => $bloque['bolsas'],
                'total_falladas' => $bloque['falladas'],
                'observacion' => $bloque['observacion'],
                'estado' => $bloque['estado'],
            ]);

            $this->mediciones($inspeccion, [
                'ancho' => $bloque['ancho'],
                'largo_util' => $bloque['largo_util'],
                'peso' => $bloque['peso'],
                // El corte salio bueno en las ocho muestras.
                'corte' => array_fill(0, 8, 'B'),
                'costura' => $bloque['costura'],
            ]);

            $inspeccion->update(['published_at' => now()]);
            $lote->recalcularEstado();
        }
    }

    private function lote(
        Sector $sector,
        User $usuario,
        string $codigoProducto,
        string $codigoMaquina,
        string $tarjeta,
        ?float $peso,
        string $hora,
        ?Lot $origen = null,
    ): Lot {
        return Lot::create([
            'code' => Lot::generarCodigo($sector),
            'sector_id' => $sector->id,
            'product_id' => Product::where('sector_id', $sector->id)->where('code', $codigoProducto)->value('id'),
            'machine_id' => Machine::where('sector_id', $sector->id)->where('code', $codigoMaquina)->value('id'),
            'nro_tarjeta' => $tarjeta,
            'fecha' => '2026-08-13',
            'hora' => $hora,
            'turno' => 'Dia',
            'peso_neto' => $peso,
            'source_lot_id' => $origen?->id,
            'created_by' => $usuario->id,
        ]);
    }

    /** @param  array<string, mixed>  $datos */
    private function inspeccion(Lot $lote, Process $proceso, User $usuario, array $datos): Inspection
    {
        return Inspection::create([
            'code' => Inspection::generarCodigo($proceso),
            'lot_id' => $lote->id,
            'process_id' => $proceso->id,
            'test_template_id' => $proceso->activeTemplate->id,
            'fecha' => '2026-08-13',
            'machine_id' => $lote->machine_id,
            'turno' => 'Dia',
            'public_token' => Inspection::generarToken(),
            'user_id' => $usuario->id,
            ...$datos,
        ]);
    }

    /**
     * Carga las mediciones evaluando cada valor contra la especificacion,
     * igual que lo hace el formulario.
     *
     * @param  array<string, array<int, mixed>>  $valores  clave "code" o "code|grupo"
     */
    private function mediciones(Inspection $inspeccion, array $valores): void
    {
        /** @var TestTemplate $plantilla */
        $plantilla = $inspeccion->template->load('parameters');
        $producto = $inspeccion->lot->product;

        foreach ($valores as $llave => $muestras) {
            [$code, $grupo] = array_pad(explode('|', $llave), 2, null);

            $parametro = $plantilla->parameters
                ->where('code', $code)
                ->firstWhere('grupo', $grupo);

            if (! $parametro) {
                continue;
            }

            foreach ($muestras as $i => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }

                $esNumero = $parametro->tipo === 'numeric' && is_numeric($valor);

                Measurement::create([
                    'inspection_id' => $inspeccion->id,
                    'test_parameter_id' => $parametro->id,
                    'muestra' => $i + 1,
                    'valor_num' => $esNumero ? (float) $valor : null,
                    'valor_texto' => $esNumero ? null : (string) $valor,
                    'en_especificacion' => $parametro->evaluar($valor, $producto),
                ]);
            }
        }
    }
}
