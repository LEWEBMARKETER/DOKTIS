<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\Cabinet;
use App\Models\Consultation;
use App\Models\Facture;
use App\Models\OrdonnanceModele;
use App\Models\Patient;
use App\Models\PlanTraitement;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $this->command?->info('Cabinet démo créé : admin@cabinet-etoile.dokta / password');
    }
}
