<?php

namespace App\Models;

use App\Enums\ProduitAbonnement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Abonnement extends Model
{
    protected $fillable = [
        'cabinet_id', 'produit', 'plan', 'prix', 'cycle_facturation', 'statut',
        'date_debut', 'date_fin', 'mode_paiement', 'taux_commission',
    ];

    protected function casts(): array
    {
        return [
            'produit' => ProduitAbonnement::class,
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

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementAbonnement::class)->orderByDesc('date_paiement');
    }

    /**
     * Expiré au sens strict (date_fin dépassée), indépendamment de la
     * période de grâce configurable (voir config('directory.jours_grace_abonnement')
     * et App\Console\Commands\ExpirerAbonnementsDirectory).
     */
    public function estExpire(): bool
    {
        return $this->date_fin !== null && $this->date_fin->isPast();
    }
}
