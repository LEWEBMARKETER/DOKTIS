<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use Illuminate\Http\Request;

class DepenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Depense::query();

        if ($debut = $request->query('debut')) {
            $query->where('date_depense', '>=', $debut);
        }

        if ($fin = $request->query('fin')) {
            $query->where('date_depense', '<=', $fin);
        }

        return $query->orderByDesc('date_depense')->paginate($request->integer('par_page', 30));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'categorie' => ['required', 'string', 'max:100'],
            'designation' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_depense' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $depense = Depense::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($depense, 201);
    }

    public function update(Request $request, Depense $depense)
    {
        $data = $request->validate([
            'categorie' => ['sometimes', 'string', 'max:100'],
            'designation' => ['sometimes', 'string', 'max:255'],
            'montant' => ['sometimes', 'numeric', 'min:0.01'],
            'date_depense' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $depense->update($data);

        return response()->json($depense);
    }

    public function destroy(Depense $depense)
    {
        $depense->delete();

        return response()->json(status: 204);
    }
}
