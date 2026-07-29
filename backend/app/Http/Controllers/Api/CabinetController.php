<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use Illuminate\Http\Request;

/**
 * Réservé au super administrateur DOKTA : administration multi-cabinets.
 */
class CabinetController extends Controller
{
    public function index()
    {
        return Cabinet::withCount('users', 'patients')->orderBy('nom')->get();
    }

    public function show(Cabinet $cabinet)
    {
        return $cabinet->loadCount('users', 'patients');
    }

    public function update(Request $request, Cabinet $cabinet)
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'plan' => ['sometimes', 'string', 'max:50'],
            'actif' => ['sometimes', 'boolean'],
            'essai_expire_at' => ['nullable', 'date'],
        ]);

        $cabinet->update($data);

        return response()->json($cabinet);
    }
}
