'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Card, PageHeader } from '@/components/ui';

interface Stats {
  fiches_publiees: number;
  fiches_en_attente_verification: number;
  fiches_brouillon: number;
  abonnements_directory_actifs: number;
  demandes_rendez_vous_en_attente: number;
}

const RACCOURCIS = [
  { href: '/admin/structures', label: 'Structures sanitaires', icon: '🏥' },
  { href: '/admin/professionnels', label: 'Professionnels indépendants', icon: '🩺' },
  { href: '/admin/rendez-vous', label: 'Demandes de rendez-vous', icon: '📅' },
  { href: '/admin/abonnements', label: 'Abonnements', icon: '💳' },
];

export default function AdminTableauDeBordPage() {
  const [stats, setStats] = useState<Stats | null>(null);

  useEffect(() => {
    api.get<Stats>('/super-admin/statistiques').then(setStats);
  }, []);

  return (
    <div>
      <PageHeader title="Tableau de bord" description="Administration DOKTIS Directory." />

      {stats && (
        <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Card><p className="text-sm text-slate-500">Fiches publiées</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.fiches_publiees}</p></Card>
          <Card><p className="text-sm text-slate-500">En attente de vérification</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.fiches_en_attente_verification}</p></Card>
          <Card><p className="text-sm text-slate-500">Brouillons</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.fiches_brouillon}</p></Card>
          <Card><p className="text-sm text-slate-500">Abonnements actifs</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.abonnements_directory_actifs}</p></Card>
          <Card><p className="text-sm text-slate-500">Demandes en attente</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.demandes_rendez_vous_en_attente}</p></Card>
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {RACCOURCIS.map((r) => (
          <Link key={r.href} href={r.href}>
            <Card className="transition-shadow hover:shadow-md">
              <p className="text-2xl">{r.icon}</p>
              <p className="mt-2 font-medium text-slate-900">{r.label}</p>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  );
}
