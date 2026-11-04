'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { JOURS, ORDRE_JOURS, formatFCFA } from '@/lib/site';
import { Badge, Button, Card, ErrorText, Field, Input, PageHeader, Textarea } from '@/components/ui';
import type { FicheCabinet, Horaire, ServiceCabinet, Specialite } from '@/types/directory';

interface Statistiques {
  periode_jours: number;
  totaux: { vues: number; clics_whatsapp: number; clics_telephone: number };
}

type Parametres = FicheCabinet & { visible_annuaire: boolean };

const STATUT: Record<FicheCabinet['statut_fiche'], { label: string; ton: 'green' | 'amber' | 'slate' }> = {
  verifiee: { label: 'Fiche vérifiée et publiée', ton: 'green' },
  en_attente: { label: 'En attente de validation par DOKTIS', ton: 'amber' },
  non_revendiquee: { label: 'Non revendiquée', ton: 'slate' },
};

export default function MaFichePage() {
  const { user } = useAuth();
  const router = useRouter();
  const [stats, setStats] = useState<Statistiques | null>(null);
  const [fiche, setFiche] = useState<Parametres | null>(null);
  const estAdmin = user?.role === 'administrateur';

  const charger = useCallback(async () => {
    const [s, f] = await Promise.all([
      api.get<Statistiques>('/fiche/statistiques').catch(() => null),
      estAdmin ? api.get<Parametres>('/parametres/cabinet').catch(() => null) : Promise.resolve(null),
    ]);
    setStats(s);
    setFiche(f);
  }, [estAdmin]);

  useEffect(() => {
    if (user?.role === 'super_admin') {
      router.replace('/pro/cabinets');
      return;
    }
    if (user) void charger();
  }, [user, router, charger]);

  if (!user || user.role === 'super_admin') return <p className="text-slate-400">Chargement…</p>;

  const statut = STATUT[fiche?.statut_fiche ?? user.cabinet?.statut_fiche ?? 'verifiee'];
  const publiee = fiche ? fiche.visible_annuaire && fiche.statut_fiche !== 'en_attente' : user.cabinet?.visible_annuaire;
  const slug = fiche?.slug ?? user.cabinet?.slug;

  return (
    <div className="flex max-w-4xl flex-col gap-6">
      <PageHeader
        title="Ma fiche annuaire"
        description="Ce que les patients voient sur DOKTIS, et les demandes qu'elle vous apporte."
        actions={
          publiee && slug ? (
            <Link href={`/cabinet/${slug}`} target="_blank" className="text-sm font-medium text-teal-700 hover:underline">
              Voir ma fiche publique ↗
            </Link>
          ) : undefined
        }
      />

      <Card className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <Badge tone={statut.ton}>{statut.label}</Badge>
          <span className="text-xs text-slate-500">30 derniers jours</span>
        </div>
        {fiche?.statut_fiche === 'en_attente' && (
          <p className="text-sm text-slate-600">
            L&apos;équipe DOKTIS vérifie votre inscription avant de publier la fiche. Complétez-la dès maintenant : elle sera en ligne dès la validation.
          </p>
        )}
        <div className="grid grid-cols-3 gap-3">
          {[
            ['Vues de la fiche', stats?.totaux.vues],
            ['Demandes WhatsApp', stats?.totaux.clics_whatsapp],
            ['Appels', stats?.totaux.clics_telephone],
          ].map(([label, valeur]) => (
            <div key={label as string} className="rounded-lg bg-slate-50 p-3">
              <p className="text-xs text-slate-500">{label}</p>
              <p className="text-2xl font-bold tabular-nums text-slate-900">{valeur ?? '–'}</p>
            </div>
          ))}
        </div>
        <p className="text-xs text-slate-500">
          Une demande WhatsApp correspond à un patient qui a ouvert WhatsApp depuis votre fiche. Le message arrive directement sur votre téléphone.
        </p>
      </Card>

      {!estAdmin && (
        <p className="text-sm text-slate-600">Seul l&apos;administrateur du cabinet peut modifier la fiche.</p>
      )}

      {estAdmin && fiche && (
        <>
          <FormulaireInformations fiche={fiche} onSaved={charger} />
          <FormulaireSpecialites fiche={fiche} onSaved={charger} />
          <FormulaireHoraires horaires={fiche.horaires} onSaved={charger} />
          <GestionServices />
        </>
      )}
    </div>
  );
}

