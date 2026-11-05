<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\CabinetService;
use App\Models\Creneau;
use App\Models\Mutuelle;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de démonstration de l'annuaire, pour présenter la plateforme en ligne.
 * Activé par DOKTA_DONNEES_DEMO=true (voir docker/entrypoint.sh).
 *
 * - Cabinets fictifs (plan « demo »), présentés comme tels sur leur fiche.
 * - Aucun numéro inventé : le bouton WhatsApp utilise DOKTA_DEMO_WHATSAPP
 *   (votre propre numéro) ; sans lui, les fiches n'ont pas de contact.
 * - Comptes praticiens utilisables seulement si DOKTA_DEMO_PASSWORD est défini.
 * - Idempotent : les fiches ne sont créées qu'une fois ; les créneaux de la
 *   semaine à venir sont recréés à chaque démarrage s'il n'en reste plus.
 */
class DemoAnnuaireSeeder extends Seeder
{
    public const PLAN = 'demo';

    private const MENTION = "\n\nFiche de démonstration DOKTIS : ce cabinet n'existe pas.";

    public function run(): void
    {
        $this->call(ReferentielSeeder::class);

        $whatsapp = config('dokta.demo.whatsapp');
        $fuseau = config('dokta.fuseau_horaire_local');

        foreach ($this->cabinets() as $donnees) {
            $cabinet = Cabinet::where('slug', $donnees['slug'])->first()
                ?? $this->creerCabinet($donnees, $whatsapp);

            if ($whatsapp && $cabinet->plan === self::PLAN) {
                $cabinet->update(['whatsapp' => $whatsapp]);
            }

            if (! empty($donnees['praticiens'])) {
                $this->renouvelerCreneaux($cabinet, $fuseau);
            }
        }
    }

    private function creerCabinet(array $d, ?string $whatsapp): Cabinet
    {
        $cabinet = Cabinet::create([
            'nom' => $d['nom'],
            'slug' => $d['slug'],
            'type' => $d['type'],
            'ville' => $d['ville'],
            'quartier' => $d['quartier'],
            'adresse' => $d['adresse'] ?? null,
            'latitude' => $d['latitude'] ?? null,
            'longitude' => $d['longitude'] ?? null,
            'description' => $d['description'].self::MENTION,
            'langues_parlees' => $d['langues'],
            'accepte_urgences' => $d['urgences'] ?? false,
            'accessible_pmr' => $d['pmr'] ?? false,
            'whatsapp' => $whatsapp,
            'plan' => self::PLAN,
            'statut_fiche' => $d['statut'],
            'visible_annuaire' => true,
        ]);

        $cabinet->specialites()->sync(
            Specialite::whereIn('slug', array_map(fn ($nom) => Str::slug($nom), $d['specialites']))->pluck('id')
        );
        $cabinet->mutuelles()->sync(Mutuelle::whereIn('nom', $d['assureurs'] ?? ['CNAMGS'])->pluck('id'));
        $cabinet->remplacerHoraires($this->semaine(...$d['horaires']));

        foreach ($d['services'] ?? [] as [$nom, $prix, $duree]) {
            CabinetService::create(['cabinet_id' => $cabinet->id, 'nom' => $nom, 'prix_indicatif' => $prix, 'duree_minutes' => $duree]);
        }

        $motDePasse = config('dokta.demo.password');
        foreach ($d['praticiens'] ?? [] as [$nom, $email, $role, $specialite]) {
            User::firstOrCreate(['email' => $email], [
                'cabinet_id' => $cabinet->id,
                'name' => $nom,
                // Sans mot de passe de démo configuré, le compte existe (le
                // praticien apparaît sur la fiche) mais personne ne peut s'y connecter.
                'password' => Hash::make($motDePasse ?: Str::random(48)),
                'role' => $role,
                'specialite' => $specialite,
            ]);
        }

        return $cabinet;
    }

