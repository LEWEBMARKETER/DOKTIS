<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CabinetHoraire extends Model
{
    protected $fillable = ['cabinet_id', 'jour_semaine', 'heure_ouverture', 'heure_fermeture', 'ferme'];

    protected function casts(): array
    {
        return ['ferme' => 'boolean'];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }
}
