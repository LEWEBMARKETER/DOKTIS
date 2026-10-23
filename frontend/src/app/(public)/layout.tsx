import Link from 'next/link';

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="font-doktis flex min-h-screen flex-col bg-doktis-blanc text-doktis-anthracite">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
          <Link href="/" className="text-xl font-extrabold text-doktis-petrol">
            DOKTIS
          </Link>
          <nav className="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
            <Link href="/recherche" className="hover:text-doktis-petrol">Rechercher</Link>
            <Link href="/structures" className="hover:text-doktis-petrol">Structures</Link>
            <Link href="/professionnels" className="hover:text-doktis-petrol">Professionnels</Link>
            <Link href="/contact" className="hover:text-doktis-petrol">Contact</Link>
          </nav>
          <div className="flex items-center gap-4 text-sm font-medium">
            <Link href="/patient" className="text-slate-600 hover:text-doktis-petrol">
              Espace patient
            </Link>
            <Link
              href="/connexion"
              className="rounded-lg bg-doktis-petrol px-4 py-2 text-white hover:bg-doktis-petrol/90"
            >
              Connexion
            </Link>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">{children}</main>

      <footer className="border-t border-slate-200 bg-white py-10">
        <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
          <p>© {new Date().getFullYear()} DOKTIS — L&apos;annuaire des professionnels de santé au Gabon.</p>
          <div className="flex gap-4">
            <Link href="/connexion" className="hover:text-doktis-petrol">Espace partenaire</Link>
            <Link href="/contact" className="hover:text-doktis-petrol">Contact</Link>
          </div>
        </div>
      </footer>
    </div>
  );
}
