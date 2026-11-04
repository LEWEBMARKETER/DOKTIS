'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { ajouterJours, aujourdhuiLocal, heureLisible, jourLong } from '@/lib/dates';
import { Button, Card, ErrorText, Field, Input, PageHeader, Select } from '@/components/ui';
import type { User } from '@/types';
import type { CreneauCabinet } from '@/types/directory';

const JOURS_SEMAINE = [
  { valeur: 1, label: 'Lun' }, { valeur: 2, label: 'Mar' }, { valeur: 3, label: 'Mer' }, { valeur: 4, label: 'Jeu' },
  { valeur: 5, label: 'Ven' }, { valeur: 6, label: 'Sam' }, { valeur: 0, label: 'Dim' },
];
const PERIODE_JOURS = 14;

export default function CreneauxPage() {
  const [debut, setDebut] = useState(aujourdhuiLocal());
  const [creneaux, setCreneaux] = useState<CreneauCabinet[]>([]);
  const [praticiens, setPraticiens] = useState<User[]>([]);
  const [erreur, setErreur] = useState<string | null>(null);

  const fin = ajouterJours(debut, PERIODE_JOURS - 1);

  const charger = useCallback(() => {
    api
      .get<CreneauCabinet[]>(`/creneaux?du=${debut}&au=${fin}`)
      .then(setCreneaux)
      .catch(() => setErreur('Impossible de charger les créneaux.'));
  }, [debut, fin]);

  useEffect(charger, [charger]);
  useEffect(() => {
    api.get<User[]>('/users').then((u) => setPraticiens(u.filter((x) => ['administrateur', 'medecin'].includes(x.role) && x.actif))).catch(() => setPraticiens([]));
  }, []);

  const parJour = useMemo(() => {
    const groupes = new Map<string, CreneauCabinet[]>();
    for (const c of creneaux) groupes.set(c.date_locale, [...(groupes.get(c.date_locale) ?? []), c]);
    return groupes;
  }, [creneaux]);

  async function agir(action: () => Promise<unknown>) {
    setErreur(null);
    try {
      await action();
      charger();
    } catch (err) {
      setErreur(err instanceof ApiError ? err.message : 'Action impossible.');
    }
  }

  return (
    <div className="flex max-w-5xl flex-col gap-6">
      <PageHeader
        title="Mes créneaux"
        description="Les patients voient vos créneaux libres sur votre fiche et en choisissent un avant de vous écrire sur WhatsApp. Marquez un créneau « pris » dès que vous confirmez le rendez-vous."
      />

      <GenerateurCreneaux praticiens={praticiens} onGenere={charger} />

      <Card className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-base font-semibold text-slate-900">
            Du {jourLong(debut)} au {jourLong(fin)}
          </h2>
          <div className="flex gap-2">
            <Button variant="secondary" disabled={debut <= aujourdhuiLocal()} onClick={() => setDebut(ajouterJours(debut, -PERIODE_JOURS))}>← Précédent</Button>
            <Button variant="secondary" onClick={() => setDebut(ajouterJours(debut, PERIODE_JOURS))}>Suivant →</Button>
          </div>
        </div>
        <ErrorText>{erreur}</ErrorText>
        <p className="text-xs text-slate-500">Cliquez sur un créneau pour le passer de « libre » à « pris » et inversement.</p>

        {parJour.size === 0 && <p className="text-sm text-slate-400">Aucun créneau ouvert sur cette période.</p>}

        <div className="flex flex-col divide-y divide-slate-100">
          {Array.from(parJour.entries()).map(([jour, liste]) => {
            const libres = liste.filter((c) => c.statut === 'libre').length;
            return (
              <section key={jour} className="flex flex-col gap-2 py-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <h3 className="text-sm font-semibold capitalize text-slate-800">{jourLong(jour)}</h3>
                  <div className="flex items-center gap-3 text-xs text-slate-500">
                    <span>{libres} libre{libres > 1 ? 's' : ''} · {liste.length - libres} pris</span>
                    {libres > 0 && (
                      <button type="button" onClick={() => agir(() => api.delete(`/creneaux/jour/${jour}`))} className="font-medium text-red-600 hover:underline">
                        Retirer les créneaux libres
                      </button>
                    )}
                  </div>
                </div>
                <ul className="flex flex-wrap gap-2">
                  {liste.map((c) => {
                    const pris = c.statut === 'pris';
                    return (
                      <li key={c.id} className="flex items-stretch overflow-hidden rounded-lg border border-slate-200 text-sm">
                        <button
                          type="button"
                          onClick={() => agir(() => api.patch(`/creneaux/${c.id}`, { statut: pris ? 'libre' : 'pris' }))}
                          className={`flex items-center gap-2 px-3 py-1.5 tabular-nums ${pris ? 'bg-slate-100 text-slate-500 line-through' : 'bg-emerald-50 text-emerald-900 hover:bg-emerald-100'}`}
                          title={pris ? 'Remettre en libre' : 'Marquer comme pris'}
                        >
                          {heureLisible(c.heure_debut)}
                          {c.praticien && <span className="text-xs no-underline opacity-70">{c.praticien.name.replace(/^Dr\.? /, '')}</span>}
                          {c.demandes > 0 && !pris && (
                            <span className="rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800">
                              {c.demandes} demande{c.demandes > 1 ? 's' : ''}
                            </span>
                          )}
                        </button>
                        <button
                          type="button"
                          onClick={() => agir(() => api.delete(`/creneaux/${c.id}`))}
                          className="border-l border-slate-200 px-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                          aria-label={`Supprimer le créneau de ${heureLisible(c.heure_debut)}`}
                        >
                          ×
                        </button>
                      </li>
                    );
                  })}
                </ul>
              </section>
            );
          })}
        </div>
      </Card>
    </div>
  );
}

