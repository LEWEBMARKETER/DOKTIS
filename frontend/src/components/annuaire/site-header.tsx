import Link from 'next/link';
import { SITE_NAME } from '@/lib/site';

export function SiteHeader() {
  return (
    <header className="border-b border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <Link href="/" className="flex items-baseline gap-2">
          <span className="text-xl font-extrabold tracking-tight text-teal-800">{SITE_NAME}</span>
          <span className="hidden text-xs text-slate-500 sm:inline">L&apos;annuaire santé</span>
        </Link>
        <nav className="flex items-center gap-1 text-sm">
          <Link href="/recherche" className="hidden rounded-lg px-3 py-2 font-medium text-slate-700 hover:bg-slate-100 sm:inline-block">
            Trouver un praticien
          </Link>
          <Link
            href="/pro"
            className="whitespace-nowrap rounded-lg border border-teal-700 px-3 py-2 font-medium text-teal-800 hover:bg-teal-50"
          >
            Espace praticien
          </Link>
        </nav>
      </div>
    </header>
  );
}

export function SiteFooter() {
  return (
    <footer className="mt-16 border-t border-slate-200 bg-white">
      <div className="mx-auto grid max-w-6xl gap-6 px-4 py-10 text-sm text-slate-600 sm:grid-cols-3 sm:px-6">
        <div className="space-y-2">
          <p className="text-base font-bold text-teal-800">{SITE_NAME}</p>
          <p>Trouvez un professionnel de santé près de chez vous et demandez votre rendez-vous directement sur WhatsApp.</p>
        </div>
        <div className="space-y-2">
          <p className="font-semibold text-slate-800">Patients</p>
          <ul className="space-y-1">
            <li><Link href="/recherche" className="hover:underline">Rechercher un praticien</Link></li>
            <li><Link href="/recherche?ouvert=1" className="hover:underline">Ouvert maintenant</Link></li>
            <li><Link href="/recherche?urgence=1" className="hover:underline">Urgences acceptées</Link></li>
          </ul>
        </div>
        <div className="space-y-2">
          <p className="font-semibold text-slate-800">Praticiens</p>
          <ul className="space-y-1">
            <li><Link href="/register" className="hover:underline">Inscrire mon cabinet</Link></li>
            <li><Link href="/pro" className="hover:underline">Gérer ma fiche</Link></li>
          </ul>
          <p className="text-xs text-slate-500">
            En cas d&apos;urgence vitale, appelez directement les services d&apos;urgence.
          </p>
        </div>
      </div>
    </footer>
  );
}
