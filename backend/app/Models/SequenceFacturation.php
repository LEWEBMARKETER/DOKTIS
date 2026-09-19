<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SequenceFacturation extends Model
{
    public $timestamps = false;

    protected $table = 'sequences_facturation';

    protected $fillable = ['cabinet_id', 'annee', 'dernier_numero'];
}
