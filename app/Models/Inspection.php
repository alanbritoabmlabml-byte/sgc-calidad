<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Inspection extends Model
{
    use HasFactory;

    /** Estados tal como los usa hoy Calidad en las planillas. */
    public const PENDIENTE = 'PENDIENTE';
    public const CONFORME = 'P.C';
    public const OBSERVADO = 'P.OBS';
    public const RECHAZADO = 'RECHAZADO';

    public const ESTADOS = [
        self::PENDIENTE => 'Pendiente de cierre',
        self::CONFORME => 'P.C - Pasa conforme',
        self::OBSERVADO => 'P.OBS - Pasa con observacion',
        self::RECHAZADO => 'Rechazado / No conforme',
    ];

    /**
     * Texto completo del estado, en mayusculas, para la etiqueta impresa y la
     * boleta. En la etiqueta no sirve la abreviatura: quien la lee en planta o
     * en el deposito del cliente tiene que entenderla sin conocer el codigo.
     */
    public const ESTADOS_COMPLETOS = [
        self::PENDIENTE => 'PENDIENTE DE INSPECCION',
        self::CONFORME => 'PRODUCTO CONFORME',
        self::OBSERVADO => 'PRODUCTO CON OBSERVACION',
        self::RECHAZADO => 'PRODUCTO NO CONFORME',
    ];

    protected $fillable = [
        'code', 'lot_id', 'process_id', 'test_template_id',
        'fecha', 'hora', 'machine_id', 'operador', 'responsable', 'turno',
        'total_unidades', 'total_falladas',
        'observacion', 'observacion_interna', 'estado',
        'public_token', 'published_at', 'etiquetas_impresas_at', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'published_at' => 'datetime',
            'etiquetas_impresas_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TestTemplate::class, 'test_template_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    /**
     * Correlativo por proceso que replica el N. preimpreso de la boleta fisica:
     * IT-000001 para Tejido, ICC-000010 para Corte y Costura.
     */
    public static function generarCodigo(Process $process): string
    {
        $prefijo = Str::upper(preg_replace('/[^A-Z0-9]/i', '', $process->code)).'-';

        return DB::transaction(function () use ($process, $prefijo) {
            $ultimo = static::where('process_id', $process->id)
                ->where('code', 'like', $prefijo.'%')
                ->lockForUpdate()
                ->orderByDesc('code')
                ->value('code');

            $siguiente = $ultimo
                ? ((int) substr($ultimo, strlen($prefijo))) + 1
                : 1;

            return $prefijo.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
        });
    }

    /** Token del QR: aleatorio y no correlativo, para que no se pueda enumerar. */
    public static function generarToken(): string
    {
        do {
            $token = Str::lower(Str::random(24));
        } while (static::where('public_token', $token)->exists());

        return $token;
    }

    /** Bolsas buenas = total - falladas. */
    public function getTotalBuenasAttribute(): ?int
    {
        if ($this->total_unidades === null) {
            return null;
        }

        return $this->total_unidades - (int) $this->total_falladas;
    }

    /** Porcentaje de unidades falladas sobre el total. */
    public function getPorcentajeFalladasAttribute(): ?float
    {
        if (! $this->total_unidades) {
            return null;
        }

        return round(($this->total_falladas / $this->total_unidades) * 100, 2);
    }

    /**
     * Mediciones agrupadas por parametro, con promedio y veredicto.
     * Es la estructura que consumen la boleta y el certificado.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function resumen(): Collection
    {
        $this->loadMissing('template.parameters', 'measurements', 'lot.product');
        $producto = $this->lot->product;

        return $this->template->parameters->map(function (TestParameter $parametro) use ($producto) {
            $valores = $this->measurements
                ->where('test_parameter_id', $parametro->id)
                ->sortBy('muestra');

            $numericos = $valores
                ->pluck('valor_num')
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v) => (float) $v);

            $promedio = $parametro->promediar && $numericos->isNotEmpty()
                ? round($numericos->avg(), 2)
                : null;

            // El veredicto del parametro se resuelve sobre el promedio cuando hay
            // varias muestras, y sobre el valor unico cuando hay una sola.
            $veredicto = $promedio !== null
                ? $parametro->evaluar($promedio, $producto)
                : $parametro->evaluar($valores->first()?->valor_texto ?? $valores->first()?->valor_num, $producto);

            [$min, $max] = $parametro->limites($producto);

            return [
                'parametro' => $parametro,
                'valores' => $valores,
                'promedio' => $promedio,
                'veredicto' => $veredicto,
                'spec' => $parametro->specTexto($producto),
                'min' => $min,
                'max' => $max,
                'fuera' => $valores->filter(fn (Measurement $m) => $m->en_especificacion === false)->count(),
            ];
        });
    }

    /**
     * Estado que sugiere el sistema segun las mediciones. El inspector puede
     * sobreescribirlo: la decision de calidad sigue siendo humana.
     *
     * - Todo dentro de especificacion  -> P.C
     * - Un parametro requerido fuera   -> RECHAZADO
     * - Algun otro parametro fuera     -> P.OBS
     */
    public function estadoSugerido(): string
    {
        $resumen = $this->resumen();

        $requeridoFuera = $resumen->contains(
            fn (array $fila) => $fila['veredicto'] === false && $fila['parametro']->requerido
        );

        if ($requeridoFuera) {
            return self::RECHAZADO;
        }

        $algunoFuera = $resumen->contains(fn (array $fila) => $fila['veredicto'] === false);

        return $algunoFuera ? self::OBSERVADO : self::CONFORME;
    }

    /**
     * Datos que faltan para poder emitir la boleta.
     *
     * Una boleta emitida es el certificado de calidad de un producto vendido:
     * no puede salir con campos en blanco.
     *
     * Devuelve nombres de campo, no frases: el rotulo de la maquina cambia por
     * proceso ("Telar", "Extrusora", "Maquina") y armar una frase con articulo
     * daria concordancias equivocadas. Como lista de campos se lee igual de
     * claro y no depende del genero de cada rotulo.
     *
     * @return array<int, string>
     */
    public function datosFaltantes(): array
    {
        $this->loadMissing('template.parameters', 'measurements', 'lot.product', 'process', 'machine');

        $faltan = [];
        $proceso = $this->process;

        // ---- Cabecera de la boleta ----
        if ($this->estado === self::PENDIENTE) {
            $faltan[] = 'Estado de inspeccion (esta PENDIENTE)';
        }

        if (blank($this->hora)) {
            $faltan[] = 'Hora';
        }

        if ($this->machine_id === null) {
            $faltan[] = $proceso->etiqueta('maquina', 'Maquina');
        }

        if (blank($this->operador)) {
            $faltan[] = 'Nombre del operador';
        }

        if (blank($this->responsable)) {
            $faltan[] = 'Responsable de Control de Calidad';
        }

        // ---- Identificacion del lote ----
        if ($this->lot->product_id === null) {
            $faltan[] = 'Codigo de producto del lote';
        }

        if (blank($this->lot->nro_tarjeta)) {
            $faltan[] = $proceso->etiqueta('tarjeta', 'N. de tarjeta').' del lote';
        }

        // ---- Cantidades, solo en los procesos que las llevan ----
        if ($proceso->requiere_cantidades) {
            if ($this->total_unidades === null) {
                $faltan[] = $proceso->etiqueta('unidades', 'Total de unidades');
            }

            if ($this->total_falladas === null) {
                $faltan[] = $proceso->etiqueta('falladas', 'Falladas');
            }
        }

        // ---- Mediciones: cada caracteristica necesita al menos un valor ----
        $conDato = $this->measurements
            ->filter(fn (Measurement $m) => $m->valor_num !== null || filled($m->valor_texto))
            ->pluck('test_parameter_id')
            ->unique();

        foreach ($this->template->parameters as $parametro) {
            if (! $conDato->contains($parametro->id)) {
                $faltan[] = 'Medicion de '.$parametro->etiqueta;
            }
        }

        return $faltan;
    }

    public function puedeEmitirse(): bool
    {
        return $this->datosFaltantes() === [];
    }

    public function estaPublicada(): bool
    {
        return $this->published_at !== null
            && in_array($this->estado, [self::CONFORME, self::OBSERVADO, self::RECHAZADO], true);
    }

    /** URL publica que se codifica en el QR. */
    public function urlPublica(): string
    {
        return route('certificado', $this->public_token);
    }

    /** "PRODUCTO CONFORME" en lugar de "P.C". */
    public function getEstadoCompletoAttribute(): string
    {
        return self::ESTADOS_COMPLETOS[$this->estado] ?? $this->estado;
    }

    public function getBadgeColorAttribute(): string
    {
        return match ($this->estado) {
            self::CONFORME => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::OBSERVADO => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::RECHAZADO => 'bg-rojo-100 text-rojo-800 ring-rojo-600/20',
            default => 'bg-slate-100 text-slate-700 ring-slate-600/20',
        };
    }
}
