<?php

namespace App\Models\Scopes;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CabinetScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        // DOKTA Patient et DOKTA Office partagent le même mécanisme de jeton :
        // un compte patient n'a pas de cabinet_id et ne doit jamais recevoir de
        // données scopées par cabinet via ce chemin (les contrôleurs patient
        // utilisent withoutGlobalScopes() explicitement quand c'est légitime).
        if (! $user instanceof User) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Comparaison par valeur : $user->role est casté en instance de
        // l'enum RoleUtilisateur (App\Models\User::casts()), jamais égale
        // par ===  à la chaîne ->value. Avec l'ancienne comparaison, ce
        // contournement pour super_admin n'était jamais atteint : une
        // requête super_admin sur un modèle BelongsToCabinet retombait sur
        // `WHERE cabinet_id = NULL` (super_admin n'a pas de cabinet), donc
        // un résultat systématiquement vide plutôt que non filtré — un
        // échec silencieux côté fonctionnalité (jamais une fuite de
        // données, puisque NULL ne correspond jamais), mais qui aurait
        // rendu inutilisables les vues plateforme (admin Directory) qui
        // s'appuient sur ce contournement.
        if ($user->role === RoleUtilisateur::SuperAdmin) {
            return;
        }

        $builder->where($model->getTable().'.cabinet_id', $user->cabinet_id);
    }
}
