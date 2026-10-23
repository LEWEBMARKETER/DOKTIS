<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compteurs agrégés par jour pour une fiche. Voir la migration
 * create_visites_fiche_table pour le raisonnement "vie privée par défaut".
 */
class VisiteFiche extends Model
{
    protected $table = 'visites_fiche';

    protected $fillable = [
        'cabinet_id', 'date', 'visites', 'visiteurs_uniques',
        'clics_telephone', 'clics_whatsapp', 'clics_itineraire',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }
}
