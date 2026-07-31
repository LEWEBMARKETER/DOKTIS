<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Cabinet;
use Illuminate\Http\Request;

class AvisController extends Controller
{
    /**
     * Un patient ne peut laisser qu'un avis par cabinet ; un nouvel envoi
     * met à jour son avis existant (mêmes règles que la plupart des annuaires).
     */
    public function store(Request $request, Cabinet $cabinet)
    {
        $data = $request->validate([
            'note' => ['required', 'integer', 'min:1', 'max:5'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        $avis = Avis::updateOrCreate(
            ['cabinet_id' => $cabinet->id, 'patient_account_id' => $request->user()->id],
            $data
        );

        $cabinet->rafraichirNoteMoyenne();

        return response()->json($avis, 201);
    }

    public function mesAvis(Request $request)
    {
        return Avis::where('patient_account_id', $request->user()->id)->with('cabinet')->latest()->get();
    }

    public function destroy(Request $request, Cabinet $cabinet)
    {
        Avis::where('cabinet_id', $cabinet->id)->where('patient_account_id', $request->user()->id)->delete();
        $cabinet->rafraichirNoteMoyenne();

        return response()->json(status: 204);
    }
}