function useEnvoi() {
  const [etat, setEtat] = useState<'repos' | 'envoi' | 'ok'>('repos');
  const [erreur, setErreur] = useState<string | null>(null);

  async function envoyer(action: () => Promise<unknown>) {
    setEtat('envoi');
    setErreur(null);
    try {
      await action();
      setEtat('ok');
      setTimeout(() => setEtat('repos'), 2500);
    } catch (err) {
      setErreur(err instanceof ApiError ? err.message : 'Enregistrement impossible.');
      setEtat('repos');
    }
  }

  return { etat, erreur, envoyer };
}

function BoutonEnregistrer({ etat }: { etat: 'repos' | 'envoi' | 'ok' }) {
  return (
    <div className="flex items-center gap-3">
      <Button type="submit" disabled={etat === 'envoi'}>{etat === 'envoi' ? 'Enregistrement…' : 'Enregistrer'}</Button>
      {etat === 'ok' && <span className="text-sm text-emerald-700">Enregistré</span>}
    </div>
  );
}

function FormulaireInformations({ fiche, onSaved }: { fiche: Parametres; onSaved: () => void }) {
  const [form, setForm] = useState({
    nom: fiche.nom ?? '',
    description: fiche.description ?? '',
    whatsapp: fiche.whatsapp ? `+${fiche.whatsapp}` : '',
    telephone: fiche.telephone ?? '',
    adresse: fiche.adresse ?? '',
    ville: fiche.ville ?? '',
    quartier: fiche.quartier ?? '',
    langues: (fiche.langues_parlees ?? []).join(', '),
    site_web: fiche.site_web ?? '',
    accepte_urgences: fiche.accepte_urgences,
    accessible_pmr: fiche.accessible_pmr,
  });
  const { etat, erreur, envoyer } = useEnvoi();
  const maj = (cle: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [cle]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value }));

  return (
    <Card>
      <h2 className="mb-4 text-base font-semibold text-slate-900">Informations publiques</h2>
      <form
        className="grid gap-4 sm:grid-cols-2"
        onSubmit={(e) => {
          e.preventDefault();
          void envoyer(async () => {
            const { langues, ...reste } = form;
            await api.patch('/parametres/cabinet', {
              ...reste,
              whatsapp: reste.whatsapp || null,
              site_web: reste.site_web || null,
              langues_parlees: langues.split(',').map((l) => l.trim()).filter(Boolean),
            });
            onSaved();
          });
        }}
      >
        <div className="sm:col-span-2"><ErrorText>{erreur}</ErrorText></div>
        <Field label="Nom du cabinet"><Input id="fiche-nom" required value={form.nom} onChange={maj('nom')} /></Field>
        <Field label="Numéro WhatsApp (format international)">
          <Input id="fiche-whatsapp" value={form.whatsapp} onChange={maj('whatsapp')} placeholder="+237 6XX XX XX XX" />
        </Field>
        <Field label="Téléphone"><Input id="fiche-telephone" value={form.telephone} onChange={maj('telephone')} /></Field>
        <Field label="Adresse"><Input id="fiche-adresse" value={form.adresse} onChange={maj('adresse')} placeholder="Rue, repère" /></Field>
        <Field label="Ville"><Input id="fiche-ville" value={form.ville} onChange={maj('ville')} /></Field>
        <Field label="Quartier"><Input id="fiche-quartier" value={form.quartier} onChange={maj('quartier')} /></Field>
        <Field label="Langues parlées (séparées par des virgules)">
          <Input id="fiche-langues" value={form.langues} onChange={maj('langues')} placeholder="Français, Anglais" />
        </Field>
        <Field label="Site web"><Input id="fiche-site" type="url" value={form.site_web} onChange={maj('site_web')} placeholder="https://" /></Field>
        <div className="sm:col-span-2">
          <Field label="Présentation">
            <Textarea id="fiche-description" rows={4} value={form.description} onChange={maj('description')} placeholder="Soins proposés, équipement, ce qui vous distingue…" />
          </Field>
        </div>
        <div className="flex flex-wrap gap-6 text-sm text-slate-700 sm:col-span-2">
          <label className="inline-flex items-center gap-2">
            <input id="fiche-urgences" type="checkbox" checked={form.accepte_urgences} onChange={maj('accepte_urgences')} className="h-4 w-4 accent-teal-600" />
            Accepte les urgences
          </label>
          <label className="inline-flex items-center gap-2">
            <input id="fiche-pmr" type="checkbox" checked={form.accessible_pmr} onChange={maj('accessible_pmr')} className="h-4 w-4 accent-teal-600" />
            Accessible aux personnes à mobilité réduite
          </label>
        </div>
        <div className="sm:col-span-2"><BoutonEnregistrer etat={etat} /></div>
      </form>
    </Card>
  );
}

