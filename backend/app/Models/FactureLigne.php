<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactureLigne extends Model
{
    use HasFactory;

    protected $table = 'facture_lignes';

    protected $fillable = ['facture_id', 'designation', 'quantite', 'prix_unitaire', 'montant'];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:2',
            'prix_unitaire' => 'decimal:2',
            'montant' => 'decimal:2',
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
