'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageHeader } from '@/components/ui';

interface Stats {
  total_cabinets: number;
  cabinets_actifs: number;
  fiches_publiees: number;
  fiches_en_attente_verification: number;
  fiches_brouillon: number;
  abonnements_directory_actifs: number;
  demandes_rendez_vous_en_attente: number;
  total_comptes_patients: number;
}

export default function AdminStatistiquesPage() {
  const [stats, setStats] = useState<Stats | null>(null);

  useEffect(() => {
    api.get<Stats>('/super-admin/statistiques').then(setStats);
  }, []);

  if (!stats) return <p className="text-slate-400">Chargement…</p>;

  const tiles: { label: string; value: number }[] = [
    { label: 'Fiches publiées', value: stats.fiches_publiees },
    { label: 'Fiches en attente de vérification', value: stats.fiches_en_attente_verification },
    { label: 'Fiches en brouillon', value: stats.fiches_brouillon },
    { label: 'Abonnements Directory actifs', value: stats.abonnements_directory_actifs },
    { label: 'Demandes de rendez-vous en attente', value: stats.demandes_rendez_vous_en_attente },
    { label: 'Cabinets (total plateforme)', value: stats.total_cabinets },
  ];

  return (
    <div>
      <PageHeader title="Statistiques plateforme" description="Vue d'ensemble DOKTIS Directory et socle multi-cabinets." />
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {tiles.map((t) => (
          <Card key={t.label}>
            <p className="text-sm text-slate-500">{t.label}</p>
            <p className="mt-1 text-3xl font-bold text-doktis-petrol">{t.value}</p>
          </Card>
        ))}
      </div>
    </div>
  );
}
