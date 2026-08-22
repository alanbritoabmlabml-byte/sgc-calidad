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
        'created_by', 'activada_at', 'activada_por',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'activada_at' => 'datetime'];
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

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activada_por');
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->name} (Rev:{$this->revision})";
    }

    /**
     * Activa esta plantilla y desactiva las demas del mismo proceso.
     * Las inspecciones ya emitidas conservan la plantilla con la que se llenaron.
     *
     * Se registra quien y cuando: activar una revision cambia la especificacion
     * contra la que se va a evaluar de aca en adelante, y eso tiene que quedar
     * atribuido a una persona.
     */
    public function activar(?User $usuario = null): void
    {
        static::where('process_id', $this->process_id)
            ->whereKeyNot($this->getKey())
            ->update(['active' => false]);

        $this->update([
            'active' => true,
            'activada_at' => now(),
            'activada_por' => $usuario?->id ?? $this->activada_por,
        ]);
    }
}
