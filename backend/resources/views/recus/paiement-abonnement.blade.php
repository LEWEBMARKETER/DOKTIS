<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu de paiement — DOKTIS</title>
    <style>
        body { font-family: sans-serif; color: #263238; max-width: 640px; margin: 40px auto; }
        h1 { color: #0B3B4A; font-size: 1.3rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        td:first-child { color: #64748b; }
        td:last-child { text-align: right; font-weight: 600; }
        .montant { font-size: 1.4rem; color: #13B8A6; }
    </style>
</head>
<body>
    <h1>DOKTIS — Reçu de paiement d'abonnement</h1>
    <p>{{ $paiement->abonnement->cabinet->nom }}</p>

    <table>
        <tr><td>Produit</td><td>{{ $paiement->abonnement->produit->label() }}</td></tr>
        <tr><td>Montant</td><td class="montant">{{ number_format((float) $paiement->montant, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td>Mode de paiement</td><td>{{ $paiement->mode_paiement }}</td></tr>
        <tr><td>Référence</td><td>{{ $paiement->reference ?? '—' }}</td></tr>
        <tr><td>Date du paiement</td><td>{{ $paiement->date_paiement->format('d/m/Y') }}</td></tr>
        <tr><td>Période couverte</td><td>{{ $paiement->abonnement->date_debut->format('d/m/Y') }} – {{ $paiement->abonnement->date_fin?->format('d/m/Y') ?? '—' }}</td></tr>
    </table>

    <p style="margin-top: 32px; font-size: 0.85rem; color: #64748b;">
        Justificatif généré le {{ now()->format('d/m/Y à H:i') }}. Enregistrement manuel du paiement par l'administration DOKTIS — aucune passerelle de paiement en ligne n'est utilisée à ce stade.
    </p>
</body>
</html>
