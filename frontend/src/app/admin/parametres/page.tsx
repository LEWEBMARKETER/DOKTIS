'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Badge, Card, PageHeader } from '@/components/ui';

interface Parametres {
  features: { directory: boolean; office: boolean; patient_medical: boolean };
  directory: { jours_grace_abonnement: number };
}

const LABELS: Record<string, string> = {
  directory: 'DOKTIS Directory (annuaire public)',
  office: 'DOKTIS Office (SaaS cabinet)',
  patient_medical: 'Dossier médical patient',
};

export default function AdminParametresPage() {
  const [parametres, setParametres] = useState<Parametres | null>(null);

  useEffect(() => {
    api.get<Parametres>('/admin/parametres').then(setParametres);
  }, []);

  if (!parametres) return <p className="text-slate-400">Chargement…</p>;

  return (
    <div>
      <PageHeader title="Paramètres plateforme" description="Lecture seule : ces modules sont pilotés par les variables d'environnement du serveur." />

      <Card className="mb-6">
        <h2 className="mb-3 font-semibold text-slate-900">Modules</h2>
        <ul className="space-y-2 text-sm">
          {Object.entries(parametres.features).map(([cle, actif]) => (
            <li key={cle} className="flex items-center justify-between">
              <span>{LABELS[cle] ?? cle}</span>
              <Badge tone={actif ? 'green' : 'slate'}>{actif ? 'Activé' : 'Désactivé'}</Badge>
            </li>
          ))}
        </ul>
        <p className="mt-4 text-xs text-slate-400">
          Pour activer ou désactiver un module, modifiez la variable d&apos;environnement correspondante
          (DOKTIS_OFFICE_ENABLED, DOKTIS_PATIENT_MEDICAL_ENABLED, DOKTIS_DIRECTORY_ENABLED) côté serveur, puis redéployez.
        </p>
      </Card>

      <Card>
        <h2 className="mb-3 font-semibold text-slate-900">Abonnements</h2>
        <p className="text-sm text-slate-600">
          Période de grâce après expiration : <strong>{parametres.directory.jours_grace_abonnement} jours</strong> (DOKTIS_JOURS_GRACE_ABONNEMENT).
        </p>
      </Card>
    </div>
  );
}
