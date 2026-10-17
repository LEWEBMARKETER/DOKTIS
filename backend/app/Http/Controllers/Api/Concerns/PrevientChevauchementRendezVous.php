<?php

namespace App\Http\Controllers\Api\Concerns;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait PrevientChevauchementRendezVous
{
    /**
     * Exécute la création/mise à jour du rendez-vous dans une transaction et
     * convertit la violation de la contrainte EXCLUDE PostgreSQL
     * (SQLSTATE 23P01 : chevauchement de créneau pour le même praticien) en
     * réponse 422 propre.
     *
     * C'est la protection réelle contre le double-booking sous requêtes
     * concurrentes : la vérification préalable (where(...)->exists()) laisse
     * une fenêtre de course entre la lecture et l'écriture — deux requêtes
     * simultanées peuvent toutes les deux la passer puis créer un
     * chevauchement. La contrainte, elle, est vérifiée par PostgreSQL au
     * moment de l'écriture, quel que soit le nombre de requêtes concurrentes.
     */
    protected function creerSansChevauchement(Closure $callback)
    {
        try {
            return DB::transaction($callback);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23P01') {
                abort(422, 'Ce créneau ne semble plus disponible, merci de recharger les disponibilités.');
            }

            throw $e;
        }
    }
}
