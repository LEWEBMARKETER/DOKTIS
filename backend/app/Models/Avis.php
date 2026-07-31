<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avis extends Model
{
    protected $table = 'avis';

    protected $fillable = ['cabinet_id', 'patient_account_id', 'note', 'commentaire', 'reponse_cabinet', 'reponse_at', 'statut'];

    protected function casts(): array
    {
        return [
            'note' => 'integer',
            'reponse_at' => 'datetime',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(PatientAccount::class, 'patient_account_id');
    }
}
