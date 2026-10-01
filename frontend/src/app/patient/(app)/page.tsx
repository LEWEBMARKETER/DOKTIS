'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { patientApi } from '@/lib/patient-api';
import { usePatientAuth } from '@/lib/patient-auth-context';
import { buildCabinetNameMap, formatDateTime, STATUT_RDV_LABELS } from '@/lib/patient-helpers';
import { Badge, Button, Card, PageHeader } from '@/components/ui';
import type { Dossier, PatientRendezVous } from '@/types/patient';

export default function PatientDashboardPage() {
  const { account } = usePatientAuth();
  const [rendezVous, setRendezVous] = useState<PatientRendezVous[]>([]);
  const [cabinetNames, setCabinetNames] = useState<Record<number, string>>({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      patientApi.get<PatientRendezVous[]>('/patient/rendez-vous'),
      patientApi.get<Dossier[]>('/patient/mes-dossiers'),
    ])
      .then(([rdv, dossiers]) => {
        setRendezVous(rdv);
        setCabinetNames(buildCabinetNameMap(dossiers));
      })
      .finally(() => setLoading(false));
  }, []);

  const prochains = rendezVous
    .filter((r) => r.statut !== 'annule' && r.statut !== 'termine' && new Date(r.debut) > new Date())
    .sort((a, b) => new Date(a.debut).getTime() - new Date(b.debut).getTime())
    .slice(0, 5);

  return (
    <div>
      <PageHeader
        title={`Bonjour ${account?.prenom ?? ''}`}
        description="Voici un aperçu de votre suivi médical."
        actions={
          <Link href="/patient/rendez-vous/nouveau">
            <Button>+ Prendre rendez-vous</Button>
          </Link>
        }
      />

      <Card>
        <h2 className="mb-3 font-semibold text-slate-900">Prochains rendez-vous</h2>
        {loading && <p className="text-sm text-slate-400">Chargement…</p>}
        {!loading && prochains.length === 0 && (
          <p className="text-sm text-slate-400">Aucun rendez-vous à venir.</p>
        )}
        <ul className="divide-y divide-slate-100">
          {prochains.map((r) => (
            <li key={r.id} className="flex items-center justify-between py-3 text-sm">
              <div>
                <p className="font-medium text-slate-700">{cabinetNames[r.patient.cabinet_id] ?? 'Cabinet'}</p>
                <p className="text-slate-500">
                  {formatDateTime(r.debut)} — {r.praticien.name}
                  {r.motif && ` · ${r.motif}`}
                </p>
              </div>
              <Badge tone={r.statut === 'confirme' ? 'green' : 'blue'}>{STATUT_RDV_LABELS[r.statut]}</Badge>
            </li>
          ))}
        </ul>
        {prochains.length > 0 && (
          <Link href="/patient/rendez-vous" className="mt-3 inline-block text-sm text-teal-700 hover:underline">
            Voir tous mes rendez-vous →
          </Link>
        )}
      </Card>

      <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Link href="/patient/dossier">
          <Card className="transition-shadow hover:shadow-md">
            <p className="text-2xl">📋</p>
            <p className="mt-2 font-medium text-slate-900">Mon dossier médical</p>
            <p className="text-sm text-slate-500">Ordonnances, documents, factures, plans de traitement</p>
          </Card>
        </Link>
        <Link href="/patient/messagerie">
          <Card className="transition-shadow hover:shadow-md">
            <p className="text-2xl">💬</p>
            <p className="mt-2 font-medium text-slate-900">Messagerie</p>
            <p className="text-sm text-slate-500">Échangez avec vos cabinets</p>
          </Card>
        </Link>
        <Link href="/directory">
          <Card className="transition-shadow hover:shadow-md">
            <p className="text-2xl">🔍</p>
            <p className="mt-2 font-medium text-slate-900">Trouver un cabinet</p>
            <p className="text-sm text-slate-500">Parcourir l&apos;annuaire DOKTA</p>
          </Card>
        </Link>
      </div>
    </div>
  );
}
