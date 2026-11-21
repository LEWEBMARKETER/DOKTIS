<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fiche du référentiel national des structures sanitaires (DOKTA Directory).
 */
class EtablissementSante extends Model
{
    protected $table = 'etablissements_sante';

    public const CATEGORIES = [
        'pharmacie' => 'Pharmacie',
        'depot_pharmaceutique' => 'Dépôt pharmaceutique',
        'hopital' => 'Hôpital',
        'clinique' => 'Clinique / Polyclinique',
        'centre_sante' => 'Centre de santé',
        'cabinet_medical' => 'Cabinet médical',
        'cabinet_dentaire' => 'Cabinet dentaire',
        'laboratoire' => "Laboratoire d'analyses",
        'imagerie' => 'Imagerie médicale',
        'opticien' => 'Opticien',
        'kinesitherapie' => 'Kinésithérapie',
        'autre' => 'Autre professionnel de santé',
    ];

    public const STATUTS_VERIFICATION = ['a_verifier', 'verifie', 'ferme'];

    protected $fillable = [
        'code', 'nom', 'slug', 'categorie', 'specialites', 'praticien', 'adresse', 'quartier',
        'ville', 'province', 'pays', 'boite_postale', 'telephone', 'telephones', 'email',
        'site_web', 'horaires', 'ouvert_24h', 'latitude', 'longitude', 'osm_ref', 'sources',
        'source_urls', 'statut_verification', 'verifie_le', 'notes', 'cabinet_id', 'visible_annuaire',
    ];

    protected function casts(): array
    {
        return [
            'specialites' => 'array',
            'telephones' => 'array',
            'sources' => 'array',
            'source_urls' => 'array',
            'ouvert_24h' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verifie_le' => 'datetime',
            'visible_annuaire' => 'boolean',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }
}
