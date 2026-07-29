<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use BelongsToCabinet, HasFactory, SoftDeletes;

    protected $fillable = [
        'cabinet_id', 'numero_dossier', 'nom', 'prenom', 'date_naissance', 'sexe',
        'telephone', 'email', 'adresse', 'groupe_sanguin', 'allergies',
        'antecedents_medicaux', 'contact_urgence_nom', 'contact_urgence_telephone',
        'notes', 'actif', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'actif' => 'boolean',
        ];
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function plansTraitement(): HasMany
    {
        return $this->hasMany(PlanTraitement::class);
    }

    public function ordonnances(): HasMany
    {
        return $this->hasMany(Ordonnance::class);
    }

    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
