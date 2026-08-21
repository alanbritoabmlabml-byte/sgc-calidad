<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Machine extends Model
{
    use HasFactory;

    public const TIPOS = ['telar', 'extrusora', 'corte', 'impresora', 'otro'];

    protected $fillable = ['sector_id', 'code', 'name', 'tipo', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
