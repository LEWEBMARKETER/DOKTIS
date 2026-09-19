<?php

namespace App\Services;

use App\Models\RendezVous;
use Illuminate\Support\Facades\DB;

final class DisponibiliteRendezVous
{
    public function conflit(int $praticienId, string $debut, string $fin, ?int $exclureId = null): bool
    {
        // Sérialise les écritures d'agenda d'un praticien sous PostgreSQL et
        // évite que deux requêtes concurrentes réservent le même créneau.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(?)', [$praticienId]);
        }

        return RendezVous::query()
            ->where('praticien_id', $praticienId)
            ->when($exclureId, fn ($query) => $query->whereKeyNot($exclureId))
            ->whereNotIn('statut', ['annule', 'reprogramme'])
            ->where('debut', '<', $fin)
            ->where('fin', '>', $debut)
            ->lockForUpdate()
            ->first(['id']) !== null;
    }
}
