<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Mutuelle extends Model
{
    protected $fillable = ['nom'];

    public function cabinets(): BelongsToMany
    {
        return $this->belongsToMany(Cabinet::class, 'cabinet_mutuelle');
    }
}
