<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\Abonnement;
use App\Models\Avis;
use App\Models\Cabinet;
use App\Models\CabinetHoraire;
use App\Models\CabinetService;
use App\Models\Consultation;
use App\Models\Conversation;
use App\Models\Depense;
use App\Models\DentTraitement;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Mutuelle;
use App\Models\OrdonnanceModele;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\PlanTraitement;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\TicketSupport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super administrateur DOKTA (gère tous les cabinets, plan/abonnements).
        User::create([
            'name' => 'Support DOKTA',
            'email' => 'superadmin@dokta.app',
            'password' => Hash::make('password'),
            'role' => RoleUtilisateur::SuperAdmin,
        ]);

        $cabinet = Cabinet::create([
            'nom' => 'Cabinet Dentaire Étoile',
            'slug' => 'cabinet-dentaire-etoile',
            'type' => 'dentaire',
            'telephone' => '+237 690 12 34 56',
            'email' => 'contact@cabinet-etoile.dokta',
            'adresse' => 'Avenue Kennedy',
            'ville' => 'Douala',
            'plan' => 'pro',
            'actif' => true,
        ]);

        $admin = User::create([
            'cabinet_id' => $cabinet->id,
            'name' => 'Dr Amina Ngo',
            'email' => 'admin@cabinet-etoile.dokta',
            'password' => Hash::make('password'),
            'role' => RoleUtilisateur::Administrateur,
            'specialite' => 'Chirurgien-dentiste',
        ]);

        $medecin = User::create([
            'cabinet_id' => $cabinet->id,
            'name' => 'Dr Paul Eto',
            'email' => 'medecin@cabinet-etoile.dokta',
            'password' => Hash::make('password'),
            'role' => RoleUtilisateur::Medecin,
            'specialite' => 'Dentiste généraliste',
        ]);

        $secretaire = User::create([
            'cabinet_id' => $cabinet->id,
            'name' => 'Chantal Biya',
            'email' => 'secretaire@cabinet-etoile.dokta',
            'password' => Hash::make('password'),
            'role' => RoleUtilisateur::Secretaire,
        ]);

        $assistant = User::create([
            'cabinet_id' => $cabinet->id,
            'name' => 'Jean Fotso',
            'email' => 'assistant@cabinet-etoile.dokta',
            'password' => Hash::make('password'),
            'role' => RoleUtilisateur::Assistant,
        ]);

        $modeleOrdonnance = OrdonnanceModele::create([
            'cabinet_id' => $cabinet->id,
            'praticien_id' => $medecin->id,
            'titre' => 'Douleur post-soin standard',
            'contenu' => "Paracétamol 1g : 1 comprimé 3 fois par jour pendant 3 jours\nBain de bouche antiseptique : 2 fois par jour pendant 5 jours",
        ]);

        $patients = collect(range(1, 15))->map(function (int $i) use ($cabinet, $admin) {
            return Patient::create([
                'cabinet_id' => $cabinet->id,
                'numero_dossier' => sprintf('2026-%04d', $i),
                'nom' => fake('fr_FR')->lastName(),
                'prenom' => fake('fr_FR')->firstName(),
                'date_naissance' => fake()->dateTimeBetween('-70 years', '-5 years')->format('Y-m-d'),
                'sexe' => fake()->randomElement(['M', 'F']),
                'telephone' => '6'.fake()->numerify('########'),
                'email' => fake()->boolean(60) ? fake()->safeEmail() : null,
                'adresse' => fake('fr_FR')->address(),
                'groupe_sanguin' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
                'allergies' => fake()->boolean(20) ? 'Pénicilline' : null,
                'notes' => null,
                'created_by' => $admin->id,
            ]);
        });

        $praticiens = [$admin, $medecin];

        foreach ($patients as $index => $patient) {
            $praticien = $praticiens[$index % 2];

            // Rendez-vous passés et à venir.
            $rdvPasse = RendezVous::create([
                'cabinet_id' => $cabinet->id,
                'patient_id' => $patient->id,
                'praticien_id' => $praticien->id,
                'motif' => 'Consultation de contrôle',
                'type' => 'controle',
                'debut' => now()->subDays(30 - $index)->setTime(9 + ($index % 6), 0),
                'fin' => now()->subDays(30 - $index)->setTime(9 + ($index % 6), 30),
                'statut' => 'termine',
                'created_by' => $secretaire->id,
            ]);

            if ($index < 8) {
                RendezVous::create([
                    'cabinet_id' => $cabinet->id,
                    'patient_id' => $patient->id,
                    'praticien_id' => $praticien->id,
                    'motif' => fake()->randomElement(['Détartrage', 'Contrôle carie', 'Suivi orthodontique', 'Extraction']),
                    'type' => 'consultation',
                    'debut' => now()->addDays($index)->setTime(9 + ($index % 6), 0),
                    'fin' => now()->addDays($index)->setTime(9 + ($index % 6), 30),
                    'statut' => 'planifie',
                    'created_by' => $secretaire->id,
                ]);
            }

            $consultation = Consultation::create([
                'cabinet_id' => $cabinet->id,
                'patient_id' => $patient->id,
                'praticien_id' => $praticien->id,
                'rendez_vous_id' => $rdvPasse->id,
                'date_consultation' => $rdvPasse->debut,
                'motif' => 'Consultation de contrôle',
                'diagnostic' => fake()->randomElement(['RAS', 'Carie légère', 'Gingivite', 'Tartre important']),
                'observations' => fake('fr_FR')->sentence(12),
                'traitement' => fake()->randomElement(['Détartrage', 'Obturation', 'Conseils hygiène', null]),
                'created_by' => $praticien->id,
            ]);

            if ($index % 3 === 0) {
                $plan = PlanTraitement::create([
                    'cabinet_id' => $cabinet->id,
                    'patient_id' => $patient->id,
                    'consultation_id' => $consultation->id,
                    'praticien_id' => $praticien->id,
                    'titre' => 'Traitement carie multiple',
                    'description' => 'Prise en charge complète des caries diagnostiquées.',
                    'statut' => 'en_cours',
                    'cout_estime' => 75000,
                    'date_debut' => now()->subDays(5),
                    'date_fin_prevue' => now()->addMonths(2),
                ]);

                $plan->etapes()->createMany([
                    ['titre' => 'Détartrage complet', 'ordre' => 0, 'statut' => 'realisee', 'cout' => 15000, 'date_realisee' => now()->subDays(5)],
                    ['titre' => 'Obturation dent 26', 'ordre' => 1, 'statut' => 'en_cours', 'cout' => 25000, 'date_prevue' => now()->addWeek()],
                    ['titre' => 'Contrôle final', 'ordre' => 2, 'statut' => 'a_faire', 'cout' => 5000, 'date_prevue' => now()->addMonths(2)],
                ]);
            }

            if ($index % 2 === 0) {
                $patient->ordonnances()->create([
                    'cabinet_id' => $cabinet->id,
                    'consultation_id' => $consultation->id,
                    'praticien_id' => $praticien->id,
                    'ordonnance_modele_id' => $modeleOrdonnance->id,
                    'contenu' => $modeleOrdonnance->contenu,
                    'date_emission' => $consultation->date_consultation,
                ]);
            }

            $facture = Facture::create([
                'cabinet_id' => $cabinet->id,
                'patient_id' => $patient->id,
                'consultation_id' => $consultation->id,
                'numero' => sprintf('FAC-2026-%05d', $index + 1),
                'date_emission' => $consultation->date_consultation,
                'date_echeance' => $consultation->date_consultation->copy()->addDays(15),
                'statut' => 'envoyee',
                'created_by' => $secretaire->id,
            ]);

            $ligneMontant = fake()->randomElement([15000, 20000, 35000, 50000]);
            $facture->lignes()->create([
                'designation' => 'Consultation et soins',
                'quantite' => 1,
                'prix_unitaire' => $ligneMontant,
                'montant' => $ligneMontant,
            ]);
            $facture->update(['montant_total' => $ligneMontant]);

            match ($index % 3) {
                0 => null, // impayée
                1 => (function () use ($facture, $secretaire) {
                    $facture->paiements()->create([
                        'cabinet_id' => $facture->cabinet_id,
                        'montant' => $facture->montant_total / 2,
                        'mode_paiement' => 'mobile_money',
                        'date_paiement' => now(),
                        'echeance_numero' => 1,
                        'created_by' => $secretaire->id,
                    ]);
                    $facture->increment('montant_paye', $facture->montant_total / 2);
                    $facture->refresh()->rafraichirStatutPaiement();
                })(),
                2 => (function () use ($facture, $secretaire) {
                    $facture->paiements()->create([
                        'cabinet_id' => $facture->cabinet_id,
                        'montant' => $facture->montant_total,
                        'mode_paiement' => 'especes',
                        'date_paiement' => now(),
                        'created_by' => $secretaire->id,
                    ]);
                    $facture->increment('montant_paye', $facture->montant_total);
                    $facture->refresh()->rafraichirStatutPaiement();
                })(),
            };
        }

        $this->seedFondationsPlateforme($cabinet, $admin, $medecin, $secretaire, $patients);

        $this->command?->info('Cabinet démo créé : admin@cabinet-etoile.dokta / password');
        $this->command?->info('Compte patient démo : sonia@patient.dokta / password');
    }

    /**
     * Données de démonstration pour les fondations de la plateforme 3-en-1 :
     * annuaire (DOKTA Directory), compte patient (DOKTA Patient), et
     * administration DOKTA (Super Admin).
     */
    private function seedFondationsPlateforme(
        Cabinet $cabinet,
        User $admin,
        User $medecin,
        User $secretaire,
        \Illuminate\Support\Collection $patients,
    ): void {
        // --- Référentiels partagés par tous les cabinets ---
        $specialites = collect([
            'Cabinet dentaire', 'Médecin généraliste', 'Gynécologue', 'Pédiatre',
            'Ophtalmologue', 'Laboratoire', "Centre d'imagerie",
        ])->map(fn (string $nom) => Specialite::create(['nom' => $nom, 'slug' => Str::slug($nom)]));

        $mutuelles = collect(['CNPS', 'Saham Assurance', 'Activa Assurance', 'Allianz Santé'])
            ->map(fn (string $nom) => Mutuelle::create(['nom' => $nom]));

        // --- DOKTA Directory : fiche publique du cabinet démo ---
        $cabinet->update([
            'quartier' => 'Bonapriso',
            'latitude' => 4.0345,
            'longitude' => 9.7043,
            'description' => "Cabinet dentaire moderne au cœur de Douala, prise en charge des soins conservateurs, de l'orthodontie et de l'implantologie.",
            'langues_parlees' => ['Français', 'Anglais'],
            'accepte_urgences' => true,
            'accessible_pmr' => true,
            'site_web' => 'https://cabinet-etoile.dokta.example',
            'visible_annuaire' => true,
        ]);
        $cabinet->specialites()->sync([$specialites[0]->id, $specialites[1]->id]);
        $cabinet->mutuelles()->sync([$mutuelles[0]->id, $mutuelles[1]->id]);

        foreach ([
            [1, '08:00', '18:00'], [2, '08:00', '18:00'], [3, '08:00', '18:00'],
            [4, '08:00', '18:00'], [5, '08:00', '18:00'], [6, '08:00', '13:00'],
        ] as [$jour, $ouverture, $fermeture]) {
            CabinetHoraire::create(['cabinet_id' => $cabinet->id, 'jour_semaine' => $jour, 'heure_ouverture' => $ouverture, 'heure_fermeture' => $fermeture]);
        }
        CabinetHoraire::create(['cabinet_id' => $cabinet->id, 'jour_semaine' => 0, 'ferme' => true]);

        CabinetService::create(['cabinet_id' => $cabinet->id, 'nom' => 'Détartrage', 'prix_indicatif' => 15000, 'duree_minutes' => 30]);
        CabinetService::create(['cabinet_id' => $cabinet->id, 'nom' => 'Consultation de contrôle', 'prix_indicatif' => 10000, 'duree_minutes' => 20]);
        CabinetService::create(['cabinet_id' => $cabinet->id, 'nom' => 'Pose de couronne', 'prix_indicatif' => 85000, 'duree_minutes' => 60]);

        // --- DOKTA Patient : un compte patient réel, lié à un dossier existant ---
        $comptePatient = PatientAccount::create([
            'nom' => 'Kamdem',
            'prenom' => 'Sonia',
            'email' => 'sonia@patient.dokta',
            'telephone' => '690112233',
            'password' => Hash::make('password'),
            'date_naissance' => '1994-03-12',
            'sexe' => 'F',
        ]);

        $dossierLie = $patients->first();
        $dossierLie->update(['patient_account_id' => $comptePatient->id]);

        $rdvPatientApp = RendezVous::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $dossierLie->id,
            'praticien_id' => $medecin->id,
            'motif' => 'Douleur dentaire',
            'type' => 'consultation',
            'source' => 'patient_app',
            'debut' => now()->addDays(3)->setTime(14, 0),
            'fin' => now()->addDays(3)->setTime(14, 30),
            'statut' => 'planifie',
        ]);

        $conversation = Conversation::create([
            'cabinet_id' => $cabinet->id,
            'patient_account_id' => $comptePatient->id,
            'dernier_message_at' => now(),
        ]);
        $conversation->messages()->create(['expediteur_type' => 'patient', 'expediteur_id' => $comptePatient->id, 'contenu' => 'Bonjour, à quelle heure dois-je arriver mercredi ?']);
        $conversation->messages()->create(['expediteur_type' => 'staff', 'expediteur_id' => $secretaire->id, 'contenu' => 'Bonjour Sonia, votre rendez-vous est à 14h00, merci d\'arriver 10 minutes en avance.', 'lu_at' => now()]);

        Avis::create([
            'cabinet_id' => $cabinet->id,
            'patient_account_id' => $comptePatient->id,
            'note' => 5,
            'commentaire' => 'Accueil chaleureux et soins de qualité, je recommande !',
        ]);
        $cabinet->rafraichirNoteMoyenne();

        DentTraitement::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $dossierLie->id,
            'numero_dent' => 26,
            'type_traitement' => 'obturation',
            'statut' => 'traite',
            'praticien_id' => $medecin->id,
            'date_traitement' => now()->subDays(10),
            'notes' => 'Obturation composite suite à carie occlusale.',
        ]);

        // --- Devis : un proposé, un accepté puis converti en facture ---
        Devis::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $dossierLie->id,
            'numero' => 'DEV-2026-00001',
            'montant_total' => 45000,
            'statut' => 'propose',
            'date_emission' => now(),
            'date_validite' => now()->addDays(30),
        ])->lignes()->create(['designation' => 'Plan orthodontique - phase 1', 'quantite' => 1, 'prix_unitaire' => 45000, 'montant' => 45000]);

        $devisAccepte = Devis::create([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patients[1]->id,
            'numero' => 'DEV-2026-00002',
            'montant_total' => 20000,
            'statut' => 'accepte',
            'date_emission' => now()->subDays(2),
        ]);
        $devisAccepte->lignes()->create(['designation' => 'Détartrage complet', 'quantite' => 1, 'prix_unitaire' => 20000, 'montant' => 20000]);

        // --- Comptabilité ---
        Depense::create(['cabinet_id' => $cabinet->id, 'categorie' => 'Fournitures médicales', 'designation' => 'Gants et compresses', 'montant' => 45000, 'date_depense' => now()->subDays(5), 'created_by' => $admin->id]);
        Depense::create(['cabinet_id' => $cabinet->id, 'categorie' => 'Loyer', 'designation' => 'Loyer du mois', 'montant' => 250000, 'date_depense' => now()->startOfMonth(), 'created_by' => $admin->id]);

        // --- Super Admin DOKTA ---
        Abonnement::create([
            'cabinet_id' => $cabinet->id,
            'plan' => 'Pro',
            'prix' => 25000,
            'cycle_facturation' => 'mensuel',
            'statut' => 'actif',
            'date_debut' => now()->subMonths(2),
            'mode_paiement' => 'mobile_money',
            'taux_commission' => 5,
        ]);

        $ticket = TicketSupport::create([
            'cabinet_id' => $cabinet->id,
            'sujet' => "Question sur l'export comptable",
            'description' => "Comment exporter le journal de caisse du mois dernier au format Excel ?",
            'statut' => 'ouvert',
            'priorite' => 'normale',
        ]);
        $ticket->messages()->create(['auteur_type' => 'staff', 'auteur_id' => $admin->id, 'contenu' => "Comment exporter le journal de caisse du mois dernier au format Excel ?"]);
    }
}
