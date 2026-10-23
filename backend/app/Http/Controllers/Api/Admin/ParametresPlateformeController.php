<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

/**
 * Lecture seule : ces paramètres sont pilotés par les variables
 * d'environnement du serveur (config/features.php, config/directory.php),
 * pas par une table modifiable en direct depuis l'interface — les
 * changer nécessite un redéploiement, volontairement, pour éviter qu'un
 * flag de module aussi sensible soit basculé par erreur sans revue.
 */
class ParametresPlateformeController extends Controller
{
    public function index()
    {
        return response()->json([
            'features' => config('features'),
            'directory' => [
                'jours_grace_abonnement' => config('directory.jours_grace_abonnement'),
            ],
        ]);
    }
}
