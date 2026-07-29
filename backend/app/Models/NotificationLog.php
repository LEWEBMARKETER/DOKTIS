<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $table = 'notifications_log';

    protected $fillable = [
        'cabinet_id', 'patient_id', 'rendez_vous_id', 'canal', 'type',
        'destinataire', 'contenu', 'statut', 'envoye_at',
    ];

    protected function casts(): array
    {
        return [
            'envoye_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }
}
