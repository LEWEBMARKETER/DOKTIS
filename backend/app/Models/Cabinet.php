<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabinet extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'slug', 'type', 'telephone', 'email', 'adresse', 'ville', 'quartier',
        'pays', 'logo_path', 'plan', 'essai_expire_at', 'actif',
        'latitude', 'longitude', 'description', 'langues_parlees',
        'accepte_urgences', 'accessible_pmr', 'site_web', 'facebook_url', 'instagram_url',
        'visible_annuaire',
    ];

    protected function casts(): array
    {
        return [
            'essai_expire_at' => 'datetime',
            'actif' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'langues_parlees' => 'array',
            'accepte_urgences' => 'boolean',
            'accessible_pmr' => 'boolean',
            'note_moyenne' => 'decimal:2',
            'visible_annuaire' => 'boolean',
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

    public function specialites(): BelongsToMany
    {
        return $this->belongsToMany(Specialite::class, 'cabinet_specialite');
    }

    public function mutuelles(): BelongsToMany
    {
        return $this->belongsToMany(Mutuelle::class, 'cabinet_mutuelle');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CabinetPhoto::class)->orderBy('ordre');
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }

    public function horaires(): HasMany
    {
        return $this->hasMany(CabinetHoraire::class)->orderBy('jour_semaine');
    }

    public function services(): HasMany
    {
        return $this->hasMany(CabinetService::class);
    }

    public function rafraichirNoteMoyenne(): void
    {
        $stats = $this->avis()->where('statut', 'visible')->selectRaw('avg(note) as moyenne, count(*) as total')->first();

        $this->update([
            'note_moyenne' => round((float) $stats->moyenne, 2),
            'nombre_avis' => (int) $stats->total,
        ]);
    }
}
