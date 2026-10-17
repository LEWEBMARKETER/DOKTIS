<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\JournalAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::query();

        if ($patientId = $request->query('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return $query->latest()->get()->map(fn (Document $document) => [
            ...$document->toArray(),
            'download_url' => $document->cheminTelechargement(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'consultation_id' => ['nullable', 'exists:consultations,id'],
            'type' => ['required', 'in:photo,radio,pdf,autre'],
            'titre' => ['nullable', 'string', 'max:255'],
            'fichier' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $disque = config('filesystems.documents_disk');
        $fichier = $request->file('fichier');
        // Nom de fichier généré côté serveur (jamais le nom fourni par le
        // client) : évite l'écrasement, la collision et toute tentative de
        // traversée de chemin via un nom de fichier forgé.
        $chemin = $fichier->store("patients/{$data['patient_id']}", $disque);

        $document = Document::create([
            'patient_id' => $data['patient_id'],
            'consultation_id' => $data['consultation_id'] ?? null,
            'type' => $data['type'],
            'titre' => $data['titre'] ?? $fichier->getClientOriginalName(),
            'nom_fichier' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'disque' => $disque,
            'taille' => $fichier->getSize(),
            'mime_type' => $fichier->getClientMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        JournalAction::enregistrer('document.televerse', Document::class, $document->id, ['patient_id' => $document->patient_id]);

        return response()->json([
            ...$document->toArray(),
            'download_url' => $document->cheminTelechargement(),
        ], 201);
    }

    /**
     * Diffuse le fichier au lieu de rediriger vers une URL de stockage : le
     * document reste privé quel que soit le disque (local ou S3/Supabase),
     * et n'est jamais accessible par un simple lien connu sans passer par
     * cette route authentifiée. La liaison de modèle implicite applique le
     * scope cabinet (BelongsToCabinet) : un document d'un autre cabinet
     * renvoie 404 avant même d'atteindre cette méthode.
     */
    public function telecharger(Document $document): StreamedResponse
    {
        JournalAction::enregistrer('document.telecharge', Document::class, $document->id, ['patient_id' => $document->patient_id]);

        return Storage::disk($document->disque)->response($document->chemin, $document->nom_fichier);
    }

    public function destroy(Document $document)
    {
        Storage::disk($document->disque)->delete($document->chemin);

        JournalAction::enregistrer('document.supprime', Document::class, $document->id, ['patient_id' => $document->patient_id]);

        $document->delete();

        return response()->json(status: 204);
    }
}
