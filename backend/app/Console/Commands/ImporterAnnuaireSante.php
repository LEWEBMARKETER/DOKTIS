<?php

namespace App\Console\Commands;

use App\Models\EtablissementSante;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importe (ou met à jour) le référentiel des structures sanitaires depuis le
 * CSV généré par data/annuaire-gabon/build.py. L'import est idempotent : la
 * clé est la colonne `code`. Les champs gérés dans DOKTIS (statut de
 * vérification, visibilité, rattachement à un cabinet) ne sont jamais écrasés.
 */
class ImporterAnnuaireSante extends Command
{
    protected $signature = 'annuaire:importer
        {fichier? : Chemin du CSV (défaut : ../data/annuaire-gabon/etablissements_sante_gabon.csv)}
        {--dry-run : Valide le fichier sans rien écrire}';

    protected $description = 'Importe le référentiel des structures sanitaires (pharmacies, cliniques, cabinets...)';

    private const COLONNES_REQUISES = ['code', 'nom', 'slug', 'categorie', 'ville'];

    private const COLONNES_LISTES = ['specialites', 'telephones', 'sources', 'source_urls'];

    public function handle(): int
    {
        $chemin = $this->argument('fichier')
            ?? base_path('../data/annuaire-gabon/etablissements_sante_gabon.csv');

        if (! is_readable($chemin)) {
            $this->error("Fichier introuvable : {$chemin}");

            return self::FAILURE;
        }

        $flux = fopen($chemin, 'r');
        $entete = fgetcsv($flux, escape: '');
        $entete[0] = preg_replace('/^\xEF\xBB\xBF/', '', $entete[0]); // BOM UTF-8

        if ($manquantes = array_diff(self::COLONNES_REQUISES, $entete)) {
            $this->error('Colonnes manquantes : '.implode(', ', $manquantes));

            return self::FAILURE;
        }

        $fiches = [];
        $erreurs = [];
        $numero = 1;

        while (($ligne = fgetcsv($flux, escape: '')) !== false) {
            $numero++;
            if ($ligne === [null]) {
                continue;
            }
            if (count($ligne) !== count($entete)) {
                $erreurs[] = "Ligne {$numero} : nombre de colonnes incorrect";

                continue;
            }

            $brut = array_combine($entete, $ligne);

            if (! array_key_exists($brut['categorie'], EtablissementSante::CATEGORIES)) {
                $erreurs[] = "Ligne {$numero} : catégorie inconnue « {$brut['categorie']} »";

                continue;
            }

            $fiches[] = $this->convertir($brut);
        }
        fclose($flux);

        foreach ($erreurs as $erreur) {
            $this->warn($erreur);
        }

        if ($this->option('dry-run')) {
            $this->info(count($fiches).' fiches valides, '.count($erreurs).' erreur(s). Aucune écriture (dry-run).');

            return $erreurs ? self::FAILURE : self::SUCCESS;
        }

        $crees = 0;
        $majs = 0;

        DB::transaction(function () use ($fiches, &$crees, &$majs) {
            foreach ($fiches as $fiche) {
                $etablissement = EtablissementSante::firstOrNew(['code' => $fiche['code']]);
                $etablissement->exists ? $majs++ : $crees++;
                $etablissement->fill($fiche)->save();
            }
        });

        $this->info("{$crees} fiche(s) créée(s), {$majs} mise(s) à jour, ".count($erreurs).' ignorée(s).');

        return self::SUCCESS;
    }

    private function convertir(array $brut): array
    {
        $fiche = [];
        $fillable = (new EtablissementSante)->getFillable();
        $proteges = ['statut_verification', 'verifie_le', 'cabinet_id', 'visible_annuaire'];

        foreach ($brut as $colonne => $valeur) {
            if (! in_array($colonne, $fillable, true) || in_array($colonne, $proteges, true)) {
                continue;
            }

            $valeur = trim((string) $valeur);

            $fiche[$colonne] = match (true) {
                in_array($colonne, self::COLONNES_LISTES, true) => $valeur === ''
                    ? null
                    : array_values(array_filter(array_map('trim', explode(';', $valeur)))),
                $colonne === 'ouvert_24h' => in_array(mb_strtolower($valeur), ['oui', '1', 'true'], true),
                default => $valeur === '' ? null : $valeur,
            };
        }

        $fiche['pays'] ??= 'Gabon';

        return $fiche;
    }
}