function GenerateurCreneaux({ praticiens, onGenere }: { praticiens: User[]; onGenere: () => void }) {
  const [form, setForm] = useState({
    date_debut: aujourdhuiLocal(),
    date_fin: ajouterJours(aujourdhuiLocal(), 6),
    jours_semaine: [1, 2, 3, 4, 5],
    heure_debut: '08:00',
    heure_fin: '12:00',
    duree_minutes: '30',
    praticien_id: '',
  });
  const [resultat, setResultat] = useState<string | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);
  const [envoi, setEnvoi] = useState(false);

  async function generer(e: React.FormEvent) {
    e.preventDefault();
    setEnvoi(true);
    setErreur(null);
    setResultat(null);
    try {
      const r = await api.post<{ crees: number; ignores: number }>('/creneaux/generer', {
        ...form,
        duree_minutes: Number(form.duree_minutes),
        praticien_id: form.praticien_id ? Number(form.praticien_id) : null,
      });
      setResultat(
        `${r.crees} créneau${r.crees > 1 ? 'x' : ''} ouvert${r.crees > 1 ? 's' : ''}` +
          (r.ignores ? ` (${r.ignores} ignoré${r.ignores > 1 ? 's' : ''} : déjà passés ou déjà ouverts)` : '') + '.',
      );
      onGenere();
    } catch (err) {
      setErreur(err instanceof ApiError ? err.message : 'Impossible d’ouvrir ces créneaux.');
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <Card>
      <h2 className="mb-4 text-base font-semibold text-slate-900">Ouvrir des créneaux</h2>
      <form onSubmit={generer} className="flex flex-col gap-4">
        <ErrorText>{erreur}</ErrorText>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Field label="Du"><Input id="gen-du" type="date" required value={form.date_debut} min={aujourdhuiLocal()} onChange={(e) => setForm({ ...form, date_debut: e.target.value })} /></Field>
          <Field label="Au"><Input id="gen-au" type="date" required value={form.date_fin} min={form.date_debut} onChange={(e) => setForm({ ...form, date_fin: e.target.value })} /></Field>
          <Field label="De"><Input id="gen-de" type="time" required value={form.heure_debut} onChange={(e) => setForm({ ...form, heure_debut: e.target.value })} /></Field>
          <Field label="À"><Input id="gen-a" type="time" required value={form.heure_fin} onChange={(e) => setForm({ ...form, heure_fin: e.target.value })} /></Field>
          <Field label="Durée d'un rendez-vous">
            <Select id="gen-duree" value={form.duree_minutes} onChange={(e) => setForm({ ...form, duree_minutes: e.target.value })}>
              {['15', '20', '30', '45', '60'].map((d) => <option key={d} value={d}>{d} minutes</option>)}
            </Select>
          </Field>
          {praticiens.length > 1 && (
            <Field label="Praticien">
              <Select id="gen-praticien" value={form.praticien_id} onChange={(e) => setForm({ ...form, praticien_id: e.target.value })}>
                <option value="">Tout le cabinet</option>
                {praticiens.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </Select>
            </Field>
          )}
        </div>
        <fieldset className="flex flex-col gap-2">
          <legend className="text-sm font-medium text-slate-700">Jours</legend>
          <div className="flex flex-wrap gap-2">
            {JOURS_SEMAINE.map((j) => {
              const actif = form.jours_semaine.includes(j.valeur);
              return (
                <label key={j.valeur} className={`cursor-pointer rounded-lg border px-3 py-1.5 text-sm ${actif ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-300 text-slate-600'}`}>
                  <input
                    id={`gen-jour-${j.valeur}`}
                    type="checkbox"
                    className="sr-only"
                    checked={actif}
                    onChange={() => setForm({ ...form, jours_semaine: actif ? form.jours_semaine.filter((v) => v !== j.valeur) : [...form.jours_semaine, j.valeur] })}
                  />
                  {j.label}
                </label>
              );
            })}
          </div>
        </fieldset>
        <div className="flex flex-wrap items-center gap-3">
          <Button type="submit" disabled={envoi || form.jours_semaine.length === 0}>{envoi ? 'Ouverture…' : 'Ouvrir les créneaux'}</Button>
          {resultat && <span className="text-sm text-emerald-700">{resultat}</span>}
        </div>
      </form>
    </Card>
  );
}
