<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Process extends Model
{
    use HasFactory;

    protected $fillable = [
        'sector_id', 'code', 'name', 'boleta_code', 'etiquetas',
        'orden', 'bloquea_siguiente', 'active',
    ];

    protected function casts(): array
    {
        return [
            'etiquetas' => 'array',
            'bloquea_siguiente' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * Rotulo de un campo de la boleta impresa, con el texto del formulario
     * preimpreso del proceso. Si no se cargo uno, cae al texto generico.
     */
    public function etiqueta(string $clave, string $defecto): string
    {
        return $this->etiquetas[$clave] ?? $defecto;
    }

    /** Titulo de la boleta: "INSPECCION DE TEJIDO (IT)". */
    public function tituloBoleta(): string
    {
        return $this->etiqueta('titulo', 'Inspeccion de '.$this->name);
    }

    /** Titulo del recuadro de veredicto: "ESTADO DE INSPECCION DE TEJIDO". */
    public function tituloEstado(): string
    {
        return $this->etiqueta('titulo_estado', 'Estado de inspeccion');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(TestTemplate::class);
    }

    /** Plantilla vigente del proceso. */
    public function activeTemplate(): HasOne
    {
        return $this->hasOne(TestTemplate::class)->where('active', true);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    /** Proceso inmediatamente anterior en la cadena del sector. */
    public function anterior(): ?self
    {
        return static::where('sector_id', $this->sector_id)
            ->where('orden', '<', $this->orden)
            ->orderByDesc('orden')
            ->first();
    }

    public function siguiente(): ?self
    {
        return static::where('sector_id', $this->sector_id)
            ->where('orden', '>', $this->orden)
            ->orderBy('orden')
            ->first();
    }
}
