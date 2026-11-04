<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeRevendication extends Model
{
    protected $table = 'demandes_revendication';

    protected $fillable = [
        'cabinet_id', 'nom', 'email', 'telephone', 'fonction', 'password', 'message',
        'statut', 'motif_rejet', 'traitee_par', 'traitee_at',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'traitee_at' => 'datetime',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }

    public function traiteePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traitee_par');
    }
}
