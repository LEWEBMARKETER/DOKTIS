<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depense extends Model
{
    use BelongsToCabinet;

    protected $fillable = [
        'cabinet_id', 'categorie', 'designation', 'montant', 'date_depense',
        'justificatif_path', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_depense' => 'date',
            'montant' => 'decimal:2',
        ];
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
