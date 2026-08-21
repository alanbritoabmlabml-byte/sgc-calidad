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

    public function getBadgeColorAttribute(): string
    {
        return match ($this->estado) {
            self::CONFORME => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::OBSERVADO => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::RECHAZADO => 'bg-red-100 text-red-800 ring-red-600/20',
            default => 'bg-slate-100 text-slate-700 ring-slate-600/20',
        };
    }
}
