<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Administrador: configura catalogos, plantillas y usuarios. */
    public const ADMIN = 'admin';

    /** Calidad: carga lotes, inspecciones y emite boletas. */
    public const CALIDAD = 'calidad';

    /** Lectura: solo consulta y reimprime. */
    public const LECTURA = 'lectura';

    public const ROLES = [
        self::ADMIN => 'Administrador',
        self::CALIDAD => 'Control de Calidad',
        self::LECTURA => 'Solo lectura',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function esAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }

    /** Puede crear y editar lotes e inspecciones. */
    public function puedeEditar(): bool
    {
        return in_array($this->role, [self::ADMIN, self::CALIDAD], true);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
