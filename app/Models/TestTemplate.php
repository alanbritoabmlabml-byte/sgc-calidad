<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_id', 'name', 'revision', 'muestras_default', 'muestras_max', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(TestParameter::class)->orderBy('orden');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->name} (Rev:{$this->revision})";
    }

    /**
     * Activa esta plantilla y desactiva las demas del mismo proceso.
     * Las inspecciones ya emitidas conservan la plantilla con la que se llenaron.
     */
    public function activar(): void
    {
        static::where('process_id', $this->process_id)
            ->whereKeyNot($this->getKey())
            ->update(['active' => false]);

        $this->update(['active' => true]);
    }
}
