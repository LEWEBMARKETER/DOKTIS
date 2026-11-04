<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\DemandeRevendication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevendicationController extends Controller
{
    public function index(Request $request)
    {
        return DemandeRevendication::with('cabinet:id,nom,slug,ville,quartier,telephone,whatsapp,statut_fiche')
            ->when($request->query('statut'), fn ($q, $statut) => $q->where('statut', $statut))
            ->latest()
            ->paginate(50);
    }

    /**
     * Crée le compte administrateur du cabinet avec le mot de passe choisi
     * par le demandeur, et marque la fiche comme vérifiée.
     */
    public function approuver(Request $request, DemandeRevendication $demande)
    {
        abort_if($demande->statut !== 'en_attente', 422, 'Cette demande a déjà été traitée.');
        abort_if(User::where('email', $demande->email)->exists(), 422, 'Un compte existe déjà avec cet e-mail.');

        $user = DB::transaction(function () use ($request, $demande) {
            $user = new User([
                'cabinet_id' => $demande->cabinet_id,
                'name' => $demande->nom,
                'email' => $demande->email,
                'telephone' => $demande->telephone,
                'role' => RoleUtilisateur::Administrateur,
            ]);
            // Hash déjà calculé à la création de la demande : le cast « hashed »
            // le conserve tel quel.
            $user->password = $demande->getAttributes()['password'];
            $user->save();

            $demande->cabinet->update(['statut_fiche' => Cabinet::STATUT_VERIFIEE]);

            $demande->update([
                'statut' => 'approuvee',
                'traitee_par' => $request->user()->id,
                'traitee_at' => now(),
            ]);

            DemandeRevendication::where('cabinet_id', $demande->cabinet_id)
                ->where('statut', 'en_attente')
                ->update([
                    'statut' => 'rejetee',
                    'motif_rejet' => 'Fiche attribuée à une autre demande.',
                    'traitee_par' => $request->user()->id,
                    'traitee_at' => now(),
                ]);

            return $user;
        });

        return response()->json(['demande' => $demande->fresh('cabinet'), 'user' => $user]);
    }

    public function rejeter(Request $request, DemandeRevendication $demande)
    {
        abort_if($demande->statut !== 'en_attente', 422, 'Cette demande a déjà été traitée.');

        $data = $request->validate(['motif_rejet' => ['nullable', 'string', 'max:1000']]);

        $demande->update([
            'statut' => 'rejetee',
            'motif_rejet' => $data['motif_rejet'] ?? null,
            'traitee_par' => $request->user()->id,
            'traitee_at' => now(),
        ]);

        return response()->json($demande);
    }
}
