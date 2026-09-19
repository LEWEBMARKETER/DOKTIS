<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\TenantRule;
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

        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', TenantRule::exists($request->user(), 'patients')],
            'consultation_id' => ['nullable', TenantRule::exists($request->user(), 'consultations')],
            'type' => ['required', 'in:photo,radio,pdf,autre'],
            'titre' => ['nullable', 'string', 'max:255'],
            'fichier' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $disque = config('filesystems.documents_disk');
        $fichier = $request->file('fichier');
        $chemin = $fichier->store("patients/{$data['patient_id']}", $disque);

        try {
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
        } catch (\Throwable $e) {
            Storage::disk($disque)->delete($chemin);
            throw $e;
        }

        return response()->json($document, 201);
    }

    public function download(Document $document)
    {
        abort_unless(Storage::disk($document->disque)->exists($document->chemin), 404);

        return Storage::disk($document->disque)->download($document->chemin, $document->nom_fichier);
    }

    public function destroy(Document $document)
    {
        Storage::disk($document->disque)->delete($document->chemin);
        $document->delete();

        return response()->json(status: 204);
    }
}
