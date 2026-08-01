<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facture extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $fillable = [
        'cabinet_id', 'patient_id', 'consultation_id', 'numero', 'montant_total',
        'montant_paye', 'statut', 'date_emission', 'date_echeance', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'date',
            'date_echeance' => 'date',
            'montant_total' => 'decimal:2',
            'montant_paye' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(FactureLigne::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function getSoldeAttribute(): float
    {
        return round((float) $this->montant_total - (float) $this->montant_paye, 2);
    }

    public function rafraichirStatutPaiement(): void
    {
        $paye = round((float) $this->montant_paye, 2);
        $total = round((float) $this->montant_total, 2);

        $this->statut = match (true) {
            $paye <= 0 => $this->statut === 'annulee' ? 'annulee' : ($this->statut === 'brouillon' ? 'brouillon' : 'envoyee'),
            $paye < $total => 'partiellement_payee',
            default => 'payee',
        };

        $this->save();
    }
}
