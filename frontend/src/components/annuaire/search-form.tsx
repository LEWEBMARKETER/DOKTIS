import type { Localite, Specialite } from '@/types/directory';

interface Props {
  specialites: Specialite[];
  localites: Localite[];
  valeurs?: { q?: string; specialite?: string; ville?: string; ouvert?: boolean; urgence?: boolean };
  compact?: boolean;
}

/**
 * Formulaire de recherche en GET vers /recherche : fonctionne sans JavaScript.
 */
export function SearchForm({ specialites, localites, valeurs = {}, compact = false }: Props) {
  return (
    <form action="/recherche" method="get" className="flex flex-col gap-3" role="search">
      <div className={`grid gap-3 ${compact ? 'sm:grid-cols-[1fr_1fr_1fr_auto]' : 'sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_auto]'}`}>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-700">Nom, praticien ou soin</span>
          <input
            id="recherche-q"
            name="q"
            defaultValue={valeurs.q}
            placeholder="ex. détartrage, Dr Nzé, pédiatre"
            className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-700">Spécialité</span>
          <select
            id="recherche-specialite"
            name="specialite"
            defaultValue={valeurs.specialite ?? ''}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600"
          >
            <option value="">Toutes les spécialités</option>
            {specialites.map((s) => (
              <option key={s.slug} value={s.slug}>{s.nom}</option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-700">Ville</span>
          <select
            id="recherche-ville"
            name="ville"
            defaultValue={valeurs.ville ?? ''}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600"
          >
            <option value="">Toutes les villes</option>
            {localites.map((l) => (
              <option key={l.slug} value={l.slug}>{l.ville}</option>
            ))}
          </select>
        </label>
        <div className="flex items-end">
          <button
            type="submit"
            className="w-full rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2"
          >
            Rechercher
          </button>
        </div>
      </div>
      <div className="flex flex-wrap gap-4 text-sm text-slate-700">
        <label className="inline-flex items-center gap-2">
          <input id="recherche-ouvert" type="checkbox" name="ouvert" value="1" defaultChecked={valeurs.ouvert} className="h-4 w-4 accent-teal-700" />
          Ouvert maintenant
        </label>
        <label className="inline-flex items-center gap-2">
          <input id="recherche-urgence" type="checkbox" name="urgence" value="1" defaultChecked={valeurs.urgence} className="h-4 w-4 accent-teal-700" />
          Accepte les urgences
        </label>
      </div>
    </form>
  );
}
