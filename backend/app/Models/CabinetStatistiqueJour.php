<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Totaux journaliers anonymes d'une fiche annuaire.
 */
class CabinetStatistiqueJour extends Model
{
    public const EVENEMENTS = [
        'vue' => 'vues',
        'clic_whatsapp' => 'clics_whatsapp',
        'clic_telephone' => 'clics_telephone',
    ];

    protected $table = 'cabinet_statistiques_jour';

    public $timestamps = false;

    /** Origine d'une vue : QR code imprimé ou lien partagé par le praticien. */
    public const SOURCES = [
        'qr' => 'vues_qr',
        'lien' => 'vues_lien',
    ];

    protected $fillable = ['cabinet_id', 'jour', 'vues', 'clics_whatsapp', 'clics_telephone', 'vues_qr', 'vues_lien'];

    protected function casts(): array
    {
        return [
            'jour' => 'date:Y-m-d',
            'vues' => 'integer',
            'clics_whatsapp' => 'integer',
            'clics_telephone' => 'integer',
            'vues_qr' => 'integer',
            'vues_lien' => 'integer',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }

    public static function enregistrer(int $cabinetId, string $evenement, ?string $source = null): void
    {
        $colonnes = [self::EVENEMENTS[$evenement]];
        if ($evenement === 'vue' && $source && isset(self::SOURCES[$source])) {
            $colonnes[] = self::SOURCES[$source];
        }
        $jour = now(config('dokta.fuseau_horaire_local'))->toDateString();

        DB::table('cabinet_statistiques_jour')->insertOrIgnore([
            'cabinet_id' => $cabinetId,
            'jour' => $jour,
        ]);

        DB::table('cabinet_statistiques_jour')
            ->where('cabinet_id', $cabinetId)
            ->where('jour', $jour)
            ->incrementEach(array_fill_keys($colonnes, 1));
    }
}
