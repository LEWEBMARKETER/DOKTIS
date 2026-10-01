'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api, ApiError } from '@/lib/api';
import { Badge, Card } from '@/components/ui';
import type { DirectoryCabinet } from '@/types';

const JOURS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

function formatHeure(heure: string | null): string {
  return heure ? heure.slice(0, 5) : '';
}

function formatMontant(montant: string | null): string {
  if (!montant) return '';
  return `${Number(montant).toLocaleString('fr-FR')} FCFA`;
}

export default function CabinetDirectoryPage() {
  const params = useParams<{ id: string }>();
  const [cabinet, setCabinet] = useState<DirectoryCabinet | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<DirectoryCabinet>(`/directory/cabinets/${params.id}`)
      .then(setCabinet)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Cabinet introuvable.'));
  }, [params.id]);

  if (error) {
    return (
      <div className="text-center text-slate-500">
        <p>{error}</p>
        <Link href="/directory" className="mt-3 inline-block text-teal-700 hover:underline">
          ← Retour à l&apos;annuaire
        </Link>
      </div>
    );
  }

  if (!cabinet) return <p className="py-10 text-center text-slate-400">Chargement…</p>;

  return (
    <div>
      <Link href="/directory" className="text-sm text-teal-700 hover:underline">
        ← Retour à l&apos;annuaire
      </Link>

      <div className="mt-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{cabinet.nom}</h1>
          <p className="text-slate-500">{[cabinet.quartier, cabinet.ville].filter(Boolean).join(', ')}</p>
        </div>
        <div className="flex items-center gap-4">
          {Number(cabinet.note_moyenne) > 0 && (
            <span className="text-lg font-medium text-amber-600">
              ★ {Number(cabinet.note_moyenne).toFixed(1)}
              <span className="text-sm text-slate-400"> ({cabinet.nombre_avis} avis)</span>
            </span>
          )}
          <Link
            href={`/patient/rendez-vous/nouveau?cabinet=${cabinet.id}`}
            className="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
          >
            Prendre rendez-vous
          </Link>
        </div>
      </div>

      <div className="mt-2 flex flex-wrap gap-1.5">
        {cabinet.specialites?.map((s) => (
          <Badge key={s.id} tone="blue">
            {s.nom}
          </Badge>
        ))}
        {cabinet.accepte_urgences && <Badge tone="red">Urgences</Badge>}
        {cabinet.accessible_pmr && <Badge tone="green">Accessible PMR</Badge>}
      </div>

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          {cabinet.description && (
            <Card>
              <h2 className="mb-2 font-semibold text-slate-900">À propos</h2>
              <p className="text-sm text-slate-600">{cabinet.description}</p>
              {cabinet.langues_parlees && cabinet.langues_parlees.length > 0 && (
                <p className="mt-3 text-sm text-slate-500">
                  Langues parlées : {cabinet.langues_parlees.join(', ')}
                </p>
              )}
            </Card>
          )}

          {!!cabinet.services?.length && (
            <Card>
              <h2 className="mb-3 font-semibold text-slate-900">Services &amp; tarifs indicatifs</h2>
              <ul className="divide-y divide-slate-100">
                {cabinet.services.map((s) => (
                  <li key={s.id} className="flex items-center justify-between py-2 text-sm">
                    <div>
                      <p className="font-medium text-slate-700">{s.nom}</p>
                      {s.description && <p className="text-slate-500">{s.description}</p>}
                    </div>
                    <p className="shrink-0 text-slate-600">{formatMontant(s.prix_indicatif)}</p>
                  </li>
                ))}
              </ul>
            </Card>
          )}

          {!!cabinet.users?.length && (
            <Card>
              <h2 className="mb-3 font-semibold text-slate-900">Praticiens</h2>
              <ul className="space-y-2">
                {cabinet.users.map((u) => (
                  <li key={u.id} className="text-sm">
                    <span className="font-medium text-slate-700">{u.name}</span>
                    {u.specialite && <span className="text-slate-500"> — {u.specialite}</span>}
                  </li>
                ))}
              </ul>
            </Card>
          )}

          <Card>
            <h2 className="mb-3 font-semibold text-slate-900">Avis des patients</h2>
            {!cabinet.avis?.length && <p className="text-sm text-slate-400">Aucun avis pour le moment.</p>}
            <ul className="space-y-4">
              {cabinet.avis?.map((a) => (
                <li key={a.id} className="border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-slate-700">
                      {a.auteur ? `${a.auteur.prenom} ${a.auteur.nom}` : 'Patient'}
                    </span>
                    <span className="text-sm text-amber-600">{'★'.repeat(a.note)}</span>
                  </div>
                  {a.commentaire && <p className="mt-1 text-sm text-slate-600">{a.commentaire}</p>}
                  {a.reponse_cabinet && (
                    <p className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                      <span className="font-medium">Réponse du cabinet : </span>
                      {a.reponse_cabinet}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <h2 className="mb-3 font-semibold text-slate-900">Contact</h2>
            <ul className="space-y-1.5 text-sm text-slate-600">
              {cabinet.adresse && <li>{cabinet.adresse}</li>}
              {cabinet.telephone && <li>{cabinet.telephone}</li>}
              {cabinet.email && <li>{cabinet.email}</li>}
              {cabinet.site_web && (
                <li>
                  <a href={cabinet.site_web} target="_blank" rel="noreferrer" className="text-teal-700 hover:underline">
                    Site web
                  </a>
                </li>
              )}
            </ul>
          </Card>

          {!!cabinet.horaires?.length && (
            <Card>
              <h2 className="mb-3 font-semibold text-slate-900">Horaires</h2>
              <ul className="space-y-1 text-sm">
                {[...cabinet.horaires]
                  .sort((a, b) => a.jour_semaine - b.jour_semaine)
                  .map((h) => (
                    <li key={h.id} className="flex justify-between text-slate-600">
                      <span>{JOURS[h.jour_semaine]}</span>
                      <span>{h.ferme ? 'Fermé' : `${formatHeure(h.heure_ouverture)} – ${formatHeure(h.heure_fermeture)}`}</span>
                    </li>
                  ))}
              </ul>
            </Card>
          )}

          {!!cabinet.mutuelles?.length && (
            <Card>
              <h2 className="mb-3 font-semibold text-slate-900">Mutuelles acceptées</h2>
              <div className="flex flex-wrap gap-1.5">
                {cabinet.mutuelles.map((m) => (
                  <Badge key={m.id}>{m.nom}</Badge>
                ))}
              </div>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}
