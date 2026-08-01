<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $query = Incident::query();

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->latest('date_debut')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'gravite' => ['required', 'in:mineur,majeur,critique'],
        ]);

        return response()->json(Incident::create($data), 201);
    }

    public function update(Request $request, Incident $incident)
    {
        $data = $request->validate([
            'titre' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'gravite' => ['sometimes', 'in:mineur,majeur,critique'],
            'statut' => ['sometimes', 'in:en_cours,resolu'],
        ]);

        if (($data['statut'] ?? null) === 'resolu' && $incident->statut !== 'resolu') {
            $data['date_resolution'] = now();
        }

        $incident->update($data);

        return response()->json($incident);
    }
}
