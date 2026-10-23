<?php

namespace App\Models;

use App\Enums\StatutDemandeModificationFiche;
use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeModificationFiche extends Model
{
    use BelongsToCabinet;

    protected $table = 'demandes_modification_fiche';

    protected $fillable = [
        'cabinet_id', 'partenaire_id', 'champs_proposes', 'statut',
        'commentaire_admin', 'traite_par_id', 'traite_at',
    ];

    protected function casts(): array
    {
        return [
            'champs_proposes' => 'array',
            'traite_at' => 'datetime',
            'statut' => StatutDemandeModificationFiche::class,
        ];
    }

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partenaire_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par_id');
    }
}