function FormulaireSpecialites({ fiche, onSaved }: { fiche: Parametres; onSaved: () => void }) {
  const [disponibles, setDisponibles] = useState<Specialite[]>([]);
  const [choix, setChoix] = useState<number[]>(fiche.specialites.map((s) => s.id));
  const { etat, erreur, envoyer } = useEnvoi();

  useEffect(() => {
    api.get<Specialite[]>('/parametres/specialites-disponibles').then(setDisponibles).catch(() => setDisponibles([]));
  }, []);

  return (
    <Card>
      <h2 className="mb-1 text-base font-semibold text-slate-900">Spécialités</h2>
      <p className="mb-4 text-sm text-slate-500">Elles déterminent les pages de l&apos;annuaire où votre cabinet apparaît.</p>
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          void envoyer(async () => {
            await api.patch('/parametres/cabinet', { specialite_ids: choix });
            onSaved();
          });
        }}
      >
        <ErrorText>{erreur}</ErrorText>
        <div className="flex flex-wrap gap-2">
          {disponibles.map((s) => {
            const actif = choix.includes(s.id);
            return (
              <label key={s.id} className={`cursor-pointer rounded-full border px-3 py-1.5 text-sm ${actif ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-300 text-slate-700'}`}>
                <input
                  id={`specialite-${s.id}`}
                  type="checkbox"
                  className="sr-only"
                  checked={actif}
                  onChange={() => setChoix((c) => (actif ? c.filter((id) => id !== s.id) : [...c, s.id]))}
                />
                {s.nom}
              </label>
            );
          })}
        </div>
        <BoutonEnregistrer etat={etat} />
      </form>
    </Card>
  );
}

function FormulaireHoraires({ horaires, onSaved }: { horaires: Horaire[]; onSaved: () => void }) {
  const initial = ORDRE_JOURS.map((jour) => {
    const h = horaires.find((x) => x.jour_semaine === jour);
    return {
      jour_semaine: jour,
      heure_ouverture: h?.heure_ouverture?.slice(0, 5) ?? '',
      heure_fermeture: h?.heure_fermeture?.slice(0, 5) ?? '',
      ferme: h ? h.ferme : jour === 0,
    };
  });
  const [lignes, setLignes] = useState(initial);
  const { etat, erreur, envoyer } = useEnvoi();

  const maj = (i: number, valeurs: Partial<(typeof initial)[number]>) =>
    setLignes((l) => l.map((ligne, j) => (j === i ? { ...ligne, ...valeurs } : ligne)));

  return (
    <Card>
      <h2 className="mb-1 text-base font-semibold text-slate-900">Horaires d&apos;ouverture</h2>
      <p className="mb-4 text-sm text-slate-500">Utilisés pour le filtre « Ouvert maintenant ». Heure locale.</p>
      <form
        className="flex flex-col gap-3"
        onSubmit={(e) => {
          e.preventDefault();
          void envoyer(async () => {
            await api.put('/parametres/horaires', {
              horaires: lignes.map((l) => ({
                jour_semaine: l.jour_semaine,
                ferme: l.ferme,
                heure_ouverture: l.ferme || !l.heure_ouverture ? null : l.heure_ouverture,
                heure_fermeture: l.ferme || !l.heure_fermeture ? null : l.heure_fermeture,
              })),
            });
            onSaved();
          });
        }}
      >
        <ErrorText>{erreur}</ErrorText>
        {lignes.map((l, i) => (
          <div key={l.jour_semaine} className="grid grid-cols-[6rem_1fr] items-center gap-3 sm:grid-cols-[7rem_auto_1fr_1fr]">
            <span className="text-sm font-medium text-slate-700">{JOURS[l.jour_semaine]}</span>
            <label className="inline-flex items-center gap-2 text-sm text-slate-600">
              <input id={`ferme-${l.jour_semaine}`} type="checkbox" checked={l.ferme} onChange={(e) => maj(i, { ferme: e.target.checked })} className="h-4 w-4 accent-teal-600" />
              Fermé
            </label>
            {!l.ferme && (
              <>
                <Input id={`ouverture-${l.jour_semaine}`} type="time" aria-label={`Ouverture ${JOURS[l.jour_semaine]}`} value={l.heure_ouverture} onChange={(e) => maj(i, { heure_ouverture: e.target.value })} />
                <Input id={`fermeture-${l.jour_semaine}`} type="time" aria-label={`Fermeture ${JOURS[l.jour_semaine]}`} value={l.heure_fermeture} onChange={(e) => maj(i, { heure_fermeture: e.target.value })} />
              </>
            )}
          </div>
        ))}
        <BoutonEnregistrer etat={etat} />
      </form>
    </Card>
  );
}

