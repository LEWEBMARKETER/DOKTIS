<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Politique de conservation des empreintes de dédoublonnage des visiteurs
 * (mission Partie E) : ces empreintes ne servent qu'à compter les
 * visiteurs uniques du jour et n'ont aucune utilité statistique au-delà de
 * quelques semaines. Les compteurs agrégés dans "visites_fiche" ne sont
 * jamais purgés par cette commande.
 */
class PurgerEmpreintesVisites extends Command
{
    protected $signature = 'visites:purger-empreintes {--jours=60 : ancienneté en jours au-delà de laquelle une empreinte est supprimée}';

    protected $description = 'Supprime les empreintes de visiteurs (dédoublonnage quotidien) plus anciennes que N jours';

    public function handle(): int
    {
        $seuil = now()->subDays((int) $this->option('jours'));

        $supprimees = DB::table('visites_fiche_empreintes')->where('date', '<', $seuil->toDateString())->delete();

        $this->info("{$supprimees} empreinte(s) supprimée(s) (antérieures à {$seuil->toDateString()}).");

        return self::SUCCESS;
    }
}
