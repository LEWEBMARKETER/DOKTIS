<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DemandeRendezVous;
use Illuminate\Http\Request;

/**
 * Vue d'ensemble, en lecture seule, des demandes de rendez-vous reçues sur
 * l'ensemble des fiches. Le traitement (confirmer/refuser/proposer un
 * créneau) reste la responsabilité exclusive du partenaire concerné
 * (Api\Partenaire\RendezVousController) — l'administration DOKTIS ne se
 * substitue jamais à lui.
 */
class DemandeRendezVousController extends Controller
{
    public function index(Request $request)
    {
        $query = DemandeRendezVous::with('cabinet:id,nom,categorie_fiche');

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->latest('date_souhaitee')->paginate($request->integer('par_page', 30));
    }
}
