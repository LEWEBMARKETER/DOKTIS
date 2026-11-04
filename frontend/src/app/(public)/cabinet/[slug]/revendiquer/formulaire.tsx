'use client';

import { useState } from 'react';
import { PUBLIC_API_URL } from '@/lib/site';

const CHAMP = 'rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600';

export function FormulaireRevendication({ slug }: { slug: string }) {
  const [form, setForm] = useState({ nom: '', fonction: '', email: '', telephone: '', password: '', message: '' });
  const [etat, setEtat] = useState<'saisie' | 'envoi' | 'envoye'>('saisie');
  const [erreur, setErreur] = useState<string | null>(null);
  const [erreursChamps, setErreursChamps] = useState<Record<string, string[]>>({});

  const maj = (cle: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [cle]: e.target.value }));

  async function envoyer(e: React.FormEvent) {
    e.preventDefault();
    setEtat('envoi');
    setErreur(null);
    setErreursChamps({});
    try {
      const res = await fetch(`${PUBLIC_API_URL}/directory/cabinets/${encodeURIComponent(slug)}/revendications`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(form),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok) {
        setErreur(data?.message ?? 'La demande n’a pas pu être envoyée. Réessayez dans un instant.');
        setErreursChamps(data?.errors ?? {});
        setEtat('saisie');
        return;
      }
      setEtat('envoye');
    } catch {
      setErreur('Connexion impossible. Vérifiez votre réseau et réessayez.');
      setEtat('saisie');
    }
  }

  if (etat === 'envoye') {
    return (
      <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
        <p className="font-semibold">Demande envoyée.</p>
        <p>
          L&apos;équipe DOKTIS va appeler le cabinet pour vérifier que vous en faites partie. Dès validation, vous pourrez vous connecter à
          l&apos;espace praticien avec l&apos;e-mail et le mot de passe choisis.
        </p>
      </div>
    );
  }

  const aide = (cle: string) => erreursChamps[cle]?.[0] && <span className="text-xs text-red-700">{erreursChamps[cle][0]}</span>;

  return (
    <form onSubmit={envoyer} className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5">
      {erreur && <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{erreur}</p>}
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-700">Nom complet</span>
          <input id="rev-nom" required value={form.nom} onChange={maj('nom')} className={CHAMP} autoComplete="name" />
          {aide('nom')}
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-700">Fonction</span>
          <input id="rev-fonction" value={form.fonction} onChange={maj('fonction')} placeholder="ex. Chirurgien-dentiste, gérant" className={CHAMP} />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-700">E-mail</span>
          <input id="rev-email" type="email" required value={form.email} onChange={maj('email')} className={CHAMP} autoComplete="email" />
          {aide('email')}
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-700">Téléphone</span>
          <input id="rev-telephone" type="tel" required value={form.telephone} onChange={maj('telephone')} placeholder="+237 6XX XX XX XX" className={CHAMP} autoComplete="tel" />
          {aide('telephone')}
        </label>
        <label className="flex flex-col gap-1 text-sm sm:col-span-2">
          <span className="font-medium text-slate-700">Mot de passe de votre futur compte</span>
          <input id="rev-password" type="password" required minLength={8} value={form.password} onChange={maj('password')} className={CHAMP} autoComplete="new-password" />
          <span className="text-xs text-slate-500">8 caractères minimum.</span>
          {aide('password')}
        </label>
        <label className="flex flex-col gap-1 text-sm sm:col-span-2">
          <span className="font-medium text-slate-700">Message <span className="font-normal text-slate-500">(facultatif)</span></span>
          <textarea id="rev-message" rows={3} value={form.message} onChange={maj('message')} placeholder="Meilleur moment pour vous appeler, corrections à apporter…" className={CHAMP} />
        </label>
      </div>
      <button type="submit" disabled={etat === 'envoi'} className="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:bg-teal-300">
        {etat === 'envoi' ? 'Envoi…' : 'Envoyer ma demande'}
      </button>
    </form>
  );
}
