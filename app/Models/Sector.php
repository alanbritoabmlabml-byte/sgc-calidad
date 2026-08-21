<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'prefijo_lote', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class)->orderBy('orden');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
