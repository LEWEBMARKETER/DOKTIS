'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { Badge, Card, PageHeader } from '@/components/ui';
import type { DashboardStats } from '@/types';

function formatFCFA(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
}

const STATUT_BADGE: Record<string, 'slate' | 'green' | 'amber' | 'red' | 'blue'> = {
  planifie: 'blue',
  confirme: 'blue',
  termine: 'green',
  annule: 'red',
  absent: 'amber',
};

export default function DashboardPage() {
  const { user } = useAuth();
  const router = useRouter();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (user?.role === 'super_admin') {
      router.replace('/cabinets');
      return;
    }
    api
      .get<DashboardStats>('/dashboard/stats')
      .then(setStats)
      .catch(() => setError('Impossible de charger les statistiques.'));
  }, [user, router]);

  if (error) return <p className="text-red-600">{error}</p>;
  if (!stats) return <p className="text-slate-400">Chargement…</p>;

  const cards = [
    { label: 'Patients', value: stats.total_patients, href: '/patients' },
    { label: "Rendez-vous aujourd'hui", value: stats.rendez_vous_aujourdhui, href: '/agenda' },
    { label: 'Consultations ce mois', value: stats.consultations_mois, href: '/consultations' },
    { label: 'Factures impayées', value: stats.factures_impayees, href: '/facturation' },
  ];

  return (
    <div>
      <PageHeader title={`Bonjour, ${user?.name}`} description="Aperçu de l'activité de votre cabinet." />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {cards.map((c) => (
          <Link key={c.label} href={c.href}>
            <Card className="transition-shadow hover:shadow-md">
              <p className="text-sm text-slate-500">{c.label}</p>
              <p className="mt-1 text-3xl font-bold text-slate-900">{c.value}</p>
            </Card>
          </Link>
        ))}
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <p className="text-sm text-slate-500">Chiffre d&apos;affaires (mois en cours)</p>
          <p className="mt-1 text-2xl font-bold text-slate-900">{formatFCFA(stats.chiffre_affaires_mois)}</p>
          <p className="mt-3 text-sm text-slate-500">Encaissé</p>
          <p className="text-xl font-semibold text-emerald-600">{formatFCFA(stats.montant_encaisse_mois)}</p>
        </Card>

        <Card>
          <p className="mb-3 text-sm font-medium text-slate-700">Prochains rendez-vous</p>
          {stats.prochains_rendez_vous.length === 0 && <p className="text-sm text-slate-400">Aucun rendez-vous à venir.</p>}
          <ul className="space-y-2">
            {stats.prochains_rendez_vous.map((rdv) => (
              <li key={rdv.id} className="flex items-center justify-between text-sm">
                <div>
                  <p className="font-medium text-slate-800">
                    {rdv.patient?.prenom} {rdv.patient?.nom}
                  </p>
                  <p className="text-slate-500">
                    {new Date(rdv.debut).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })} — {rdv.praticien?.name}
                  </p>
                </div>
                <Badge tone={STATUT_BADGE[rdv.statut] ?? 'slate'}>{rdv.statut}</Badge>
              </li>
            ))}
          </ul>
        </Card>
      </div>
    </div>
  );
}
