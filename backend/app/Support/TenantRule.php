<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rule;

final class TenantRule
{
    public static function exists(Authenticatable $user, string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('cabinet_id', $user->cabinet_id);
    }
}
