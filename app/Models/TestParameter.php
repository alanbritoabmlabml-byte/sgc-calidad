<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestParameter extends Model
{
    use HasFactory;

    public const TIPOS = ['numeric', 'text', 'select', 'bool'];

    /** Modos de especificacion soportados. */
    public const MODO_RANGO = 'rango';                 // spec_min <= v <= spec_max
    public const MODO_OBJETIVO_TOL = 'objetivo_tol';   // |v - objetivo| <= tolerancia
    public const MODO_OBJETIVO_PCT = 'objetivo_pct';   // |v - objetivo| <= objetivo * pct/100
    public const MODO_MINIMO = 'minimo';               // v >= spec_min
    public const MODO_MAXIMO = 'maximo';               // v <= spec_max
    public const MODO_OPCIONES = 'opciones';           // valor dentro de opciones.conformes
    public const MODO_LIBRE = 'libre';                 // sin evaluacion automatica

    public const MODOS = [
        self::MODO_RANGO => 'Rango (minimo y maximo)',
        self::MODO_OBJETIVO_TOL => 'Objetivo mas/menos tolerancia',
        self::MODO_OBJETIVO_PCT => 'Objetivo mas/menos porcentaje',
        self::MODO_MINIMO => 'Solo minimo',
        self::MODO_MAXIMO => 'Solo maximo',
        self::MODO_OPCIONES => 'Valores conformes',
        self::MODO_LIBRE => 'Sin evaluacion',
    ];

    protected $fillable = [
        'test_template_id', 'code', 'label', 'unit', 'tipo', 'grupo',
        'spec_modo', 'spec_min', 'spec_max', 'spec_objetivo',
        'spec_tolerancia', 'spec_tolerancia_pct', 'spec_label', 'spec_desde_producto',
        'muestras', 'promediar', 'opciones', 'orden', 'requerido', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'spec_min' => 'float',
            'spec_max' => 'float',
            'spec_objetivo' => 'float',
            'spec_tolerancia' => 'float',
            'spec_tolerancia_pct' => 'float',
            'opciones' => 'array',
            'promediar' => 'boolean',
            'requerido' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TestTemplate::class, 'test_template_id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class, 'test_parameter_id');
    }

    /** Etiqueta completa para mostrar, por ejemplo "Tension (kgf) - U". */
    public function getEtiquetaAttribute(): string
    {
        $texto = $this->label;

        if ($this->unit) {
            $texto .= " ({$this->unit})";
        }

        if ($this->grupo) {
            $texto .= " - {$this->grupo}";
        }

        return $texto;
    }

    /**
     * Resuelve el objetivo de la especificacion. Si el parametro esta atado a un
     * atributo del producto (spec_desde_producto), el objetivo lo define el codigo
     * de producto del lote; si no, el valor fijo cargado en la plantilla.
     */
    public function objetivo(?Product $producto = null): ?float
    {
        if ($this->spec_desde_producto && $producto) {
            $valor = $producto->{$this->spec_desde_producto};

            if ($valor !== null) {
                return (float) $valor;
            }
        }

        return $this->spec_objetivo;
    }

    /**
     * Limites efectivos [min, max] del parametro. Cualquiera puede ser null
     * cuando el modo no lo acota (MODO_MINIMO, por ejemplo, no tiene techo).
     *
     * @return array{0: ?float, 1: ?float}
     */
    public function limites(?Product $producto = null): array
    {
        $objetivo = $this->objetivo($producto);

        return match ($this->spec_modo) {
            self::MODO_RANGO => [$this->spec_min, $this->spec_max],
            self::MODO_MINIMO => [$this->spec_min, null],
            self::MODO_MAXIMO => [null, $this->spec_max],

            self::MODO_OBJETIVO_TOL => $objetivo === null || $this->spec_tolerancia === null
                ? [null, null]
                : [$objetivo - $this->spec_tolerancia, $objetivo + $this->spec_tolerancia],

            self::MODO_OBJETIVO_PCT => $objetivo === null || $this->spec_tolerancia_pct === null
                ? [null, null]
                : [
                    $objetivo * (1 - $this->spec_tolerancia_pct / 100),
                    $objetivo * (1 + $this->spec_tolerancia_pct / 100),
                ],

            default => [null, null],
        };
    }

    /**
     * Evalua un valor contra la especificacion.
     * true = conforme, false = fuera de especificacion,
     * null = sin especificacion aplicable o valor vacio.
     */
    public function evaluar(mixed $valor, ?Product $producto = null): ?bool
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($this->spec_modo === self::MODO_OPCIONES) {
            $conformes = $this->opciones['conformes'] ?? [];

            if ($conformes === []) {
                return null;
            }

            return in_array((string) $valor, array_map('strval', $conformes), true);
        }

        if ($this->spec_modo === self::MODO_LIBRE || ! is_numeric($valor)) {
            return null;
        }

        [$min, $max] = $this->limites($producto);

        if ($min === null && $max === null) {
            return null;
        }

        $valor = (float) $valor;

        if ($min !== null && $valor < $min) {
            return false;
        }

        if ($max !== null && $valor > $max) {
            return false;
        }

        return true;
    }

    /**
     * Texto de la especificacion para la boleta. Respeta spec_label cuando
     * Calidad cargo uno propio para replicar la planilla original.
     */
    public function specTexto(?Product $producto = null): string
    {
        if ($this->spec_label) {
            return $this->spec_label;
        }

        $objetivo = $this->objetivo($producto);

        return match ($this->spec_modo) {
            self::MODO_RANGO => $this->num($this->spec_min).' a '.$this->num($this->spec_max),
            self::MODO_MINIMO => 'min. '.$this->num($this->spec_min),
            self::MODO_MAXIMO => 'max. '.$this->num($this->spec_max),
            self::MODO_OBJETIVO_TOL => $objetivo === null
                ? '-'
                : $this->num($objetivo).' +/- '.$this->num($this->spec_tolerancia),
            self::MODO_OBJETIVO_PCT => $objetivo === null
                ? '-'
                : $this->num($objetivo).' +/- '.$this->num($this->spec_tolerancia_pct).'%',
            self::MODO_OPCIONES => implode(' / ', $this->opciones['conformes'] ?? []),
            default => '-',
        };
    }

    /** Formatea un numero quitando decimales sobrantes. */
    private function num(?float $valor): string
    {
        if ($valor === null) {
            return '-';
        }

        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
