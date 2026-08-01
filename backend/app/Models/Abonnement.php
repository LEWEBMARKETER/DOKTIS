<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Abonnement extends Model
{
    protected $fillable = [
        'cabinet_id', 'plan', 'prix', 'cycle_facturation', 'statut',
        'date_debut', 'date_fin', 'mode_paiement', 'taux_commission',
    ];

    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
            'taux_commission' => 'decimal:2',
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }
}
