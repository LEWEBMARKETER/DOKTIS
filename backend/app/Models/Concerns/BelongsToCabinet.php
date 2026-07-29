<?php

namespace App\Models\Concerns;

use App\Models\Cabinet;
use App\Models\Scopes\CabinetScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCabinet
{
    public static function bootBelongsToCabinet(): void
    {
        static::addGlobalScope(new CabinetScope);

        static::creating(function ($model) {
            if (! $model->cabinet_id && auth()->check()) {
                $model->cabinet_id = auth()->user()->cabinet_id;
            }
        });
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }
}
