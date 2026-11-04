'use client';

import { useEffect, useMemo, useState } from 'react';
import { heureLisible, jourCourt, jourLong } from '@/lib/dates';
import { PUBLIC_API_URL } from '@/lib/site';
import { formaterNumero, lienWhatsApp, messageRendezVous, signalerEvenement } from '@/lib/whatsapp';
import type { CreneauPublic } from '@/types/directory';

interface Props {
  slug: string;
  cabinet: string;
  whatsapp: string | null;
  telephone: string | null;
  praticiens: string[];
  services: string[];
}

const MOTIFS_GENERAUX = ['Consultation', 'Contrôle ou suivi', 'Urgence', 'Soins'];
const MOMENTS = ['Dès que possible', 'Cette semaine', 'Un matin', 'Un après-midi', 'Samedi'];
const CHAMP = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm';

/** Créneaux libres publiés par le cabinet, chargés sans cache pour rester à jour. */
function useCreneaux(slug: string, actif: boolean) {
  const [creneaux, setCreneaux] = useState<CreneauPublic[] | null>(null);

  useEffect(() => {
    if (!actif) return;
    let annule = false;
    fetch(`${PUBLIC_API_URL}/directory/cabinets/${encodeURIComponent(slug)}/creneaux`, { headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : []))
      .then((data: CreneauPublic[]) => !annule && setCreneaux(data))
      .catch(() => !annule && setCreneaux([]));
    return () => {
      annule = true;
    };
  }, [slug, actif]);

  return creneaux;
}

