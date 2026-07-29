<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanTraitementEtape extends Model
{
    use HasFactory;

    protected $table = 'plan_traitement_etapes';

    protected $fillable = [
        'plan_traitement_id', 'titre', 'description', 'ordre', 'statut',
        'cout', 'date_prevue', 'date_realisee',
    ];

    protected function casts(): array
    {
        return [
            'date_prevue' => 'date',
            'date_realisee' => 'date',
            'cout' => 'decimal:2',
        ];
    }

    public function planTraitement(): BelongsTo
    {
        return $this->belongsTo(PlanTraitement::class);
    }
}
