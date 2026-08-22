<?php

namespace App\Models;

use App\Support\Permisos;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'permissions', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Administrador: acceso total, incluida la gestion de usuarios. */
    public const ADMIN = 'admin';

    /** Calidad: registros, emision de boletas y creacion de plantillas. */
    public const CALIDAD = 'calidad';

    /** Gerencia: solo consulta de tableros, inspecciones y boletas. */
    public const GERENCIA = 'gerencia';

    /** Solo lectura de la operacion. */
    public const LECTURA = 'lectura';

    public const ROLES = [
        self::ADMIN => 'Administrador',
        self::CALIDAD => 'Control de Calidad',
        self::GERENCIA => 'Gerencia',
        self::LECTURA => 'Solo lectura',
    ];

    public const ROLES_DESCRIPCION = [
        self::ADMIN => 'Acceso total al sistema, incluida la configuracion y los usuarios.',
        self::CALIDAD => 'Carga lotes e inspecciones, emite boletas, imprime etiquetas y crea plantillas de ensayo.',
        self::GERENCIA => 'Solo consulta: tableros gerenciales, ultimas inspecciones y boletas.',
        self::LECTURA => 'Solo consulta de la operacion, con reimpresion de etiquetas.',
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
            'permissions' => 'array',
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

    /**
     * Permisos efectivos del usuario. Si nunca se le asignaron explicitamente,
     * se usa el preset de su rol: asi un usuario creado antes de existir los
     * permisos granulares sigue funcionando.
     *
     * @return array<int, string>
     */
    public function permisos(): array
    {
        if ($this->esAdmin()) {
            return Permisos::todos();
        }

        $propios = $this->permissions;

        return is_array($propios) && $propios !== []
            ? $propios
            : Permisos::preset($this->role);
    }

    public function puede(string $permiso): bool
    {
        return $this->esAdmin() || in_array($permiso, $this->permisos(), true);
    }

    /** @param  array<int, string>  $permisos */
    public function puedeAlguno(array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if ($this->puede($permiso)) {
                return true;
            }
        }

        return false;
    }

    /** Puede modificar datos de operacion (lotes o inspecciones). */
    public function puedeEditar(): bool
    {
        return $this->puedeAlguno([
            Permisos::LOTES_CREAR,
            Permisos::LOTES_EDITAR,
            Permisos::INSPECCIONES_CREAR,
            Permisos::INSPECCIONES_EDITAR,
        ]);
    }

    /** Tiene acceso a alguna pantalla de configuracion. */
    public function puedeConfigurar(): bool
    {
        return $this->puedeAlguno([
            Permisos::PLANTILLAS_VER,
            Permisos::CATALOGOS_VER,
            Permisos::USUARIOS_GESTIONAR,
        ]);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /**
     * Nombre completo tal como se imprime en la boleta, en el campo
     * "Responsable de Control de Calidad".
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim($this->name);
    }
}
