import type { Metadata } from 'next';
import Link from 'next/link';
import { CabinetCard } from '@/components/annuaire/cabinet-card';
import { SearchForm } from '@/components/annuaire/search-form';
import { listerLocalites, listerSpecialites, rechercherCabinets } from '@/lib/directory';

export const metadata: Metadata = {
  title: 'Rechercher un praticien',
  // Les pages de résultats filtrés ne doivent pas être indexées : les pages
  // « spécialité / ville / quartier » jouent ce rôle.
  robots: { index: false, follow: true },
};

type Params = { [cle: string]: string | string[] | undefined };

function texte(v: string | string[] | undefined): string | undefined {
  const s = Array.isArray(v) ? v[0] : v;
  return s?.trim() || undefined;
}

export default async function Recherche({ searchParams }: { searchParams: Promise<Params> }) {
  const sp = await searchParams;
  const filtres = {
    q: texte(sp.q),
    specialite: texte(sp.specialite),
    ville: texte(sp.ville),
    quartier: texte(sp.quartier),
    ouvert: texte(sp.ouvert) === '1',
    urgence: texte(sp.urgence) === '1',
    page: Math.max(1, Number(texte(sp.page)) || 1),
  };

  const [specialites, localites, resultats] = await Promise.all([
    listerSpecialites(),
    listerLocalites(),
    rechercherCabinets(filtres),
  ]);

  const lienPage = (page: number) => {
    const p = new URLSearchParams();
    for (const [cle, valeur] of Object.entries(filtres)) {
      if (cle === 'page' || valeur === undefined || valeur === false) continue;
      p.set(cle, valeur === true ? '1' : String(valeur));
    }
    if (page > 1) p.set('page', String(page));
    return `/recherche?${p.toString()}`;
  };

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
      <h1 className="text-2xl font-bold text-slate-900">Rechercher un praticien</h1>
      <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
        <SearchForm specialites={specialites} localites={localites} valeurs={filtres} compact />
      </div>

      <p className="text-sm text-slate-600" aria-live="polite">
        {resultats.total === 0
          ? 'Aucun praticien ne correspond à ces critères.'
          : `${resultats.total} praticien${resultats.total > 1 ? 's' : ''} trouvé${resultats.total > 1 ? 's' : ''}`}
      </p>

      {resultats.total === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
          Essayez d&apos;élargir la recherche : retirez un filtre ou choisissez une autre ville.{' '}
          <Link href="/recherche" className="font-medium text-teal-800 hover:underline">Voir tous les praticiens</Link>
        </div>
      ) : (
        <ul className="grid gap-4 md:grid-cols-2">
          {resultats.data.map((c) => <CabinetCard key={c.id} cabinet={c} />)}
        </ul>
      )}

      {resultats.last_page > 1 && (
        <nav aria-label="Pagination" className="flex items-center justify-between text-sm">
          {resultats.current_page > 1 ? (
            <Link href={lienPage(resultats.current_page - 1)} className="font-medium text-teal-800 hover:underline">← Page précédente</Link>
          ) : <span />}
          <span className="text-slate-500">Page {resultats.current_page} sur {resultats.last_page}</span>
          {resultats.current_page < resultats.last_page ? (
            <Link href={lienPage(resultats.current_page + 1)} className="font-medium text-teal-800 hover:underline">Page suivante →</Link>
          ) : <span />}
        </nav>
      )}
    </div>
  );
}
