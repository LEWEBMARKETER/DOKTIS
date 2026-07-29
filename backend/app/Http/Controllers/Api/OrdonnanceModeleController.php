<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrdonnanceModele;
use Illuminate\Http\Request;

class OrdonnanceModeleController extends Controller
{
    public function index()
    {
        return OrdonnanceModele::orderBy('titre')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'contenu' => ['required', 'string'],
        ]);

        $modele = OrdonnanceModele::create([
            ...$data,
            'praticien_id' => $request->user()->id,
        ]);

        return response()->json($modele, 201);
    }

    public function update(Request $request, OrdonnanceModele $ordonnanceModele)
    {
        $data = $request->validate([
            'titre' => ['sometimes', 'string', 'max:255'],
            'contenu' => ['sometimes', 'string'],
        ]);

        $ordonnanceModele->update($data);

        return response()->json($ordonnanceModele);
    }

    public function destroy(OrdonnanceModele $ordonnanceModele)
    {
        $ordonnanceModele->delete();

        return response()->json(status: 204);
    }
}
