<?php

use App\Models\Conversation;
use App\Models\PatientAccount;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canaux de diffusion temps réel (Laravel Reverb)
|--------------------------------------------------------------------------
|
| DOKTA Office et DOKTA Patient partagent le même mécanisme de jeton Sanctum
| (polymorphe : $user peut être un App\Models\User du personnel ou un
| App\Models\PatientAccount). Chaque canal vérifie explicitement le type.
*/

// Mises à jour propres à un cabinet (agenda, factures...) : personnel de ce cabinet uniquement.
Broadcast::channel('cabinet.{cabinetId}', function ($user, int $cabinetId) {
    return $user instanceof User && (int) $user->cabinet_id === $cabinetId;
});

// Notifications personnelles d'un patient (rappels, résultats disponibles...).
Broadcast::channel('patient.{patientAccountId}', function ($user, int $patientAccountId) {
    return $user instanceof PatientAccount && (int) $user->id === $patientAccountId;
});

// Messagerie sécurisée : le membre du cabinet concerné ou le patient de la conversation.
Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = Conversation::withoutGlobalScopes()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    if ($user instanceof User) {
        return (int) $user->cabinet_id === $conversation->cabinet_id;
    }

    if ($user instanceof PatientAccount) {
        return (int) $user->id === $conversation->patient_account_id;
    }

    return false;
});
