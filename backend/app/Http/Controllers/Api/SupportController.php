<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketSupport;
use Illuminate\Http\Request;

/**
 * Création et suivi de tickets de support par le personnel du cabinet.
 * La gestion complète (priorisation, réponse) reste réservée au Super Admin.
 */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        return TicketSupport::where('cabinet_id', $request->user()->cabinet_id)
            ->with('messages')
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sujet' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000'],
            'priorite' => ['nullable', 'in:basse,normale,haute,urgente'],
        ]);

        $ticket = TicketSupport::create([...$data, 'cabinet_id' => $request->user()->cabinet_id]);

        return response()->json($ticket, 201);
    }

    public function show(Request $request, TicketSupport $ticket)
    {
        abort_if($ticket->cabinet_id !== $request->user()->cabinet_id, 404);

        return $ticket->load('messages');
    }

    public function repondre(Request $request, TicketSupport $ticket)
    {
        abort_if($ticket->cabinet_id !== $request->user()->cabinet_id, 404);

        $data = $request->validate(['contenu' => ['required', 'string', 'max:4000']]);

        $message = $ticket->messages()->create([
            'auteur_type' => 'staff',
            'auteur_id' => $request->user()->id,
            'contenu' => $data['contenu'],
        ]);

        return response()->json($message, 201);
    }
}
