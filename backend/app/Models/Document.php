<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCabinet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use BelongsToCabinet, HasFactory;

    protected $fillable = [
        'cabinet_id', 'patient_id', 'consultation_id', 'type', 'titre',
        'nom_fichier', 'chemin', 'disque', 'taille', 'mime_type', 'uploaded_by',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function uploadePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Chemin d'API authentifié et autorisé à utiliser pour télécharger ce
     * document — jamais une URL de stockage directe (locale ou S3), qui
     * contournerait tout contrôle d'accès. Le fichier est systématiquement
     * diffusé (streamé) par le contrôleur après vérification des droits.
     */
    public function cheminTelechargement(string $prefixe = '/api/documents'): string
    {
        return "{$prefixe}/{$this->id}/telecharger";
    }
}
