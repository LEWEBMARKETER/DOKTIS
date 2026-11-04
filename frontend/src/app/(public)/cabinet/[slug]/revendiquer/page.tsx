import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { FormulaireRevendication } from './formulaire';
import { lireFiche } from '@/lib/directory';

type Props = { params: Promise<{ slug: string }> };

export const metadata: Metadata = {
  title: 'Revendiquer une fiche',
  robots: { index: false, follow: false },
};

export default async function Revendiquer({ params }: Props) {
  const cabinet = await lireFiche((await params).slug);
  if (!cabinet) notFound();

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-8 sm:px-6">
      <Link href={`/cabinet/${cabinet.slug}`} className="text-sm text-teal-800 hover:underline">← Retour à la fiche</Link>
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-bold text-slate-900">Revendiquer « {cabinet.nom} »</h1>
        <p className="text-slate-600">
          Gratuit. Une fois la fiche vérifiée, vous pourrez corriger les informations, indiquer votre numéro WhatsApp, vos horaires et vos tarifs,
          et suivre le nombre de demandes reçues.
        </p>
      </header>

      {cabinet.statut_fiche === 'non_revendiquee' ? (
        <FormulaireRevendication slug={cabinet.slug} />
      ) : (
        <p className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-700">
          Cette fiche est déjà gérée par son cabinet. Si vous pensez qu&apos;il s&apos;agit d&apos;une erreur, contactez le support DOKTIS.
        </p>
      )}
    </div>
  );
}
