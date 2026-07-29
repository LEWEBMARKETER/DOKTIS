<?php

namespace App\Models;

use App\Enums\RoleUtilisateur;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'cabinet_id',
        'name',
        'email',
        'telephone',
        'role',
        'specialite',
        'password',
        'actif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleUtilisateur::class,
            'actif' => 'boolean',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }

    public function hasRole(RoleUtilisateur|string ...$roles): bool
    {
        $values = array_map(fn ($r) => $r instanceof RoleUtilisateur ? $r->value : $r, $roles);

        return in_array($this->role->value, $values, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === RoleUtilisateur::SuperAdmin;
    }
}
