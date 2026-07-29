<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanTraitement extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $table = 'plans_traitement';

    protected $fillable = [
        'cabinet_id', 'patient_id', 'consultation_id', 'praticien_id', 'titre',
        'description', 'statut', 'cout_estime', 'date_debut', 'date_fin_prevue',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin_prevue' => 'date',
            'cout_estime' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function praticien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'praticien_id');
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function etapes(): HasMany
    {
        return $this->hasMany(PlanTraitementEtape::class)->orderBy('ordre');
    }
}
