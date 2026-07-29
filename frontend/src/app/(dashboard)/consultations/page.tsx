'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Card, PageHeader } from '@/components/ui';
import type { Consultation, Paginated } from '@/types';

export default function ConsultationsPage() {
  const [consultations, setConsultations] = useState<Consultation[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api
      .get<Paginated<Consultation>>('/consultations')
      .then((res) => setConsultations(res.data))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div>
      <PageHeader title="Consultations" description="Historique des consultations du cabinet." />

      <Card className="overflow-x-auto p-0">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Date</th>
              <th className="px-4 py-3">Patient</th>
              <th className="px-4 py-3">Praticien</th>
              <th className="px-4 py-3">Diagnostic</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-slate-400">Chargement…</td>
              </tr>
            )}
            {!loading && consultations.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-slate-400">Aucune consultation.</td>
              </tr>
            )}
            {consultations.map((c) => (
              <tr key={c.id} className="hover:bg-slate-50">
                <td className="px-4 py-3 text-slate-500">
                  {new Date(c.date_consultation).toLocaleDateString('fr-FR', { dateStyle: 'medium' })}
                </td>
                <td className="px-4 py-3">
                  <Link href={`/patients/${c.patient_id}`} className="font-medium text-teal-700 hover:underline">
                    {c.patient?.prenom} {c.patient?.nom}
                  </Link>
                </td>
                <td className="px-4 py-3">{c.praticien?.name}</td>
                <td className="px-4 py-3">{c.diagnostic ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
