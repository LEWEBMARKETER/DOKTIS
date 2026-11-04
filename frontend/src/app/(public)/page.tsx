import Link from 'next/link';
import { SearchForm } from '@/components/annuaire/search-form';
import { JsonLd } from '@/components/annuaire/json-ld';
import { listerLocalites, listerSpecialites, lirePlanDuSite } from '@/lib/directory';
import { SITE_NAME, SITE_URL } from '@/lib/site';

export default async function Accueil() {
  const [specialites, localites, plan] = await Promise.all([listerSpecialites(), listerLocalites(), lirePlanDuSite()]);

  const nomSpecialite = new Map(specialites.map((s) => [s.slug, s.nom]));
  const nomVille = new Map(localites.map((l) => [l.slug, l.ville]));
  const populaires = plan.pages
    .filter((p) => p.quartier === null)
    .sort((a, b) => b.total - a.total)
    .slice(0, 12);

  return (
    <>
      <JsonLd
        data={{
          '@context': 'https://schema.org',
          '@type': 'WebSite',
          name: SITE_NAME,
          url: SITE_URL,
          potentialAction: {
            '@type': 'SearchAction',
            target: `${SITE_URL}/recherche?q={search_term_string}`,
            'query-input': 'required name=search_term_string',
          },
        }}
      />

      <section className="border-b border-slate-200 bg-white">
        <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
          <h1 className="max-w-3xl text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
            Trouvez un médecin ou un dentiste près de chez vous, et demandez votre rendez-vous sur WhatsApp.
          </h1>
          <p className="mt-3 max-w-2xl text-base text-slate-600">
            Horaires, tarifs indicatifs, langues parlées, urgences : tout est sur la fiche. Pas de compte à créer.
          </p>
          <div className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
            <SearchForm specialites={specialites} localites={localites} />
          </div>
        </div>
      </section>

      <div className="mx-auto flex max-w-6xl flex-col gap-12 px-4 py-10 sm:px-6">
        {populaires.length > 0 && (
          <section className="flex flex-col gap-4">
            <h2 className="text-xl font-bold text-slate-900">Recherches fréquentes</h2>
            <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {populaires.map((p) => (
                <li key={`${p.specialite}-${p.ville}`}>
                  <Link
                    href={`/${p.specialite}/${p.ville}`}
                    className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm hover:border-teal-600"
                  >
                    <span className="font-medium text-slate-800">
                      {nomSpecialite.get(p.specialite) ?? p.specialite} à {nomVille.get(p.ville) ?? p.ville}
                    </span>
                    <span className="tabular-nums text-slate-500">{p.total}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        )}

        {specialites.length > 0 && (
          <section className="flex flex-col gap-4">
            <h2 className="text-xl font-bold text-slate-900">Par spécialité</h2>
            <ul className="flex flex-wrap gap-2">
              {specialites.map((s) => (
                <li key={s.slug}>
                  <Link href={`/recherche?specialite=${s.slug}`} className="inline-block rounded-full border border-slate-300 bg-white px-4 py-1.5 text-sm text-slate-700 hover:border-teal-600 hover:text-teal-800">
                    {s.nom}
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        )}

        {localites.length > 0 && (
          <section className="flex flex-col gap-4">
            <h2 className="text-xl font-bold text-slate-900">Par ville et quartier</h2>
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {localites.map((l) => (
                <div key={l.slug} className="flex flex-col gap-2">
                  <Link href={`/recherche?ville=${l.slug}`} className="font-semibold text-slate-900 hover:text-teal-800 hover:underline">
                    {l.ville} <span className="font-normal text-slate-500">({l.total})</span>
                  </Link>
                  <ul className="flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-600">
                    {l.quartiers.map((q) => (
                      <li key={q.slug}>
                        <Link href={`/recherche?ville=${l.slug}&quartier=${q.slug}`} className="hover:text-teal-800 hover:underline">
                          {q.quartier}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          </section>
        )}

        <section className="flex flex-col gap-4">
          <h2 className="text-xl font-bold text-slate-900">Comment ça marche</h2>
          <ol className="grid gap-4 sm:grid-cols-3">
            {[
              ['Cherchez', 'Par spécialité, ville, quartier, ou les cabinets ouverts maintenant.'],
              ['Choisissez', 'Consultez horaires, tarifs indicatifs et langues parlées sur la fiche.'],
              ['Écrivez sur WhatsApp', 'Le message est pré-rempli. Le cabinet vous répond pour confirmer le rendez-vous.'],
            ].map(([titre, texte], i) => (
              <li key={titre} className="flex gap-3 rounded-xl border border-slate-200 bg-white p-4">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-50 text-sm font-bold text-teal-800">{i + 1}</span>
                <div>
                  <p className="font-semibold text-slate-900">{titre}</p>
                  <p className="text-sm text-slate-600">{texte}</p>
                </div>
              </li>
            ))}
          </ol>
        </section>

        <section className="flex flex-col items-start gap-3 rounded-2xl bg-teal-800 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-lg font-bold">Vous êtes praticien ?</h2>
            <p className="text-sm text-teal-50">
              Votre fiche existe peut-être déjà. Revendiquez-la gratuitement pour la compléter et recevoir les demandes de vos patients.
            </p>
          </div>
          <Link href="/register" className="shrink-0 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50">
            Inscrire mon cabinet
          </Link>
        </section>
      </div>
    </>
  );
}
