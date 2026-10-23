'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { Badge, Card, ErrorText, PageHeader } from '@/components/ui';
import type { AbonnementAdmin } from '@/types';

const STATUT_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red'> = {
  actif: 'green', suspendu: 'amber', annule: 'slate', expire: 'red',
};

export default function PartenaireAbonnementPage() {
  const [abonnement, setAbonnement] = useState<AbonnementAdmin | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<AbonnementAdmin>('/partenaire/abonnement')
      .then(setAbonnement)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Aucun abonnement trouvé.'));
  }, []);

  return (
    <div>
      <PageHeader title="Mon abonnement" description="Consultez votre abonnement DOKTIS Directory et vos justificatifs de paiement." />

      <ErrorText>{error}</ErrorText>

      {abonnement && (
        <>
          <Card className="mb-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-semibold text-slate-900">
                  {abonnement.produit === 'directory_structure' ? 'DOKTIS Structure' : 'DOKTIS Professionnel'}
                </p>
                <p className="text-sm text-slate-500">
                  {Number(abonnement.prix).toLocaleString('fr-FR')} FCFA / {abonnement.cycle_facturation === 'annuel' ? 'an' : 'mois'}
                </p>
              </div>
              <Badge tone={STATUT_TONE[abonnement.statut]}>{abonnement.statut}</Badge>
            </div>
            <p className="mt-3 text-sm text-slate-600">
              Échéance : {abonnement.date_fin ? new Date(abonnement.date_fin).toLocaleDateString('fr-FR') : 'non définie'}
            </p>
          </Card>

          <Card>
            <h2 className="mb-3 font-semibold text-slate-900">Historique des paiements</h2>
            {!abonnement.paiements?.length && <p className="text-sm text-slate-400">Aucun paiement enregistré.</p>}
            <ul className="divide-y divide-slate-100">
              {abonnement.paiements?.map((p) => (
                <li key={p.id} className="flex items-center justify-between py-2 text-sm">
                  <span>
                    {new Date(p.date_paiement).toLocaleDateString('fr-FR')} — {Number(p.montant).toLocaleString('fr-FR')} FCFA ({p.mode_paiement})
                  </span>
                  <a
                    href={`/api/partenaire/abonnement/paiements/${p.id}/recu`}
                    target="_blank"
                    rel="noreferrer"
                    className="text-doktis-petrol hover:underline"
                  >
                    Reçu
                  </a>
                </li>
              ))}
            </ul>
          </Card>
        </>
      )}
    </div>
  );
}