    /**
     * Ouvre les créneaux du matin des 7 prochains jours (hors dimanche) s'il
     * n'en reste plus à venir, avec quelques-uns marqués « pris ».
     */
    private function renouvelerCreneaux(Cabinet $cabinet, string $fuseau): void
    {
        $base = Creneau::withoutGlobalScopes()->where('cabinet_id', $cabinet->id);
        if ((clone $base)->where('statut', 'libre')->aVenir()->exists()) {
            return;
        }

        $praticiens = User::where('cabinet_id', $cabinet->id)->orderBy('id')->pluck('id')->all();
        $jour = now($fuseau)->startOfDay();

        for ($i = 0; $i < 7; $i++, $jour->addDay()) {
            if ($jour->isSunday()) {
                continue;
            }
            foreach (['08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30'] as $index => $heure) {
                $debut = Carbon::createFromFormat('Y-m-d H:i', $jour->toDateString().' '.$heure, $fuseau);
                if ($debut->isPast()) {
                    continue;
                }
                Creneau::withoutGlobalScopes()->create([
                    'cabinet_id' => $cabinet->id,
                    'praticien_id' => $praticiens ? $praticiens[$index % count($praticiens)] : null,
                    'debut' => $debut->copy()->utc(),
                    'fin' => $debut->copy()->addMinutes(30)->utc(),
                    'statut' => ($i + $index) % 4 === 0 ? 'pris' : 'libre',
                ]);
            }
        }
    }

    /** Lundi à vendredi ouverture-fermeture, samedi matin optionnel, dimanche fermé. */
    private function semaine(string $ouverture, string $fermeture, ?string $samediFermeture = null): array
    {
        return array_merge(
            array_map(fn (int $jour) => ['jour_semaine' => $jour, 'heure_ouverture' => $ouverture, 'heure_fermeture' => $fermeture], [1, 2, 3, 4, 5]),
            [['jour_semaine' => 6, 'heure_ouverture' => $samediFermeture ? $ouverture : null, 'heure_fermeture' => $samediFermeture, 'ferme' => $samediFermeture === null]],
            [['jour_semaine' => 0, 'ferme' => true]],
        );
    }

