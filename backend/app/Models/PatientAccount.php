<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte de l'application DOKTA Patient. Indépendant des cabinets : un même
 * compte peut être lié à un dossier (Patient) dans plusieurs cabinets.
 */
class PatientAccount extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom', 'prenom', 'email', 'telephone', 'password', 'date_naissance', 'sexe',
        'photo_path', 'contact_urgence_nom', 'contact_urgence_telephone', 'actif',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'email_verified_at' => 'datetime',
            'telephone_verifie_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    /**
     * Les dossiers médicaux de ce patient dans chaque cabinet où il a consulté.
     */
    public function dossiers(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