function GestionServices() {
  const [services, setServices] = useState<ServiceCabinet[]>([]);
  const [form, setForm] = useState({ nom: '', prix_indicatif: '', duree_minutes: '' });
  const { etat, erreur, envoyer } = useEnvoi();

  const charger = useCallback(() => {
    api.get<ServiceCabinet[]>('/parametres/services').then(setServices).catch(() => setServices([]));
  }, []);
  useEffect(charger, [charger]);

  return (
    <Card>
      <h2 className="mb-1 text-base font-semibold text-slate-900">Soins et tarifs indicatifs</h2>
      <p className="mb-4 text-sm text-slate-500">Affichés sur la fiche et proposés comme motif dans la demande WhatsApp.</p>
      <ul className="mb-4 divide-y divide-slate-100 text-sm">
        {services.map((s) => (
          <li key={s.id} className="flex items-center justify-between gap-3 py-2">
            <span className="text-slate-800">
              {s.nom}
              {s.duree_minutes ? <span className="text-slate-500"> · {s.duree_minutes} min</span> : null}
            </span>
            <span className="flex items-center gap-3">
              <span className="tabular-nums text-slate-700">{formatFCFA(s.prix_indicatif) ?? 'Sur devis'}</span>
              <button
                type="button"
                onClick={() => void envoyer(async () => { await api.delete(`/parametres/services/${s.id}`); charger(); })}
                className="text-xs font-medium text-red-600 hover:underline"
              >
                Retirer
              </button>
            </span>
          </li>
        ))}
        {services.length === 0 && <li className="py-2 text-slate-400">Aucun soin renseigné.</li>}
      </ul>
      <form
        className="grid gap-3 sm:grid-cols-[2fr_1fr_1fr_auto] sm:items-end"
        onSubmit={(e) => {
          e.preventDefault();
          void envoyer(async () => {
            await api.post('/parametres/services', {
              nom: form.nom,
              prix_indicatif: form.prix_indicatif ? Number(form.prix_indicatif) : null,
              duree_minutes: form.duree_minutes ? Number(form.duree_minutes) : null,
            });
            setForm({ nom: '', prix_indicatif: '', duree_minutes: '' });
            charger();
          });
        }}
      >
        <div className="sm:col-span-4"><ErrorText>{erreur}</ErrorText></div>
        <Field label="Soin"><Input id="service-nom" required value={form.nom} onChange={(e) => setForm({ ...form, nom: e.target.value })} placeholder="Détartrage" /></Field>
        <Field label="Prix (FCFA)"><Input id="service-prix" type="number" min={0} value={form.prix_indicatif} onChange={(e) => setForm({ ...form, prix_indicatif: e.target.value })} /></Field>
        <Field label="Durée (min)"><Input id="service-duree" type="number" min={5} value={form.duree_minutes} onChange={(e) => setForm({ ...form, duree_minutes: e.target.value })} /></Field>
        <Button type="submit" variant="secondary" disabled={etat === 'envoi'}>Ajouter</Button>
      </form>
    </Card>
  );
}
