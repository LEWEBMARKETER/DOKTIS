'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageHeader, Select } from '@/components/ui';

interface Stats {
  periode: { debut: string; fin: string };
  totaux: {
    visites: number;
    visiteurs_uniques_estimes: number;
    clics_telephone: number;
    clics_whatsapp: number;
    clics_itineraire: number;
    demandes_rendez_vous: number;
    demandes_confirmees: number;
    demandes_refusees_ou_annulees: number;
    taux_conversion_visite_vers_demande: number;
  };
  serie_visites: { date: string; visites: number; visiteurs_uniques: number }[];
}

export default function PartenaireStatistiquesPage() {
  const [periode, setPeriode] = useState('30');
  const [stats, setStats] = useState<Stats | null>(null);

  useEffect(() => {
    api.get<Stats>(`/partenaire/statistiques?periode=${periode}`).then(setStats);
  }, [periode]);

  const maxVisites = stats ? Math.max(1, ...stats.serie_visites.map((s) => s.visites)) : 1;

  return (
    <div>
      <PageHeader
        title="Statistiques"
        description="Visites, clics et demandes de rendez-vous de votre fiche. Un clic est un clic, pas une preuve d'appel ou de visite effectivement réalisée."
        actions={
          <Select value={periode} onChange={(e) => setPeriode(e.target.value)} className="w-40">
            <option value="7">7 derniers jours</option>
            <option value="30">30 derniers jours</option>
            <option value="90">90 derniers jours</option>
          </Select>
        }
      />

      {stats && (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card><p className="text-sm text-slate-500">Visites</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.visites}</p></Card>
            <Card><p className="text-sm text-slate-500">Visiteurs uniques (estimés)</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.visiteurs_uniques_estimes}</p></Card>
            <Card><p className="text-sm text-slate-500">Demandes de rendez-vous</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.demandes_rendez_vous}</p></Card>
            <Card><p className="text-sm text-slate-500">Taux de conversion</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{(stats.totaux.taux_conversion_visite_vers_demande * 100).toFixed(1)}%</p></Card>
            <Card><p className="text-sm text-slate-500">Clics téléphone</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.clics_telephone}</p></Card>
            <Card><p className="text-sm text-slate-500">Clics WhatsApp</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.clics_whatsapp}</p></Card>
            <Card><p className="text-sm text-slate-500">Clics itinéraire</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.clics_itineraire}</p></Card>
            <Card><p className="text-sm text-slate-500">Refusées/annulées</p><p className="mt-1 text-2xl font-bold text-doktis-petrol">{stats.totaux.demandes_refusees_ou_annulees}</p></Card>
          </div>

          <Card className="mt-6">
            <h2 className="mb-3 font-semibold text-slate-900">Évolution des visites</h2>
            {stats.serie_visites.length === 0 ? (
              <p className="text-sm text-slate-400">Aucune visite enregistrée sur cette période.</p>
            ) : (
              <div className="flex items-end gap-1 overflow-x-auto" style={{ height: 120 }}>
                {stats.serie_visites.map((s) => (
                  <div key={s.date} className="flex flex-col items-center gap-1" title={`${s.date} : ${s.visites} visite(s)`}>
                    <div
                      className="w-3 rounded-t bg-doktis-turquoise"
                      style={{ height: `${Math.max(4, (s.visites / maxVisites) * 100)}px` }}
                    />
                  </div>
                ))}
              </div>
            )}
          </Card>
        </>
      )}
    </div>
  );
}
