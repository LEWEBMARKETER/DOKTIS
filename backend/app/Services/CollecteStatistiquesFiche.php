<?php

namespace App\Services;

use App\Models\Cabinet;
use App\Models\VisiteFiche;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Collecte des statistiques de visibilité d'une fiche, dans le respect de
 * la vie privée (mission Partie E) :
 * - jamais d'adresse IP complète conservée (seulement un hash non réversible,
 *   lui-même purgé périodiquement par PurgerEmpreintesVisites) ;
 * - les robots connus (moteurs de recherche, outils de supervision, clients
 *   scriptés) ne sont jamais comptés comme des visites ;
 * - un simple rechargement de page le même jour, par le même visiteur,
 *   n'incrémente qu'une fois le compteur de visiteurs uniques.
 */
class CollecteStatistiquesFiche
{
    private const MOTIFS_ROBOTS = [
        'bot', 'spider', 'crawl', 'slurp', 'curl', 'wget', 'python-requests',
        'facebookexternalhit', 'preview', 'monitor', 'uptime', 'headless',
    ];

    public function enregistrerVisite(Cabinet $cabinet, Request $request): void
    {
        if ($this->estRobot($request)) {
            return;
        }

        $date = now()->toDateString();

        $ligne = VisiteFiche::firstOrCreate(
            ['cabinet_id' => $cabinet->id, 'date' => $date],
        );
        $ligne->increment('visites');

        $inserted = DB::table('visites_fiche_empreintes')->insertOrIgnore([
            'cabinet_id' => $cabinet->id,
            'date' => $date,
            'empreinte' => $this->empreinte($request),
            'created_at' => now(),
        ]);

        if ($inserted > 0) {
            $ligne->increment('visiteurs_uniques');
        }
    }

    public function enregistrerClic(Cabinet $cabinet, string $type): void
    {
        if (! in_array($type, ['telephone', 'whatsapp', 'itineraire'], true)) {
            return;
        }

        $ligne = VisiteFiche::firstOrCreate(
            ['cabinet_id' => $cabinet->id, 'date' => now()->toDateString()],
        );
        $ligne->increment("clics_{$type}");
    }

    private function estRobot(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());

        if ($userAgent === '') {
            return true;
        }

        foreach (self::MOTIFS_ROBOTS as $motif) {
            if (str_contains($userAgent, $motif)) {
                return true;
            }
        }

        return false;
    }

    private function empreinte(Request $request): string
    {
        return hash('sha256', $request->ip().'|'.$request->userAgent());
    }
}
