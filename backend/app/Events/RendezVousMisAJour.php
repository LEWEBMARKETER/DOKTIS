<?php

namespace App\Events;

use App\Models\RendezVous;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusé à la création, la mise à jour de statut ou la reprogrammation d'un
 * rendez-vous, pour tenir l'agenda du cabinet synchronisé en temps réel
 * (DOKTA Office) et notifier le patient concerné (DOKTA Patient).
 */
class RendezVousMisAJour implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public RendezVous $rendezVous) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('cabinet.'.$this->rendezVous->cabinet_id)];

        $compteId = $this->rendezVous->patient?->patient_account_id;
        if ($compteId) {
            $channels[] = new PrivateChannel('patient.'.$compteId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'rendez-vous.mis-a-jour';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->rendezVous->id,
            'statut' => $this->rendezVous->statut,
            'debut' => $this->rendezVous->debut->toIso8601String(),
            'fin' => $this->rendezVous->fin->toIso8601String(),
        ];
    }
}
