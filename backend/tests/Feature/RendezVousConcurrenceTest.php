<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Concerns\PrevientChevauchementRendezVous;
use App\Models\Cabinet;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Database\QueryException;
use PDO;
use Tests\TestCase;

class RendezVousConcurrenceTest extends TestCase
{
    /**
     * Volontairement SANS RefreshDatabase : ce trait enveloppe le test dans
     * une transaction non validée sur la connexion Eloquent par défaut, ce
     * qui la rendrait invisible aux connexions PDO indépendantes ouvertes
     * ci-dessous (PostgreSQL applique MVCC entre sessions distinctes) — le
     * test perdrait alors tout son sens. Nettoyage manuel en tearDown().
     */
    private array $cabinetIdsACreer = [];

    protected function tearDown(): void
    {
        foreach ($this->cabinetIdsACreer as $cabinetId) {
            \DB::table('rendez_vous')->where('cabinet_id', $cabinetId)->delete();
            \DB::table('patients')->where('cabinet_id', $cabinetId)->delete();
            \DB::table('users')->where('cabinet_id', $cabinetId)->delete();
            \DB::table('cabinets')->where('id', $cabinetId)->delete();
        }

        parent::tearDown();
    }

    /**
     * Preuve, sur deux connexions PostgreSQL distinctes (deux sessions
     * indépendantes, comme le seraient deux requêtes HTTP traitées par deux
     * workers différents), que la contrainte EXCLUDE rejette toujours le
     * second rendez-vous qui chevauche un créneau déjà réservé pour le même
     * praticien — quel que soit l'ordre d'arrivée des requêtes.
     *
     * Limite assumée : PHPUnit s'exécute dans un seul processus PHP et ne
     * peut pas déclencher deux appels bloquants réellement simultanés sur
     * deux connexions sans fork ni I/O asynchrone (non disponible de façon
     * fiable ici). Ce test valide donc la propriété qui rend la concurrence
     * sûre — le moteur rejette *tout* chevauchement, peu importe quelle
     * transaction valide en dernier — plutôt que de rejouer littéralement
     * le blocage temporel. Une vérification complémentaire avec deux vraies
     * requêtes HTTP lancées en parallèle (curl en arrière-plan contre le
     * serveur de développement réel) a été effectuée manuellement ; voir
     * SECURITY.md.
     */
    public function test_la_contrainte_rejette_tout_chevauchement_quel_que_soit_lordre_darrivee(): void
    {
        $cabinet = Cabinet::factory()->create();
        $this->cabinetIdsACreer[] = $cabinet->id;
        $praticien = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $patientA = Patient::factory()->create(['cabinet_id' => $cabinet->id]);
        $patientB = Patient::factory()->create(['cabinet_id' => $cabinet->id]);

        $config = config('database.connections.pgsql');
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']);
        $pdoA = new PDO($dsn, $config['username'], $config['password']);
        $pdoB = new PDO($dsn, $config['username'], $config['password']);

        $debut = now()->addDay()->setTime(10, 0)->toDateTimeString();
        $fin = now()->addDay()->setTime(10, 30)->toDateTimeString();

        $insert = <<<'SQL'
            INSERT INTO rendez_vous (cabinet_id, patient_id, praticien_id, type, source, debut, fin, statut, created_at, updated_at)
            VALUES (:cabinet_id, :patient_id, :praticien_id, 'consultation', 'office', :debut, :fin, 'planifie', now(), now())
        SQL;

        // Connexion A : réserve le créneau et valide immédiatement — simule
        // la requête qui "gagne" la course.
        $pdoA->beginTransaction();
        $pdoA->prepare($insert)->execute([
            'cabinet_id' => $cabinet->id,
            'patient_id' => $patientA->id,
            'praticien_id' => $praticien->id,
            'debut' => $debut,
            'fin' => $fin,
        ]);
        $pdoA->commit();

        // Connexion B, totalement indépendante : tente le même créneau pour
        // le même praticien, comme le ferait une seconde requête HTTP
        // concurrente pour le même rendez-vous.
        $pdoB->beginTransaction();
        $exceptionLeveeParB = null;
        try {
            $pdoB->prepare($insert)->execute([
                'cabinet_id' => $cabinet->id,
                'patient_id' => $patientB->id,
                'praticien_id' => $praticien->id,
                'debut' => $debut,
                'fin' => $fin,
            ]);
            $pdoB->commit();
        } catch (\PDOException $e) {
            $exceptionLeveeParB = $e;
            $pdoB->rollBack();
        }

        $this->assertNotNull($exceptionLeveeParB, 'La seconde réservation du même créneau aurait dû être rejetée par la contrainte EXCLUDE.');
        $this->assertSame('23P01', $exceptionLeveeParB->errorInfo[0] ?? null);

        $this->assertSame(1, RendezVous::withoutGlobalScopes()
            ->where('praticien_id', $praticien->id)
            ->where('debut', $debut)
            ->count());
    }

    public function test_la_violation_de_contrainte_est_convertie_en_reponse_422(): void
    {
        $controller = new class
        {
            use PrevientChevauchementRendezVous;

            public function appeler(\Closure $callback)
            {
                return $this->creerSansChevauchement($callback);
            }
        };

        $exception = new QueryException('pgsql', 'insert into "rendez_vous" ...', [], new \Exception('simulée'));

        $reflection = new \ReflectionProperty($exception, 'errorInfo');
        $reflection->setAccessible(true);
        $reflection->setValue($exception, ['23P01', 1, 'conflicting key value violates exclusion constraint']);

        try {
            $controller->appeler(function () use ($exception) {
                throw $exception;
            });
            $this->fail('Une HttpException 422 était attendue.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
