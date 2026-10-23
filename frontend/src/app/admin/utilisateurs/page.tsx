'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, ErrorText, PageHeader } from '@/components/ui';
import type { PartenaireAdmin, Paginated } from '@/types';

export default function AdminUtilisateursPage() {
  const [partenaires, setPartenaires] = useState<PartenaireAdmin[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  function load() {
    api.get<Paginated<PartenaireAdmin>>('/admin/partenaires').then((res) => setPartenaires(res.data));
  }

  useEffect(load, []);

  async function basculer(p: PartenaireAdmin) {
    if (busyId !== null) return;
    setError(null);
    setBusyId(p.id);
    try {
      await api.patch(`/admin/partenaires/${p.id}`, { actif: !p.actif });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Action impossible.');
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div>
      <PageHeader
        title="Comptes partenaires"
        description="Un compte par fiche, créé exclusivement via invitation (depuis la fiche concernée)."
      />
      <ErrorText>{error}</ErrorText>
      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Nom</th>
              <th className="px-4 py-3">Email</th>
              <th className="px-4 py-3">Fiche</th>
              <th className="px-4 py-3">Statut</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {partenaires.length === 0 && <tr><td colSpan={5} className="px-4 py-6 text-center text-slate-400">Aucun compte partenaire.</td></tr>}
            {partenaires.map((p) => (
              <tr key={p.id} className="hover:bg-slate-50">
                <td className="px-4 py-3 font-medium text-slate-800">{p.name}</td>
                <td className="px-4 py-3">{p.email}</td>
                <td className="px-4 py-3">{p.cabinet?.nom}</td>
                <td className="px-4 py-3"><Badge tone={p.actif ? 'green' : 'slate'}>{p.actif ? 'Actif' : 'Inactif'}</Badge></td>
                <td className="px-4 py-3 text-right">
                  <Button variant="secondary" disabled={busyId !== null} onClick={() => basculer(p)}>
                    {p.actif ? 'Désactiver' : 'Activer'}
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
