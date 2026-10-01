'use client';

import { useEffect, useState } from 'react';
import { patientApi } from '@/lib/patient-api';
import { formatDate, formatMontant, STATUT_FACTURE_LABELS } from '@/lib/patient-helpers';
import { Badge, Card, PageHeader } from '@/components/ui';
import type { PatientDocument, PatientFacture, PatientOrdonnance, PatientPlanTraitement } from '@/types/patient';

const TABS = [
  { key: 'ordonnances', label: 'Ordonnances' },
  { key: 'documents', label: 'Documents' },
  { key: 'factures', label: 'Factures' },
  { key: 'plans', label: 'Plans de traitement' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export default function DossierMedicalPage() {
  const [tab, setTab] = useState<TabKey>('ordonnances');
  const [loading, setLoading] = useState(true);
  const [ordonnances, setOrdonnances] = useState<PatientOrdonnance[]>([]);
  const [documents, setDocuments] = useState<PatientDocument[]>([]);
  const [factures, setFactures] = useState<PatientFacture[]>([]);
  const [plans, setPlans] = useState<PatientPlanTraitement[]>([]);

  useEffect(() => {
    Promise.all([
      patientApi.get<PatientOrdonnance[]>('/patient/mes-ordonnances'),
      patientApi.get<PatientDocument[]>('/patient/mes-documents'),
      patientApi.get<PatientFacture[]>('/patient/mes-factures'),
      patientApi.get<PatientPlanTraitement[]>('/patient/mes-plans-traitement'),
    ])
      .then(([o, d, f, p]) => {
        setOrdonnances(o);
        setDocuments(d);
        setFactures(f);
        setPlans(p);
      })
      .finally(() => setLoading(false));
  }, []);

  return (
    <div>
      <PageHeader title="Mon dossier médical" description="Consultez vos documents dans tous vos cabinets." />

      <div className="mb-4 flex gap-1 border-b border-slate-200">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`border-b-2 px-4 py-2 text-sm font-medium ${
              tab === t.key ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {loading && <p className="text-sm text-slate-400">Chargement…</p>}

      {!loading && tab === 'ordonnances' && (
        <div className="space-y-3">
          {ordonnances.length === 0 && <p className="text-sm text-slate-400">Aucune ordonnance.</p>}
          {ordonnances.map((o) => (
            <Card key={o.id}>
              <div className="flex items-center justify-between">
                <p className="font-medium text-slate-900">{o.patient.cabinet.nom}</p>
                <p className="text-sm text-slate-500">{formatDate(o.date_emission)}</p>
              </div>
              <p className="mt-1 text-sm text-slate-500">{o.praticien.name}</p>
              <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{o.contenu}</p>
            </Card>
          ))}
        </div>
      )}

      {!loading && tab === 'documents' && (
        <div className="space-y-3">
          {documents.length === 0 && <p className="text-sm text-slate-400">Aucun document.</p>}
          {documents.map((d) => (
            <Card key={d.id} className="flex items-center justify-between">
              <div>
                <p className="font-medium text-slate-900">{d.titre}</p>
                <p className="text-sm text-slate-500">
                  {d.patient.cabinet.nom} · {formatDate(d.created_at)} · {d.type}
                </p>
              </div>
              <a href={d.url} target="_blank" rel="noreferrer" className="text-sm font-medium text-teal-700 hover:underline">
                Ouvrir
              </a>
            </Card>
          ))}
        </div>
      )}

      {!loading && tab === 'factures' && (
        <Card className="overflow-x-auto p-0">
          <table className="w-full min-w-[640px] text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th className="px-4 py-3">N°</th>
                <th className="px-4 py-3">Cabinet</th>
                <th className="px-4 py-3">Montant</th>
                <th className="px-4 py-3">Payé</th>
                <th className="px-4 py-3">Statut</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {factures.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">Aucune facture.</td>
                </tr>
              )}
              {factures.map((f) => (
                <tr key={f.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 text-slate-500">{f.numero}</td>
                  <td className="px-4 py-3">{f.patient.cabinet.nom}</td>
                  <td className="px-4 py-3">{formatMontant(f.montant_total)}</td>
                  <td className="px-4 py-3">{formatMontant(f.montant_paye)}</td>
                  <td className="px-4 py-3">
                    <Badge tone={f.statut === 'payee' ? 'green' : f.statut === 'annulee' ? 'red' : 'amber'}>
                      {STATUT_FACTURE_LABELS[f.statut]}
                    </Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}

      {!loading && tab === 'plans' && (
        <div className="space-y-3">
          {plans.length === 0 && <p className="text-sm text-slate-400">Aucun plan de traitement.</p>}
          {plans.map((p) => (
            <Card key={p.id}>
              <div className="flex items-center justify-between">
                <p className="font-medium text-slate-900">{p.titre}</p>
                <Badge tone={p.statut === 'termine' ? 'green' : p.statut === 'abandonne' ? 'red' : 'blue'}>{p.statut}</Badge>
              </div>
              <p className="text-sm text-slate-500">
                {p.patient.cabinet.nom} · {p.praticien.name}
                {p.cout_estime && ` · ${formatMontant(p.cout_estime)}`}
              </p>
              {p.description && <p className="mt-2 text-sm text-slate-700">{p.description}</p>}
              {p.etapes.length > 0 && (
                <ul className="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm">
                  {p.etapes.map((e) => (
                    <li key={e.id} className="flex justify-between text-slate-600">
                      <span>{e.titre}</span>
                      <span className="text-slate-400">{e.statut}</span>
                    </li>
                  ))}
                </ul>
              )}
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
