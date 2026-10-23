<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementAbonnement extends Model
{
    protected $table = 'paiements_abonnement';

    protected $fillable = ['abonnement_id', 'montant', 'mode_paiement', 'reference', 'date_paiement', 'enregistre_par_id', 'notes'];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_paiement' => 'date',
        ];
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par_id');
    }
}
