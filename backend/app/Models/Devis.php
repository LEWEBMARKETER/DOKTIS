<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devis extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $table = 'devis';

    protected $fillable = [
        'cabinet_id', 'patient_id', 'facture_id', 'numero', 'montant_total',
        'statut', 'date_emission', 'date_validite', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'date',
            'date_validite' => 'date',
            'montant_total' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(DevisLigne::class);
    }
}
