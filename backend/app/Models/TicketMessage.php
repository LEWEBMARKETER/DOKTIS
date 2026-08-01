<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'auteur_type', 'auteur_id', 'contenu'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketSupport::class, 'ticket_id');
    }
}
