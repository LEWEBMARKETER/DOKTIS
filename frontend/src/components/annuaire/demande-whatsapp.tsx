'use client';

import { useEffect, useMemo, useState } from 'react';
import { formaterNumero, lienWhatsApp, messageRendezVous, signalerEvenement } from '@/lib/whatsapp';

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

export function DemandeWhatsApp({ slug, cabinet, whatsapp, telephone, praticiens, services }: Props) {
  const motifs = useMemo(() => Array.from(new Set([...MOTIFS_GENERAUX, ...services])), [services]);
  const [motif, setMotif] = useState(motifs[0]);
  const [moment, setMoment] = useState(MOMENTS[0]);
  const [praticien, setPraticien] = useState('');
  const [nom, setNom] = useState('');

  const message = messageRendezVous({ cabinet, praticien: praticien || undefined, motif, moment, nom: nom.trim() || undefined });

  if (!whatsapp && !telephone) {
    return (
      <p className="text-sm text-slate-600">
        Ce cabinet n&apos;a pas encore indiqué de numéro de contact.
      </p>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {whatsapp ? (
        <form
          className="flex flex-col gap-3"
          onSubmit={(e) => {
            e.preventDefault();
            signalerEvenement(slug, 'clic_whatsapp');
            window.open(lienWhatsApp(whatsapp, message), '_blank', 'noopener,noreferrer');
          }}
        >
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">Motif</span>
            <select id="rdv-motif" value={motif} onChange={(e) => setMotif(e.target.value)} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
              {motifs.map((m) => <option key={m}>{m}</option>)}
            </select>
          </label>
          {praticiens.length > 1 && (
            <label className="flex flex-col gap-1 text-sm">
              <span className="font-medium text-slate-700">Praticien</span>
              <select id="rdv-praticien" value={praticien} onChange={(e) => setPraticien(e.target.value)} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Peu importe</option>
                {praticiens.map((p) => <option key={p}>{p}</option>)}
              </select>
            </label>
          )}
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">Moment souhaité</span>
            <select id="rdv-moment" value={moment} onChange={(e) => setMoment(e.target.value)} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
              {MOMENTS.map((m) => <option key={m}>{m}</option>)}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-slate-700">Votre nom <span className="font-normal text-slate-500">(facultatif)</span></span>
            <input id="rdv-nom" value={nom} onChange={(e) => setNom(e.target.value)} maxLength={80} autoComplete="name" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
          </label>

          <div className="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            <p className="mb-1 font-medium text-slate-700">Message qui sera envoyé</p>
            <p className="whitespace-pre-line">{message}</p>
          </div>

          <button type="submit" className="rounded-lg bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
            Demander un rendez-vous sur WhatsApp
          </button>
          <p className="text-xs text-slate-500">
            WhatsApp s&apos;ouvre avec ce message ; vous l&apos;envoyez vous-même et le cabinet vous répond pour confirmer.
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

/** Compte une vue de fiche, une seule fois par affichage. */
export function CompteurVue({ slug }: { slug: string }) {
  useEffect(() => {
    signalerEvenement(slug, 'vue');
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
