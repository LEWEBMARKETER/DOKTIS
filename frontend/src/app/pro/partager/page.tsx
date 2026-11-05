'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import QRCode from 'qrcode';
import { useAuth } from '@/lib/auth-context';
import { SITE_NAME, SITE_URL } from '@/lib/site';
import { Button, Card, PageHeader } from '@/components/ui';

const IDEES = [
  'Dans votre fiche Google (lien « Prendre rendez-vous »)',
  'Sur votre page Facebook et dans votre statut WhatsApp',
  'Sur vos cartes de visite et vos ordonnances',
  'Sur la porte et dans la salle d’attente du cabinet',
];

export default function PartagerPage() {
  const { user } = useAuth();
  const cabinet = user?.cabinet;
  const publiee = Boolean(cabinet?.visible_annuaire && cabinet.statut_fiche !== 'en_attente');
  // Adresse publique : celle de la configuration, sinon celle de la page ouverte.
  const base = SITE_URL.includes('localhost') && typeof window !== 'undefined' ? window.location.origin : SITE_URL;
  const lien = cabinet ? `${base}/cabinet/${cabinet.slug}?source=lien` : '';
  const lienQr = cabinet ? `${base}/cabinet/${cabinet.slug}?source=qr` : '';
  const [qr, setQr] = useState<string | null>(null);
  const [copie, setCopie] = useState(false);

  useEffect(() => {
    if (!lienQr) return;
    QRCode.toDataURL(lienQr, { width: 720, margin: 1, errorCorrectionLevel: 'M', color: { dark: '#0f3d36', light: '#ffffff' } })
      .then(setQr)
      .catch(() => setQr(null));
  }, [lienQr]);

  async function copier() {
    try {
      await navigator.clipboard.writeText(lien);
      setCopie(true);
      setTimeout(() => setCopie(false), 2000);
    } catch {
      setCopie(false);
    }
  }

  if (!cabinet) return <p className="text-slate-400">Chargement…</p>;

  return (
    <div className="flex max-w-4xl flex-col gap-6">
      <div className="print:hidden">
        <PageHeader
          title="Partager ma page"
          description="Chaque patient qui arrive par ce lien ou ce QR code peut vous demander un rendez-vous sur WhatsApp. Les visites sont comptées dans « Ma fiche annuaire »."
        />
      </div>

      {!publiee && (
        <p className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 print:hidden">
          Votre fiche n&apos;est pas encore publiée : le lien fonctionnera dès sa validation par l&apos;équipe DOKTIS.
        </p>
      )}

      <Card className="flex flex-col gap-3 print:hidden">
        <h2 className="text-base font-semibold text-slate-900">Votre lien</h2>
        <div className="flex flex-col gap-2 sm:flex-row">
          <input id="lien-partage" readOnly value={lien} onFocus={(e) => e.target.select()} className="min-w-0 flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700" />
          <Button type="button" onClick={copier}>{copie ? 'Lien copié' : 'Copier le lien'}</Button>
        </div>
        <ul className="list-disc space-y-1 pl-5 text-sm text-slate-600">
          {IDEES.map((i) => <li key={i}>{i}</li>)}
        </ul>
        <Link href={`/cabinet/${cabinet.slug}`} target="_blank" className="self-start text-sm font-medium text-teal-700 hover:underline">Voir ma page ↗</Link>
      </Card>

      <Card className="flex flex-col gap-4 print:border-0 print:p-0 print:shadow-none">
        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
          <h2 className="text-base font-semibold text-slate-900">Affiche avec QR code</h2>
          <div className="flex gap-2">
            {qr && (
              <a href={qr} download={`qr-${cabinet.slug}.png`} className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Télécharger le QR code
              </a>
            )}
            <Button type="button" onClick={() => window.print()}>Imprimer l&apos;affiche</Button>
          </div>
        </div>

        <div id="affiche" className="mx-auto flex w-full max-w-md flex-col items-center gap-4 rounded-2xl border-2 border-teal-800 p-8 text-center print:max-w-none print:border-4">
          <p className="text-sm font-bold tracking-widest text-teal-800">{SITE_NAME}</p>
          <h3 className="text-2xl font-extrabold text-slate-900">{cabinet.nom}</h3>
          <p className="text-lg font-semibold text-emerald-800">Prenez rendez-vous sur WhatsApp</p>
          {qr ? (
            // eslint-disable-next-line @next/next/no-img-element -- image générée localement (data URL)
            <img src={qr} alt={`QR code menant à la page de ${cabinet.nom}`} className="h-56 w-56 print:h-80 print:w-80" />
          ) : (
            <div className="h-56 w-56 animate-pulse rounded bg-slate-100" />
          )}
          <p className="text-sm text-slate-700">Scannez ce code avec l&apos;appareil photo de votre téléphone, choisissez un créneau et envoyez votre demande.</p>
          <p className="break-all text-xs text-slate-500">{base.replace(/^https?:\/\//, '')}/cabinet/{cabinet.slug}</p>
        </div>
      </Card>
    </div>
  );
}
