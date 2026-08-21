<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sector_id', 'code', 'name', 'color',
        'ancho_nominal', 'largo_nominal', 'gramaje_nominal', 'denier_nominal', 'peso_nominal',
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
            'active' => 'boolean',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->name ? "{$this->code} - {$this->name}" : $this->code;
    }
}
