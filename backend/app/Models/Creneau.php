<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Créneau proposé par un cabinet. Les heures sont stockées en UTC et
 * présentées en heure locale (config dokta.fuseau_horaire_local).
 */
class Creneau extends Model
{
    use BelongsToCabinet;

    protected $table = 'creneaux';

    protected $fillable = ['cabinet_id', 'praticien_id', 'debut', 'fin', 'statut', 'demandes'];

    protected $appends = ['date_locale', 'heure_debut', 'heure_fin'];

    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
            'demandes' => 'integer',
        ];
    }

    public function praticien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'praticien_id');
    }

    public function scopeAVenir(Builder $query): Builder
    {
        return $query->where('debut', '>', now());
    }

    public static function local(Carbon $moment): Carbon
    {
        return $moment->copy()->setTimezone(config('dokta.fuseau_horaire_local'));
    }

    public function getDateLocaleAttribute(): ?string
    {
        return $this->debut ? static::local($this->debut)->toDateString() : null;
    }

    public function getHeureDebutAttribute(): ?string
    {
        return $this->debut ? static::local($this->debut)->format('H:i') : null;
    }

    public function getHeureFinAttribute(): ?string
    {
        return $this->fin ? static::local($this->fin)->format('H:i') : null;
    }
}
