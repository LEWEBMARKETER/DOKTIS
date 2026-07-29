<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabinet extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'slug', 'type', 'telephone', 'email', 'adresse', 'ville',
        'pays', 'logo_path', 'plan', 'essai_expire_at', 'actif',
    ];

    protected function casts(): array
    {
        return [
            'essai_expire_at' => 'datetime',
            'actif' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