export function DemandeWhatsApp({ slug, cabinet, whatsapp, telephone, praticiens, services }: Props) {
  const motifs = useMemo(() => Array.from(new Set([...MOTIFS_GENERAUX, ...services])), [services]);
  const [motif, setMotif] = useState(motifs[0]);
  const [moment, setMoment] = useState(MOMENTS[0]);
  const [praticien, setPraticien] = useState('');
  const [nom, setNom] = useState('');
  const [jourChoisi, setJourChoisi] = useState<string | null>(null);
  const [creneau, setCreneau] = useState<CreneauPublic | null>(null);
  const [autreMoment, setAutreMoment] = useState(false);

  const creneaux = useCreneaux(slug, Boolean(whatsapp));
  const parJour = useMemo(() => {
    const groupes = new Map<string, CreneauPublic[]>();
    for (const c of creneaux ?? []) groupes.set(c.date_locale, [...(groupes.get(c.date_locale) ?? []), c]);
    return groupes;
  }, [creneaux]);
  const jours = Array.from(parJour.keys());
  const jourActif = jourChoisi ?? jours[0] ?? null;
  const avecCreneaux = jours.length > 0 && !autreMoment;

  const message = messageRendezVous({
    cabinet,
    praticien: (avecCreneaux ? creneau?.praticien?.name : praticien) || undefined,
    motif,
    creneau: avecCreneaux && creneau ? `${jourLong(creneau.date_locale)} à ${heureLisible(creneau.heure_debut)}` : undefined,
    moment: avecCreneaux ? (creneau ? undefined : 'Dès que possible') : moment,
    nom: nom.trim() || undefined,
  });

  if (!whatsapp && !telephone) {
    return <p className="text-sm text-slate-600">Ce cabinet n&apos;a pas encore indiqué de numéro de contact.</p>;
  }

  return (
    <div className="flex flex-col gap-4">
      {whatsapp ? (
        <form
          className="flex flex-col gap-3"
          onSubmit={(e) => {
            e.preventDefault();
            signalerEvenement(slug, 'clic_whatsapp', { creneau_id: avecCreneaux ? creneau?.id : null });
            window.open(lienWhatsApp(whatsapp, message), '_blank', 'noopener,noreferrer');
          }}
        >
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">Motif</span>
            <select id="rdv-motif" value={motif} onChange={(e) => setMotif(e.target.value)} className={CHAMP}>
              {motifs.map((m) => <option key={m}>{m}</option>)}
            </select>
          </label>

          {avecCreneaux ? (
            <fieldset className="flex flex-col gap-2">
              <legend className="mb-1 text-sm font-medium text-slate-700">Créneaux disponibles</legend>
              <div className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1" role="tablist" aria-label="Jours disponibles">
                {jours.map((j) => (
                  <button
                    key={j}
                    type="button"
                    role="tab"
                    aria-selected={j === jourActif}
                    onClick={() => { setJourChoisi(j); setCreneau(null); }}
                    className={`shrink-0 rounded-lg border px-3 py-1.5 text-sm capitalize ${j === jourActif ? 'border-emerald-700 bg-emerald-50 font-semibold text-emerald-900' : 'border-slate-300 text-slate-700 hover:border-emerald-600'}`}
                  >
                    {jourCourt(j)}
                  </button>
                ))}
              </div>
              <div className="grid grid-cols-3 gap-1.5">
                {(jourActif ? parJour.get(jourActif) ?? [] : []).map((c) => {
                  const choisi = creneau?.id === c.id;
                  return (
                    <button
                      key={c.id}
                      type="button"
                      aria-pressed={choisi}
                      onClick={() => setCreneau(choisi ? null : c)}
                      title={c.praticien ? `Avec ${c.praticien.name}` : undefined}
                      className={`rounded-lg border px-2 py-2 text-sm tabular-nums ${choisi ? 'border-emerald-700 bg-emerald-700 font-semibold text-white' : 'border-slate-300 text-slate-800 hover:border-emerald-600'}`}
                    >
                      {heureLisible(c.heure_debut)}
                    </button>
                  );
                })}
              </div>
              {creneau?.praticien && <p className="text-xs text-slate-600">Avec {creneau.praticien.name}</p>}
              <button type="button" onClick={() => { setAutreMoment(true); setCreneau(null); }} className="self-start text-xs font-medium text-teal-800 hover:underline">
                Aucun créneau ne convient ? Proposer un autre moment
              </button>
            </fieldset>
          ) : (
            <>
              {praticiens.length > 1 && (
                <label className="flex flex-col gap-1 text-sm">
                  <span className="font-medium text-slate-700">Praticien</span>
                  <select id="rdv-praticien" value={praticien} onChange={(e) => setPraticien(e.target.value)} className={CHAMP}>
                    <option value="">Peu importe</option>
                    {praticiens.map((p) => <option key={p}>{p}</option>)}
                  </select>
                </label>
              )}
              <label className="flex flex-col gap-1 text-sm">
                <span className="font-medium text-slate-700">Moment souhaité</span>
                <select id="rdv-moment" value={moment} onChange={(e) => setMoment(e.target.value)} className={CHAMP}>
                  {MOMENTS.map((m) => <option key={m}>{m}</option>)}
                </select>
              </label>
              {jours.length > 0 && (
                <button type="button" onClick={() => setAutreMoment(false)} className="self-start text-xs font-medium text-teal-800 hover:underline">
                  Revenir aux créneaux disponibles
                </button>
              )}
            </>
          )}

          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">Votre nom <span className="font-normal text-slate-500">(facultatif)</span></span>
            <input id="rdv-nom" value={nom} onChange={(e) => setNom(e.target.value)} maxLength={80} autoComplete="name" className={CHAMP} />
          </label>

          <div className="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            <p className="mb-1 font-medium text-slate-700">Message qui sera envoyé</p>
            <p className="whitespace-pre-line">{message}</p>
          </div>

          <button type="submit" className="rounded-lg bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
            Demander un rendez-vous sur WhatsApp
          </button>
          <p className="text-xs text-slate-500">
            WhatsApp s&apos;ouvre avec ce message ; vous l&apos;envoyez vous-même. {avecCreneaux ? 'Le créneau est réservé quand le cabinet vous répond pour confirmer.' : 'Le cabinet vous répond pour confirmer.'}{' '}
            DOKTIS n&apos;enregistre ni votre nom ni votre message. N&apos;indiquez pas de détails médicaux.
          </p>
        </form>
      ) : (
        <p className="text-sm text-slate-600">Ce cabinet ne prend pas encore les demandes sur WhatsApp. Appelez-le directement.</p>
      )}

      {telephone && (
        <div className="border-t border-slate-100 pt-3 text-sm">
          <p className="text-slate-500">Par téléphone</p>
          <a
            href={`tel:${telephone.replace(/[^\d+]/g, '')}`}
            onClick={() => signalerEvenement(slug, 'clic_telephone')}
            className="font-semibold text-teal-800 hover:underline"
          >
            {telephone}
          </a>
        </div>
      )}
      {whatsapp && !telephone && (
        <p className="border-t border-slate-100 pt-3 text-sm text-slate-500">WhatsApp : <span className="font-medium text-slate-700">{formaterNumero(whatsapp)}</span></p>
      )}
    </div>
  );
}

/**
 * Compte une vue de fiche, une seule fois par affichage. L'origine est lue
 * dans l'adresse : ?source=qr (affiche, QR code) ou ?source=lien (lien partagé).
 */
export function CompteurVue({ slug }: { slug: string }) {
  useEffect(() => {
    const source = new URLSearchParams(window.location.search).get('source');
    signalerEvenement(slug, 'vue', { source: source === 'qr' || source === 'lien' ? source : null });
  }, [slug]);
  return null;
}

/**
 * Bouton fixe en bas d'écran sur mobile, qui mène au formulaire de demande.
 * Il disparaît dès que le formulaire est visible pour ne pas le recouvrir.
 */
export function BarreRendezVousMobile({ cible, libelle }: { cible: string; libelle: string }) {
  const [formulaireVisible, setFormulaireVisible] = useState(false);

  useEffect(() => {
    const element = document.getElementById(cible);
    if (!element || typeof IntersectionObserver === 'undefined') return;
    const observateur = new IntersectionObserver(([entree]) => setFormulaireVisible(entree.isIntersecting), { threshold: 0.15 });
    observateur.observe(element);
    return () => observateur.disconnect();
  }, [cible]);

  return (
    <>
      <div
        hidden={formulaireVisible}
        className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden"
      >
        <a href={`#${cible}`} className="block rounded-lg bg-emerald-700 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-emerald-800">
          {libelle}
        </a>
      </div>
      <div className="h-16 lg:hidden" aria-hidden />
    </>
  );
}
