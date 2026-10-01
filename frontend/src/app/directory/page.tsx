'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Badge, Card, Input, Select } from '@/components/ui';
import type { DirectoryCabinet, Paginated, Specialite } from '@/types';

export default function DirectoryPage() {
  const [cabinets, setCabinets] = useState<DirectoryCabinet[]>([]);
  const [specialites, setSpecialites] = useState<Specialite[]>([]);
  const [loading, setLoading] = useState(true);

  const [q, setQ] = useState('');
  const [ville, setVille] = useState('');
  const [specialite, setSpecialite] = useState('');
  const [urgence, setUrgence] = useState(false);
  const [pmr, setPmr] = useState(false);

  useEffect(() => {
    api.get<Specialite[]>('/directory/specialites').then(setSpecialites).catch(() => {});
  }, []);

  useEffect(() => {
    const timeout = setTimeout(() => {
      const params = new URLSearchParams();
      if (q) params.set('q', q);
      if (ville) params.set('ville', ville);
      if (specialite) params.set('specialite', specialite);
      if (urgence) params.set('urgence', '1');
      if (pmr) params.set('pmr', '1');

      setLoading(true);
      api
        .get<Paginated<DirectoryCabinet>>(`/directory/cabinets?${params.toString()}`)
        .then((res) => setCabinets(res.data))
        .finally(() => setLoading(false));
    }, 300);
    return () => clearTimeout(timeout);
  }, [q, ville, specialite, urgence, pmr]);

  return (
    <div>
      <div className="mb-8 text-center">
        <h1 className="text-2xl font-bold text-slate-900">Trouvez un cabinet médical ou dentaire</h1>
        <p className="mt-1 text-sm text-slate-500">
          Recherchez parmi les cabinets partenaires DOKTA et prenez rendez-vous en ligne.
        </p>
      </div>

      <Card className="mb-6">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Input
            placeholder="Nom du cabinet ou du praticien…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            className="lg:col-span-2"
          />
          <Input placeholder="Ville ou quartier…" value={ville} onChange={(e) => setVille(e.target.value)} />
          <Select value={specialite} onChange={(e) => setSpecialite(e.target.value)}>
            <option value="">Toutes spécialités</option>
            {specialites.map((s) => (
              <option key={s.id} value={s.slug}>
                {s.nom}
              </option>
            ))}
          </Select>
        </div>
        <div className="mt-3 flex flex-wrap gap-4 text-sm text-slate-600">
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={urgence} onChange={(e) => setUrgence(e.target.checked)} />
            Accepte les urgences
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={pmr} onChange={(e) => setPmr(e.target.checked)} />
            Accessible PMR
          </label>
        </div>
      </Card>

      {loading && <p className="py-10 text-center text-slate-400">Chargement…</p>}

      {!loading && cabinets.length === 0 && (
        <p className="py-10 text-center text-slate-400">Aucun cabinet ne correspond à votre recherche.</p>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {cabinets.map((c) => (
          <Link key={c.id} href={`/directory/${c.id}`} className="block h-full">
            <Card className="h-full transition-shadow hover:shadow-md">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <h2 className="font-semibold text-slate-900">{c.nom}</h2>
                  <p className="text-sm text-slate-500">
                    {[c.quartier, c.ville].filter(Boolean).join(', ') || 'Localisation non renseignée'}
                  </p>
                </div>
                {Number(c.note_moyenne) > 0 && (
                  <span className="shrink-0 text-sm font-medium text-amber-600">
                    ★ {Number(c.note_moyenne).toFixed(1)}
                    <span className="text-slate-400"> ({c.nombre_avis})</span>
                  </span>
                )}
              </div>

              {c.description && <p className="mt-2 line-clamp-2 text-sm text-slate-600">{c.description}</p>}

              <div className="mt-3 flex flex-wrap gap-1.5">
                {c.specialites?.map((s) => (
                  <Badge key={s.id} tone="blue">
                    {s.nom}
                  </Badge>
                ))}
                {c.accepte_urgences && <Badge tone="red">Urgences</Badge>}
                {c.accessible_pmr && <Badge tone="green">PMR</Badge>}
              </div>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  );
}
