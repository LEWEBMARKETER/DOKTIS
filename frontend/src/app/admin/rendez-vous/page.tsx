'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Badge, Card, PageHeader, Select } from '@/components/ui';
import type { DemandeRendezVousAdmin, Paginated } from '@/types';

const STATUT_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red' | 'blue'> = {
  en_attente: 'amber', confirmee: 'green', refusee: 'red', annulee: 'slate', terminee: 'blue',
};

export default function AdminRendezVousPage() {
  const [demandes, setDemandes] = useState<DemandeRendezVousAdmin[]>([]);
  const [statut, setStatut] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    const params = statut ? `?statut=${statut}` : '';
    api
      .get<Paginated<DemandeRendezVousAdmin>>(`/admin/demandes-rendez-vous${params}`)
      .then((res) => setDemandes(res.data))
      .finally(() => setLoading(false));
  }, [statut]);

  return (
    <div>
      <PageHeader
        title="Demandes de rendez-vous"
        description="Vue d'ensemble, tous partenaires confondus. Le traitement reste la responsabilité du partenaire concerné."
      />

      <Card className="mb-4 max-w-xs">
        <Select value={statut} onChange={(e) => setStatut(e.target.value)}>
          <option value="">Tous les statuts</option>
          <option value="en_attente">En attente</option>
          <option value="confirmee">Confirmée</option>
          <option value="refusee">Refusée</option>
          <option value="annulee">Annulée</option>
          <option value="terminee">Terminée</option>
        </Select>
      </Card>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Fiche</th>
              <th className="px-4 py-3">Contact</th>
              <th className="px-4 py-3">Date souhaitée</th>
              <th className="px-4 py-3">Statut</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading && <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-400">Chargement…</td></tr>}
            {!loading && demandes.length === 0 && <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-400">Aucune demande.</td></tr>}
            {demandes.map((d) => (
              <tr key={d.id} className="hover:bg-slate-50">
                <td className="px-4 py-3">{d.cabinet?.nom}</td>
                <td className="px-4 py-3">{d.prenom} {d.nom} — {d.telephone}</td>
                <td className="px-4 py-3">{new Date(d.date_souhaitee).toLocaleDateString('fr-FR')}</td>
                <td className="px-4 py-3"><Badge tone={STATUT_TONE[d.statut]}>{d.statut}</Badge></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
