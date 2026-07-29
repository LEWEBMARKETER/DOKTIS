<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consultation extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $fillable = [
        'cabinet_id', 'patient_id', 'praticien_id', 'rendez_vous_id',
        'date_consultation', 'motif', 'diagnostic', 'observations', 'traitement',
        'poids', 'tension', 'temperature', 'prochaine_visite_recommandee', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_consultation' => 'datetime',
            'prochaine_visite_recommandee' => 'date',
            'poids' => 'decimal:2',
            'temperature' => 'decimal:1',
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

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function ordonnances(): HasMany
    {
        return $this->hasMany(Ordonnance::class);
    }
}
