<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Lot extends Model
{
    use HasFactory;

    public const ABIERTO = 'abierto';
    public const LIBERADO = 'liberado';
    public const BLOQUEADO = 'bloqueado';
    public const CERRADO = 'cerrado';

    public const ESTADOS = [
        self::ABIERTO => 'Abierto',
        self::LIBERADO => 'Liberado',
        self::BLOQUEADO => 'Bloqueado',
        self::CERRADO => 'Cerrado',
    ];

    protected $fillable = [
        'code', 'sector_id', 'product_id', 'machine_id',
        'nro_tarjeta', 'nro_lote_produccion',
        'fecha', 'hora', 'turno', 'peso_neto',
        'source_lot_id', 'estado', 'observacion', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'peso_neto' => 'decimal:3',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    /** Lote de origen. En Rafia: el rollo de Tejido que alimento a Corte y Costura. */
    public function sourceLot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_lot_id');
    }

    /** Lotes que se produjeron a partir de este. */
    public function derivedLots(): HasMany
    {
        return $this->hasMany(self::class, 'source_lot_id');
    }

    /**
     * Genera el siguiente codigo de lote del sector: RAF-26-00001.
     * Se serializa con una transaccion para evitar codigos duplicados
     * cuando dos inspectores cargan lotes al mismo tiempo.
     */
    public static function generarCodigo(Sector $sector, ?int $anio = null): string
    {
        $anio ??= (int) now()->format('y');
        $prefijo = "{$sector->prefijo_lote}-{$anio}-";

        return DB::transaction(function () use ($sector, $prefijo) {
            $ultimo = static::where('sector_id', $sector->id)
                ->where('code', 'like', $prefijo.'%')
                ->lockForUpdate()
                ->orderByDesc('code')
                ->value('code');

            $siguiente = $ultimo
                ? ((int) substr($ultimo, strlen($prefijo))) + 1
                : 1;

            return $prefijo.str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
        });
    }

    /** Inspeccion registrada para un proceso dado (la ultima si hubo reinspeccion). */
    public function inspeccionDe(Process $process): ?Inspection
    {
        return $this->inspections
            ->where('process_id', $process->id)
            ->sortByDesc('id')
            ->first();
    }

    /**
     * Inspeccion que vale para un proceso, propia o heredada del lote de origen.
     *
     * Un lote de bolsas no repite el control de Tejido: ese control esta en el
     * rollo que consume. Para saber si el lote esta liberado hay que mirar la
     * cadena completa, no solo sus inspecciones propias.
     */
    public function inspeccionEfectivaDe(Process $process): ?Inspection
    {
        return $this->inspeccionDe($process)
            ?? $this->sourceLot?->inspeccionEfectivaDe($process);
    }

    /**
     * Determina si el lote puede inspeccionarse en un proceso.
     * La regla del requerimiento: Corte y Costura no avanza si Tejido
     * no fue inspeccionado, o si su inspeccion salio RECHAZADO.
     *
     * Se revisan TODOS los procesos anteriores marcados como bloqueantes, no
     * solo el inmediato: Impresion es opcional y no debe habilitar un salto
     * por encima de Tejido.
     *
     * @return array{permitido: bool, motivo: ?string}
     */
    public function puedeInspeccionar(Process $process): array
    {
        $anteriores = Process::where('sector_id', $process->sector_id)
            ->where('orden', '<', $process->orden)
            ->where('bloquea_siguiente', true)
            ->where('active', true)
            ->orderBy('orden')
            ->get();

        foreach ($anteriores as $anterior) {
            // El control previo puede estar en este lote o en su lote de origen
            // (el rollo de Tejido que alimento a Corte y Costura).
            $previa = $this->inspeccionEfectivaDe($anterior);

            if (! $previa) {
                return [
                    'permitido' => false,
                    'motivo' => "Falta la inspeccion de {$anterior->name}. No se puede avanzar a {$process->name}.",
                ];
            }

            if ($previa->estado === Inspection::RECHAZADO) {
                return [
                    'permitido' => false,
                    'motivo' => "La inspeccion de {$anterior->name} ({$previa->code}) resulto RECHAZADO. El lote esta bloqueado.",
                ];
            }

            if ($previa->estado === Inspection::PENDIENTE) {
                return [
                    'permitido' => false,
                    'motivo' => "La inspeccion de {$anterior->name} ({$previa->code}) todavia esta PENDIENTE de cierre.",
                ];
            }
        }

        return ['permitido' => true, 'motivo' => null];
    }

    /**
     * Recalcula el estado del lote a partir de sus inspecciones.
     * Bloqueado si alguna salio rechazada; liberado cuando todos los
     * procesos que bloquean estan aprobados.
     */
    public function recalcularEstado(): void
    {
        $this->load('inspections', 'sector.processes', 'sourceLot.inspections');

        if ($this->estado === self::CERRADO) {
            return;
        }

        if ($this->inspections->contains('estado', Inspection::RECHAZADO)) {
            $this->update(['estado' => self::BLOQUEADO]);

            return;
        }

        $puertas = $this->sector->processes->where('bloquea_siguiente', true);

        if ($puertas->isEmpty()) {
            $this->update(['estado' => self::ABIERTO]);

            return;
        }

        // Se libera cuando toda puerta de calidad del sector tiene una inspeccion
        // aprobada, propia o heredada del lote de origen.
        $aprobado = fn (Process $p): bool => in_array(
            $this->inspeccionEfectivaDe($p)?->estado,
            [Inspection::CONFORME, Inspection::OBSERVADO],
            true
        );

        // Un rechazo heredado tambien bloquea: el material viene de un lote no conforme.
        $rechazado = $puertas->contains(
            fn (Process $p) => $this->inspeccionEfectivaDe($p)?->estado === Inspection::RECHAZADO
        );

        if ($rechazado) {
            $this->update(['estado' => self::BLOQUEADO]);

            return;
        }

        $this->update([
            'estado' => $puertas->every($aprobado) ? self::LIBERADO : self::ABIERTO,
        ]);
    }

    public function scopeDelSector(Builder $query, ?int $sectorId): Builder
    {
        return $sectorId ? $query->where('sector_id', $sectorId) : $query;
    }

    public function getBadgeColorAttribute(): string
    {
        return match ($this->estado) {
            self::LIBERADO => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::BLOQUEADO => 'bg-red-100 text-red-800 ring-red-600/20',
            self::CERRADO => 'bg-slate-100 text-slate-700 ring-slate-600/20',
            default => 'bg-amber-100 text-amber-800 ring-amber-600/20',
        };
    }
}
