'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { patientApi, ApiError } from '@/lib/patient-api';
import { buildCabinetNameMap, formatDateTime, STATUT_RDV_LABELS } from '@/lib/patient-helpers';
import { Badge, Button, Card, PageHeader } from '@/components/ui';
import type { Dossier, PatientRendezVous } from '@/types/patient';

function toneForStatut(statut: PatientRendezVous['statut']): 'slate' | 'green' | 'amber' | 'red' | 'blue' {
  switch (statut) {
    case 'confirme':
      return 'green';
    case 'annule':
      return 'red';
    case 'absent':
      return 'amber';
    case 'termine':
      return 'slate';
    default:
      return 'blue';
  }
}

export default function PatientRendezVousPage() {
  const [rendezVous, setRendezVous] = useState<PatientRendezVous[]>([]);
  const [cabinetNames, setCabinetNames] = useState<Record<number, string>>({});
  const [loading, setLoading] = useState(true);
  const [cancellingId, setCancellingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  function load() {
    setLoading(true);
    Promise.all([
      patientApi.get<PatientRendezVous[]>('/patient/rendez-vous'),
      patientApi.get<Dossier[]>('/patient/mes-dossiers'),
    ])
      .then(([rdv, dossiers]) => {
        setRendezVous(rdv);
        setCabinetNames(buildCabinetNameMap(dossiers));
      })
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  async function annuler(id: number) {
    setError(null);
    setCancellingId(id);
    try {
      await patientApi.post(`/patient/rendez-vous/${id}/annuler`);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible d'annuler ce rendez-vous.");
    } finally {
      setCancellingId(null);
    }
  }

  return (
    <div>
      <PageHeader
        title="Mes rendez-vous"
        description="Tous vos rendez-vous, tous cabinets confondus."
        actions={
          <Link href="/patient/rendez-vous/nouveau">
            <Button>+ Nouveau rendez-vous</Button>
          </Link>
        }
      />

      {error && <p className="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <Card className="overflow-x-auto p-0">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Cabinet</th>
              <th className="px-4 py-3">Praticien</th>
              <th className="px-4 py-3">Date &amp; heure</th>
              <th className="px-4 py-3">Statut</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-slate-400">Chargement…</td>
              </tr>
            )}
            {!loading && rendezVous.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-slate-400">Aucun rendez-vous.</td>
              </tr>
            )}
            {rendezVous.map((r) => (
              <tr key={r.id} className="hover:bg-slate-50">
                <td className="px-4 py-3">{cabinetNames[r.patient.cabinet_id] ?? 'Cabinet'}</td>
                <td className="px-4 py-3">{r.praticien.name}</td>
                <td className="px-4 py-3">{formatDateTime(r.debut)}</td>
                <td className="px-4 py-3">
                  <Badge tone={toneForStatut(r.statut)}>{STATUT_RDV_LABELS[r.statut]}</Badge>
                </td>
                <td className="px-4 py-3 text-right">
                  {(r.statut === 'planifie' || r.statut === 'confirme') && (
                    <Button variant="secondary" disabled={cancellingId === r.id} onClick={() => annuler(r.id)}>
                      {cancellingId === r.id ? 'Annulation…' : 'Annuler'}
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
