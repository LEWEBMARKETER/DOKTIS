<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = ['titre', 'description', 'gravite', 'statut', 'date_debut', 'date_resolution'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_resolution' => 'datetime',
        ];
    }
}
