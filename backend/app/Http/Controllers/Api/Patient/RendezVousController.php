<?php

namespace App\Http\Controllers\Api\Patient;

use App\Enums\RoleUtilisateur;
use App\Events\RendezVousMisAJour;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RendezVousController extends Controller
{
    /**
     * Tous les rendez-vous du patient, tous cabinets confondus.
     */
    public function index(Request $request)
    {
        $dossierIds = Patient::withoutGlobalScopes()->where('patient_account_id', $request->user()->id)->pluck('id');

        return RendezVous::whereIn('patient_id', $dossierIds)
            ->with(['praticien:id,name,specialite', 'patient' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'cabinet_id')])
            ->withoutGlobalScopes()
            ->orderByDesc('debut')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cabinet_id' => ['required', 'exists:cabinets,id'],
            'praticien_id' => ['required', 'exists:users,id'],
            'motif' => ['nullable', 'string', 'max:255'],
            'debut' => ['required', 'date', 'after:now'],
            'fin' => ['required', 'date', 'after:debut'],
        ]);

        $cabinet = Cabinet::findOrFail($data['cabinet_id']);
        $praticien = User::where('id', $data['praticien_id'])
            ->where('cabinet_id', $cabinet->id)
            ->whereIn('role', [RoleUtilisateur::Administrateur->value, RoleUtilisateur::Medecin->value])
            ->first();

        if (! $praticien) {
            return response()->json(['message' => "Ce praticien n'appartient pas à ce cabinet."], 422);
        }

        $conflit = RendezVous::withoutGlobalScopes()
            ->where('praticien_id', $praticien->id)
            ->where('statut', '!=', 'annule')
            ->where('debut', '<', $data['fin'])
            ->where('fin', '>', $data['debut'])
            ->exists();

        if ($conflit) {
            return response()->json(['message' => 'Ce créneau ne semble plus disponible, merci de recharger les disponibilités.'], 422);
        }

        $account = $request->user();
        $dossier = Patient::withoutGlobalScopes()->firstOrCreate(
            ['cabinet_id' => $cabinet->id, 'patient_account_id' => $account->id],
            [
                'numero_dossier' => $this->genererNumeroDossier($cabinet->id),
                'nom' => $account->nom,
                'prenom' => $account->prenom,
                'telephone' => $account->telephone,
                'email' => $account->email,
                'date_naissance' => $account->date_naissance,
                'sexe' => $account->sexe,
                'contact_urgence_nom' => $account->contact_urgence_nom,
                'contact_urgence_telephone' => $account->contact_urgence_telephone,
            ]
        );

        $rendezVous = RendezVous::withoutGlobalScopes()->create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $dossier->id,
            'praticien_id' => $praticien->id,
            'motif' => $data['motif'] ?? null,
            'type' => 'consultation',
            'source' => 'patient_app',
            'debut' => $data['debut'],
            'fin' => $data['fin'],
            'statut' => 'planifie',
        ]);

        broadcast(new RendezVousMisAJour($rendezVous));

        return response()->json($rendezVous->load('praticien:id,name,specialite'), 201);
    }

    /**
     * Pas de liaison implicite de modèle ici : RendezVous applique le scope
     * cabinet, qui bloque tout accès pour un compte patient avant même que le
     * contrôleur ne s'exécute. On résout donc l'identifiant manuellement.
     */
    public function annuler(Request $request, int $rendezVous)
    {
        $rendezVous = RendezVous::withoutGlobalScopes()
            ->whereHas('patient', function ($q) use ($request) {
                $q->withoutGlobalScopes()->where('patient_account_id', $request->user()->id);
            })
            ->findOrFail($rendezVous);

        abort_if(in_array($rendezVous->statut, ['annule', 'termine']), 422, 'Ce rendez-vous ne peut plus être annulé.');

        $rendezVous->update(['statut' => 'annule']);

        broadcast(new RendezVousMisAJour($rendezVous));

        return response()->json($rendezVous);
    }

    private function genererNumeroDossier(int $cabinetId): string
    {
        $annee = now()->format('Y');
        $sequence = Patient::withoutGlobalScopes()->withTrashed()->where('cabinet_id', $cabinetId)->count() + 1;

        return sprintf('%s-%04d', $annee, $sequence).'-'.Str::upper(Str::random(3));
    }
}
