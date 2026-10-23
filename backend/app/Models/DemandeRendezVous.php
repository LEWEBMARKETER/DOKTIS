<?php

namespace App\Models;

use App\Enums\StatutDemandeRendezVous;
use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de contact/rendez-vous déposée depuis l'annuaire public. Voir la
 * migration create_demandes_rendez_vous_table pour le raisonnement qui la
 * distingue du modèle RendezVous (agenda médical confirmé).
 */
class DemandeRendezVous extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $table = 'demandes_rendez_vous';

    protected $fillable = [
        'cabinet_id', 'praticien_id', 'nom', 'prenom', 'telephone', 'email',
        'date_souhaitee', 'creneau_souhaite', 'motif', 'statut',
        'date_proposee', 'creneau_propose', 'notes_partenaire', 'traite_at',
    ];

    protected function casts(): array
    {
        return [
            'date_souhaitee' => 'date',
            'date_proposee' => 'date',
            'traite_at' => 'datetime',
            'statut' => StatutDemandeRendezVous::class,
        ];
    }

    public function praticien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'praticien_id');
    }
}
