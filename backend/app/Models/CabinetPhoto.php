<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CabinetPhoto extends Model
{
    protected $fillable = ['cabinet_id', 'chemin', 'disque', 'ordre'];

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }

    public function url(): ?string
    {
        return Storage::disk($this->disque)->url($this->chemin);
    }
}
