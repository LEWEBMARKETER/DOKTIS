'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageHeader } from '@/components/ui';
import type { DashboardStats, Facture, Paginated } from '@/types';

function fmt(value: number) {
  return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
}

const STATUT_LABEL: Record<string, string> = {
  brouillon: 'Brouillon',
  envoyee: 'Envoyée',
  partiellement_payee: 'Partiellement payée',
  payee: 'Payée',
  annulee: 'Annulée',
};

export default function StatistiquesPage() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [repartition, setRepartition] = useState<Record<string, number>>({});

  useEffect(() => {
    api.get<DashboardStats>('/dashboard/stats').then(setStats);
    api.get<Paginated<Facture>>('/factures?par_page=200').then((res) => {
      const counts: Record<string, number> = {};
      res.data.forEach((f) => {
        counts[f.statut] = (counts[f.statut] ?? 0) + 1;
      });
      setRepartition(counts);
    });
  }, []);

  if (!stats) return <p className="text-slate-400">Chargement…</p>;

  const totalFactures = Object.values(repartition).reduce((a, b) => a + b, 0) || 1;

  return (
    <div>
      <PageHeader title="Statistiques" description="Suivi d'activité du cabinet." />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Patients suivis" value={stats.total_patients} />
        <Stat label="Consultations (mois)" value={stats.consultations_mois} />
        <Stat label="CA du mois" value={fmt(stats.chiffre_affaires_mois)} />
        <Stat label="Encaissé (mois)" value={fmt(stats.montant_encaisse_mois)} />
      </div>

      <Card className="mt-6">
        <h2 className="mb-4 font-semibold text-slate-800">Répartition des factures par statut</h2>
        <div className="space-y-3">
          {Object.entries(repartition).map(([statut, count]) => (
            <div key={statut}>
              <div className="mb-1 flex justify-between text-sm text-slate-600">
                <span>{STATUT_LABEL[statut] ?? statut}</span>
                <span>{count}</span>
              </div>
              <div className="h-2 w-full rounded-full bg-slate-100">
                <div className="h-2 rounded-full bg-teal-500" style={{ width: `${(count / totalFactures) * 100}%` }} />
              </div>
            </div>
          ))}
          {Object.keys(repartition).length === 0 && <p className="text-sm text-slate-400">Aucune facture pour le moment.</p>}
        </div>
      </Card>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <Card>
      <p className="text-sm text-slate-500">{label}</p>
      <p className="mt-1 text-2xl font-bold text-slate-900">{value}</p>
    </Card>
  );
}
