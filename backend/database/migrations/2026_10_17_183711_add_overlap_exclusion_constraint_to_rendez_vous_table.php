<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Empêche le double-booking au niveau base de données, y compris sous
 * requêtes concurrentes : la vérification applicative (SELECT ... exists())
 * suivie d'un INSERT séparé n'est pas atomique et laisse une fenêtre de
 * course où deux requêtes simultanées peuvent chacune passer la
 * vérification puis créer un rendez-vous qui se chevauche. Une contrainte
 * EXCLUDE PostgreSQL est vérifiée par le moteur lui-même à l'écriture,
 * quel que soit le nombre de requêtes concurrentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Requis par l'opérateur d'égalité (=) utilisable dans un index GiST,
        // nécessaire pour combiner "même praticien" et "chevauchement de
        // créneau" dans une seule contrainte EXCLUDE.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // Même exclusion que les contrôleurs applicatifs (RendezVousController,
        // Patient\RendezVousController) : seul un rendez-vous "annulé" ne
        // bloque pas un nouveau créneau sur le même praticien.
        DB::statement(<<<'SQL'
            ALTER TABLE rendez_vous
            ADD CONSTRAINT rendez_vous_pas_de_chevauchement
            EXCLUDE USING gist (
                praticien_id WITH =,
                tsrange(debut, fin) WITH &&
            ) WHERE (statut <> 'annule')
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE rendez_vous DROP CONSTRAINT IF EXISTS rendez_vous_pas_de_chevauchement');
    }
};
