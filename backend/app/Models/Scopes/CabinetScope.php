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

        if ($user->role === RoleUtilisateur::SuperAdmin->value) {
            return;
        }

        $builder->where($model->getTable().'.cabinet_id', $user->cabinet_id);
    }
}
