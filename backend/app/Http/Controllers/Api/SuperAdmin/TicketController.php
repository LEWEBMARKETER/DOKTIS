<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TicketSupport;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = TicketSupport::with(['cabinet:id,nom', 'patientAccount:id,nom,prenom']);

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->latest()->paginate($request->integer('par_page', 30));
    }

    public function show(TicketSupport $ticket)
    {
        return $ticket->load(['cabinet:id,nom', 'patientAccount:id,nom,prenom', 'messages']);
    }

    public function update(Request $request, TicketSupport $ticket)
    {
        $data = $request->validate([
            'statut' => ['sometimes', 'in:ouvert,en_cours,resolu,ferme'],
            'priorite' => ['sometimes', 'in:basse,normale,haute,urgente'],
        ]);

        $ticket->update($data);

        return response()->json($ticket);
    }

    public function repondre(Request $request, TicketSupport $ticket)
    {
        $data = $request->validate(['contenu' => ['required', 'string', 'max:4000']]);

        $message = $ticket->messages()->create([
            'auteur_type' => 'super_admin',
            'auteur_id' => $request->user()->id,
            'contenu' => $data['contenu'],
        ]);

        if ($ticket->statut === 'ouvert') {
            $ticket->update(['statut' => 'en_cours']);
        }

        return response()->json($message, 201);
    }
}
