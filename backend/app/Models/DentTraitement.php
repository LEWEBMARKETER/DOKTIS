<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentTraitement extends Model
{
    use BelongsToCabinet;

    protected $table = 'dents_traitements';

    protected $fillable = [
        'cabinet_id', 'patient_id', 'numero_dent', 'type_traitement', 'statut',
        'consultation_id', 'plan_traitement_id', 'praticien_id', 'date_traitement', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'numero_dent' => 'integer',
            'date_traitement' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function praticien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'praticien_id');
    }

    public function planTraitement(): BelongsTo
    {
        return $this->belongsTo(PlanTraitement::class);
    }
}
