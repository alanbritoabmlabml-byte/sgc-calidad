<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Measurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_id', 'test_parameter_id', 'muestra',
        'valor_num', 'valor_texto', 'en_especificacion',
    ];

    protected function casts(): array
    {
        return [
            'valor_num' => 'float',
            'en_especificacion' => 'boolean',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(TestParameter::class, 'test_parameter_id');
    }

    /** Valor a mostrar, sea numerico o de texto. */
    public function getValorAttribute(): ?string
    {
        if ($this->valor_texto !== null && $this->valor_texto !== '') {
            return $this->valor_texto;
        }

        if ($this->valor_num === null) {
            return null;
        }

        return rtrim(rtrim(number_format($this->valor_num, 2, '.', ''), '0'), '.');
    }
}
