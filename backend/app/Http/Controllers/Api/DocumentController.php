<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'url' => $document->url(),
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

        return response()->json([...$document->toArray(), 'url' => $document->url()], 201);
    }

    public function destroy(Document $document)
    {
        Storage::disk($document->disque)->delete($document->chemin);
        $document->delete();

        return response()->json(status: 204);
    }
}
