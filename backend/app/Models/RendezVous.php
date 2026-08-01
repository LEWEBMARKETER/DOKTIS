<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RendezVous extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $table = 'rendez_vous';

    protected $fillable = [
        'cabinet_id', 'patient_id', 'praticien_id', 'motif', 'type', 'source',
        'reprogramme_depuis_id', 'debut', 'fin', 'statut', 'notes', 'rappel_envoye_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
            'rappel_envoye_at' => 'datetime',
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

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reprogrammeDepuis(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'reprogramme_depuis_id');
    }
}