    /** Cabinets fictifs répartis sur Libreville, Owendo, Port-Gentil et Franceville. */
    private function cabinets(): array
    {
        return [
            [
                'slug' => 'demo-cabinet-dentaire-etoile-libreville',
                'nom' => 'Cabinet Dentaire Étoile',
                'type' => 'dentaire', 'ville' => 'Libreville', 'quartier' => 'Glass',
                'adresse' => 'Rue des Flamboyants', 'latitude' => 0.3990, 'longitude' => 9.4450,
                'description' => 'Cabinet dentaire moderne : soins conservateurs, détartrage, orthodontie et prothèses. Radiographie numérique sur place.',
                'langues' => ['Français', 'Fang', 'Anglais'], 'urgences' => true, 'pmr' => true,
                'statut' => Cabinet::STATUT_VERIFIEE,
                'specialites' => ['Cabinet dentaire'],
                'assureurs' => ['CNAMGS', 'NSIA Assurances', 'ASCOMA Gabon'],
                'horaires' => ['08:00', '18:00', '13:00'],
                'services' => [
                    ['Consultation de contrôle', 10000, 20], ['Détartrage', 15000, 30],
                    ['Soin de carie', 25000, 45], ['Pose de couronne', 85000, 60],
                ],
                'praticiens' => [
                    ['Dr Aïcha Nzé', 'praticien.etoile@demo.doktis', RoleUtilisateur::Administrateur, 'Chirurgien-dentiste'],
                    ['Dr Paul Moussavou', 'dentiste.etoile@demo.doktis', RoleUtilisateur::Medecin, 'Dentiste généraliste'],
                ],
            ],
            [
                'slug' => 'demo-centre-medical-les-manguiers-libreville',
                'nom' => 'Centre Médical Les Manguiers',
                'type' => 'medical', 'ville' => 'Libreville', 'quartier' => 'Nzeng-Ayong',
                'description' => 'Médecine générale et suivi des enfants, consultations sans rendez-vous le matin.',
                'langues' => ['Français', 'Fang'], 'urgences' => true,
                'statut' => Cabinet::STATUT_NON_REVENDIQUEE,
                'specialites' => ['Médecin généraliste', 'Pédiatre'],
                'horaires' => ['07:30', '19:00', '13:00'],
                'services' => [['Consultation de médecine générale', 10000, 20], ['Certificat médical', 5000, 15]],
            ],
            [
                'slug' => 'demo-cabinet-pediatrique-de-louis-libreville',
                'nom' => 'Cabinet Pédiatrique de Louis',
                'type' => 'medical', 'ville' => 'Libreville', 'quartier' => 'Louis',
                'description' => 'Consultations pédiatriques, vaccinations et suivi de croissance.',
                'langues' => ['Français'],
                'statut' => Cabinet::STATUT_NON_REVENDIQUEE,
                'specialites' => ['Pédiatre'],
                'horaires' => ['08:00', '17:00'],
                'services' => [['Consultation pédiatrique', 15000, 30]],
            ],
            [
                'slug' => 'demo-laboratoire-akebe-libreville',
                'nom' => "Laboratoire d'Analyses d'Akébé",
                'type' => 'medical', 'ville' => 'Libreville', 'quartier' => 'Akébé',
                'description' => 'Prélèvements sans rendez-vous, résultats sous 24 à 48 heures.',
                'langues' => ['Français'],
                'statut' => Cabinet::STATUT_NON_REVENDIQUEE,
                'specialites' => ['Laboratoire'],
                'horaires' => ['07:00', '16:00', '12:00'],
                'services' => [['Numération formule sanguine', 8000, null], ['Glycémie', 3000, null], ['Test de paludisme', 5000, null]],
            ],
            [
                'slug' => 'demo-centre-ophtalmologie-batterie-iv-libreville',
                'nom' => "Centre d'Ophtalmologie de Batterie IV",
                'type' => 'medical', 'ville' => 'Libreville', 'quartier' => 'Batterie IV',
                'description' => 'Examens de la vue, prescription de lunettes et suivi des maladies de l’œil.',
                'langues' => ['Français', 'Anglais'], 'pmr' => true,
                'statut' => Cabinet::STATUT_NON_REVENDIQUEE,
                'specialites' => ['Ophtalmologue'],
                'horaires' => ['08:30', '17:30'],
                'services' => [['Consultation ophtalmologique', 20000, 30]],
            ],
            [
                'slug' => 'demo-cabinet-medical-owendo',
                'nom' => "Cabinet Médical d'Owendo",
                'type' => 'medical', 'ville' => 'Owendo', 'quartier' => 'Centre',
                'description' => 'Médecine générale de proximité, petite chirurgie et pansements.',
                'langues' => ['Français', 'Punu'], 'urgences' => true,
                'statut' => Cabinet::STATUT_NON_REVENDIQUEE,
                'specialites' => ['Médecin généraliste'],
                'horaires' => ['08:00', '18:00', '12:00'],
            ],
            [
                'slug' => 'demo-clinique-dentaire-du-littoral-port-gentil',
                'nom' => 'Clinique Dentaire du Littoral',
                'type' => 'dentaire', 'ville' => 'Port-Gentil', 'quartier' => 'Balise',
                'description' => 'Soins dentaires adultes et enfants, blanchiment et implantologie.',
                'langues' => ['Français', 'Myènè'],
                'statut' => Cabinet::STATUT_NON_REVENDIQUEE,
                'specialites' => ['Cabinet dentaire'],
                'horaires' => ['08:30', '18:00', '12:30'],
                'services' => [['Consultation dentaire', 10000, 20], ['Blanchiment', 60000, 60]],
            ],
            [
                'slug' => 'demo-cabinet-gynecologie-potos-franceville',
                'nom' => 'Cabinet de Gynécologie de Potos',
                'type' => 'medical', 'ville' => 'Franceville', 'quartier' => 'Potos',
                'description' => 'Suivi gynécologique et de grossesse, échographie.',
                'langues' => ['Français', 'Téké'],
                'statut' => Cabinet::STATUT_NON_REVENDIQUEE,
                'specialites' => ['Gynécologue', 'Sage-femme'],
                'horaires' => ['09:00', '17:00'],
                'services' => [['Consultation gynécologique', 20000, 30], ['Échographie obstétricale', 30000, 30]],
            ],
        ];
    }
}
