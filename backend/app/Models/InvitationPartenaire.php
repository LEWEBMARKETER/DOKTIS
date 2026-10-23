<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationPartenaire extends Model
{
    use BelongsToCabinet;

    protected $table = 'invitations_partenaire';

    protected $fillable = ['cabinet_id', 'email', 'token_hash', 'invite_par_id', 'expire_at', 'utilise_at'];

    protected function casts(): array
    {
        return [
            'expire_at' => 'datetime',
            'utilise_at' => 'datetime',
        ];
    }

    public function invitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invite_par_id');
    }

    /**
     * Génère un jeton en clair (jamais stocké) accompagné de son hash
     * (seul persisté), sur le même principe qu'un jeton de réinitialisation
     * de mot de passe Laravel.
     */
    public static function genererJeton(): array
    {
        $jeton = Str::random(48);

        return [$jeton, Hash::make($jeton)];
    }

    public function estValide(string $jeton): bool
    {
        return $this->utilise_at === null
            && $this->expire_at->isFuture()
            && Hash::check($jeton, $this->token_hash);
    }
}
