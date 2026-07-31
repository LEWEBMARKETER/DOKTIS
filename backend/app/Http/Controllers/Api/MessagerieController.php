<?php

namespace App\Http\Controllers\Api;

use App\Events\NouveauMessage;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class MessagerieController extends Controller
{
    public function index(Request $request)
    {
        return Conversation::with('patientAccount:id,nom,prenom,photo_path')
            ->orderByDesc('dernier_message_at')
            ->get();
    }

    public function messages(Conversation $conversation)
    {
        $conversation->messages()->where('expediteur_type', 'patient')->whereNull('lu_at')->update(['lu_at' => now()]);

        return $conversation->load('messages');
    }

    public function envoyer(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['contenu' => ['required', 'string', 'max:4000']]);

        $message = $conversation->messages()->create([
            'expediteur_type' => 'staff',
            'expediteur_id' => $request->user()->id,
            'contenu' => $data['contenu'],
        ]);

        $conversation->update(['dernier_message_at' => now()]);

        broadcast(new NouveauMessage($message));

        return response()->json($message, 201);
    }
}
