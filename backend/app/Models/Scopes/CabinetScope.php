<?php

namespace App\Models\Scopes;

use App\Enums\RoleUtilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CabinetScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || $user->role === RoleUtilisateur::SuperAdmin->value) {
            return;
        }

        $builder->where($model->getTable().'.cabinet_id', $user->cabinet_id);
    }
}
