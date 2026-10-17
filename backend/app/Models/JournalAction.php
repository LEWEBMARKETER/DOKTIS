<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class JournalAction extends Model
{
    public $timestamps = false;

    protected $table = 'journal_actions';

    protected $fillable = ['acteur_type', 'acteur_id', 'action', 'sujet_type', 'sujet_id', 'donnees', 'ip_address', 'created_at'];

    protected function casts(): array
    {
        return [
            'donnees' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $acteur  À
     *     fournir explicitement pendant le login/register (avant que Sanctum
     *     n'ait basculé le garde par défaut) ; sinon résolu depuis la requête
     *     authentifiée courante.
     */
    public static function enregistrer(string $action, ?string $sujetType = null, ?int $sujetId = null, array $donnees = [], ?Authenticatable $acteur = null): self
    {
        $user = $acteur ?? auth()->user();

        return static::create([
            'acteur_type' => $user ? get_class($user) : null,
            'acteur_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'sujet_type' => $sujetType,
            'sujet_id' => $sujetId,
            'donnees' => $donnees,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
