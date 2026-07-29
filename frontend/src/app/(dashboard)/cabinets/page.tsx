'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Badge, Card, PageHeader } from '@/components/ui';
import type { Cabinet } from '@/types';

interface CabinetRow extends Cabinet {
  users_count: number;
  patients_count: number;
}

export default function CabinetsPage() {
  const [cabinets, setCabinets] = useState<CabinetRow[]>([]);

  useEffect(() => {
    api.get<CabinetRow[]>('/cabinets').then(setCabinets);
  }, []);

  return (
    <div>
      <PageHeader title="Cabinets DOKTA" description="Administration multi-cabinets (super administrateur)." />

      <Card className="overflow-x-auto p-0">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Cabinet</th>
              <th className="px-4 py-3">Plan</th>
              <th className="px-4 py-3">Utilisateurs</th>
              <th className="px-4 py-3">Patients</th>
              <th className="px-4 py-3">Statut</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {cabinets.map((c) => (
              <tr key={c.id} className="hover:bg-slate-50">
                <td className="px-4 py-3 font-medium text-slate-800">{c.nom}</td>
                <td className="px-4 py-3 capitalize">{c.plan}</td>
                <td className="px-4 py-3">{c.users_count}</td>
                <td className="px-4 py-3">{c.patients_count}</td>
                <td className="px-4 py-3">
                  <Badge tone={c.actif ? 'green' : 'red'}>{c.actif ? 'Actif' : 'Suspendu'}</Badge>
                </td>
              </tr>
            ))}
            {cabinets.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-slate-400">Aucun cabinet enregistré.</td>
              </tr>
            )}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
