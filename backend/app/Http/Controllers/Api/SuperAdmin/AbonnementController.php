<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\JournalAction;
use Illuminate\Http\Request;

class AbonnementController extends Controller
{
    public function index(Request $request)
    {
        $query = Abonnement::with('cabinet:id,nom');

        if ($cabinetId = $request->query('cabinet_id')) {
            $query->where('cabinet_id', $cabinetId);
        }

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->latest()->paginate($request->integer('par_page', 30));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cabinet_id' => ['required', 'exists:cabinets,id'],
            'plan' => ['required', 'string', 'max:100'],
            'prix' => ['required', 'numeric', 'min:0'],
            'cycle_facturation' => ['required', 'in:mensuel,annuel'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
            'mode_paiement' => ['nullable', 'string', 'max:100'],
            'taux_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $abonnement = Abonnement::create($data);

        JournalAction::enregistrer('abonnement.cree', Abonnement::class, $abonnement->id, $data);

        return response()->json($abonnement, 201);
    }

    public function update(Request $request, Abonnement $abonnement)
    {
        $data = $request->validate([
            'plan' => ['sometimes', 'string', 'max:100'],
            'prix' => ['sometimes', 'numeric', 'min:0'],
            'statut' => ['sometimes', 'in:actif,suspendu,annule'],
            'date_fin' => ['nullable', 'date'],
            'taux_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $abonnement->update($data);

        JournalAction::enregistrer('abonnement.modifie', Abonnement::class, $abonnement->id, $data);

        return response()->json($abonnement);
    }
}
