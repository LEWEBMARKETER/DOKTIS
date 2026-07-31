<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use Illuminate\Http\Request;

class AvisController extends Controller
{
    public function index(Request $request)
    {
        return Avis::where('cabinet_id', $request->user()->cabinet_id)
            ->with('auteur')
            ->latest()
            ->paginate($request->integer('par_page', 20));
    }

    public function repondre(Request $request, Avis $avi)
    {
        abort_if($avi->cabinet_id !== $request->user()->cabinet_id, 404);

        $data = $request->validate([
            'reponse_cabinet' => ['required', 'string', 'max:2000'],
        ]);

        $avi->update([...$data, 'reponse_at' => now()]);

        return response()->json($avi);
    }
}
