<?php

namespace App\Http\Controllers\Api\Patient;

use App\Events\NouveauMessage;
use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\Conversation;
use Illuminate\Http\Request;

class MessagerieController extends Controller
{
    public function index(Request $request)
    {
        return Conversation::withoutGlobalScopes()
            ->where('patient_account_id', $request->user()->id)
            ->with('cabinet:id,nom,logo_path')
            ->orderByDesc('dernier_message_at')
            ->get();
    }

    public function demarrer(Request $request, Cabinet $cabinet)
    {
        $conversation = Conversation::withoutGlobalScopes()->firstOrCreate([
            'cabinet_id' => $cabinet->id,
            'patient_account_id' => $request->user()->id,
        ]);

        return response()->json($conversation->load('cabinet:id,nom,logo_path'), 201);
    }

    /**
     * Pas de liaison implicite de modèle : Conversation applique le scope
     * cabinet, qui bloque tout accès pour un compte patient avant même que le
     * contrôleur ne s'exécute. On résout donc l'identifiant manuellement.
     */
    public function messages(Request $request, int $conversation)
    {
        $conversation = $this->trouverConversation($request, $conversation);

        $conversation->messages()->where('expediteur_type', 'staff')->whereNull('lu_at')->update(['lu_at' => now()]);

        return $conversation->load('messages');
    }

    public function envoyer(Request $request, int $conversation)
    {
        $conversation = $this->trouverConversation($request, $conversation);

        $data = $request->validate(['contenu' => ['required', 'string', 'max:4000']]);

        $message = $conversation->messages()->create([
            'expediteur_type' => 'patient',
            'expediteur_id' => $request->user()->id,
            'contenu' => $data['contenu'],
        ]);

        $conversation->update(['dernier_message_at' => now()]);

        broadcast(new NouveauMessage($message));

        return response()->json($message, 201);
    }

    private function trouverConversation(Request $request, int $conversationId): Conversation
    {
        return Conversation::withoutGlobalScopes()
            ->where('patient_account_id', $request->user()->id)
            ->findOrFail($conversationId);
    }
}
