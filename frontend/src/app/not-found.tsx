import Link from 'next/link';

export default function NotFound() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 px-4 text-center">
      <p className="text-sm font-semibold uppercase tracking-wide text-teal-800">Page introuvable</p>
      <h1 className="text-2xl font-bold text-slate-900">Cette page n&apos;existe pas ou plus.</h1>
      <p className="max-w-md text-slate-600">La fiche a peut-être été retirée de l&apos;annuaire. Lancez une nouvelle recherche.</p>
      <Link href="/recherche" className="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">
        Rechercher un praticien
      </Link>
    </div>
  );
}
