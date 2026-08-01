<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialite extends Model
{
    protected $fillable = ['nom', 'slug'];

    public function cabinets(): BelongsToMany
    {
        return $this->belongsToMany(Cabinet::class, 'cabinet_specialite');
    }
}
