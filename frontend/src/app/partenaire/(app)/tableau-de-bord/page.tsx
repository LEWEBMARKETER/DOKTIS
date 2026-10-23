'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { Badge, Card, PageHeader } from '@/components/ui';
import type { AdminFiche } from '@/types';

interface Stats {
  totaux: { visites: number; demandes_rendez_vous: number; demandes_confirmees: number };
}

const STATUT_PUB_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red'> = {
  brouillon: 'slate', publiee: 'green', suspendue: 'amber', archivee: 'red',
};

export default function PartenaireTableauDeBordPage() {
  const { user } = useAuth();
  const [fiche, setFiche] = useState<AdminFiche | null>(null);
  const [stats, setStats] = useState<Stats | null>(null);

  useEffect(() => {
    api.get<AdminFiche>('/partenaire/ma-fiche').then(setFiche);
    api.get<Stats>('/partenaire/statistiques?periode=30').then(setStats);
  }, []);

  return (
    <div>
      <PageHeader title={`Bonjour${user ? `, ${user.name}` : ''}`} description="Tableau de bord de votre fiche DOKTIS Directory." />

      {fiche && (
        <Card className="mb-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-semibold text-slate-900">{fiche.nom}</p>
              <p className="text-sm text-slate-500">{fiche.categorie_fiche === 'structure' ? 'Structure sanitaire' : 'Professionnel indépendant'}</p>
            </div>
            <Badge tone={STATUT_PUB_TONE[fiche.statut_publication]}>{fiche.statut_publication}</Badge>
          </div>
        </Card>
      )}

      {stats && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Card><p className="text-sm text-slate-500">Visites (30 jours)</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.visites}</p></Card>
          <Card><p className="text-sm text-slate-500">Demandes de rendez-vous</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.demandes_rendez_vous}</p></Card>
          <Card><p className="text-sm text-slate-500">Confirmées</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.demandes_confirmees}</p></Card>
        </div>
      )}

      <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link href="/partenaire/rendez-vous">
          <Card className="transition-shadow hover:shadow-md">
            <p className="font-medium text-slate-900">Traiter les demandes de rendez-vous</p>
          </Card>
        </Link>
        <Link href="/partenaire/ma-fiche">
          <Card className="transition-shadow hover:shadow-md">
            <p className="font-medium text-slate-900">Consulter ma fiche</p>
          </Card>
        </Link>
      </div>
    </div>
  );
}
