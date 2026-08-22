<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    /** Tipos de tejido de rafia. */
    public const TIPOS_TEJIDO = ['Plano', 'Leno', 'Laminado', 'Plano laminado'];

    protected $fillable = [
        'sector_id', 'code', 'name', 'color',
        'norma', 'tipo_tejido', 'tratamiento_uv', 'cliente',
        'ancho_nominal', 'largo_nominal', 'gramaje_nominal', 'denier_nominal', 'peso_nominal',
        'capacidad_kg', 'densidad_urdimbre_nominal', 'densidad_trama_nominal',
        'observaciones_tecnicas', 'actualizado_por', 'ficha_actualizada_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'ancho_nominal' => 'decimal:2',
            'largo_nominal' => 'decimal:2',
            'gramaje_nominal' => 'decimal:2',
            'denier_nominal' => 'decimal:2',
            'peso_nominal' => 'decimal:2',
            'capacidad_kg' => 'decimal:2',
            'densidad_urdimbre_nominal' => 'decimal:2',
            'densidad_trama_nominal' => 'decimal:2',
            'ficha_actualizada_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->name ? "{$this->code} - {$this->name}" : $this->code;
    }

    /**
     * Nombre para la etiqueta impresa: el nombre comercial seguido del gramaje,
     * que es lo que identifica el producto en planta.
     * Ejemplo: "Saco Blanco 65x104 - 66 g/m2".
     */
    public function getNombreConGramajeAttribute(): string
    {
        $nombre = $this->name ?: $this->code;

        if ($this->gramaje_nominal === null) {
            return $nombre;
        }

        $gramaje = rtrim(rtrim(number_format((float) $this->gramaje_nominal, 2, ',', ''), '0'), ',');

        return "{$nombre} - {$gramaje} g/m2";
    }

    /**
     * Cuantos nominales estan cargados sobre el total. Sirve para avisar que la
     * ficha tecnica esta incompleta: un peso nominal faltante deja al ensayo de
     * Corte y Costura sin especificacion contra la que evaluar.
     *
     * @return array{cargados: int, total: int, faltan: array<int, string>}
     */
    public function completitudNominales(): array
    {
        $requeridos = [
            'ancho_nominal' => 'ancho',
            'largo_nominal' => 'largo util',
            'gramaje_nominal' => 'gramaje',
            'peso_nominal' => 'peso de bolsa',
        ];

        $faltan = [];

        foreach ($requeridos as $campo => $etiqueta) {
            if ($this->{$campo} === null) {
                $faltan[] = $etiqueta;
            }
        }

        return [
            'cargados' => count($requeridos) - count($faltan),
            'total' => count($requeridos),
            'faltan' => $faltan,
        ];
    }

    public function fichaCompleta(): bool
    {
        return $this->completitudNominales()['faltan'] === [];
    }
}
