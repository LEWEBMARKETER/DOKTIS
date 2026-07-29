<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ordonnance extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $fillable = [
        'cabinet_id', 'patient_id', 'consultation_id', 'praticien_id',
        'ordonnance_modele_id', 'contenu', 'date_emission',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'date',
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

    public function modele(): BelongsTo
    {
        return $this->belongsTo(OrdonnanceModele::class, 'ordonnance_modele_id');
    }
}
