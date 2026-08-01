<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CabinetService extends Model
{
    protected $table = 'cabinet_services';

    protected $fillable = ['cabinet_id', 'nom', 'description', 'prix_indicatif', 'duree_minutes', 'actif'];

    protected function casts(): array
    {
        return [
            'prix_indicatif' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }
}
