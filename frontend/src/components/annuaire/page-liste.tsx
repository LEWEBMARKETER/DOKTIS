import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { CabinetCard } from './cabinet-card';
import { FilAriane } from './breadcrumb';
import { JsonLd } from './json-ld';
import { lirePlanDuSite, listerLocalites, listerSpecialites, rechercherCabinets } from '@/lib/directory';
import { SITE_URL } from '@/lib/site';

interface Cle {
  specialite: string;
  ville: string;
  quartier?: string;
}

/**
 * Page « spécialité à ville (quartier) » : une page indexable par combinaison
 * réellement couverte par l'annuaire. Les combinaisons vides renvoient 404
 * pour ne pas publier de pages sans contenu.
 */
async function charger({ specialite, ville, quartier }: Cle) {
  const [specialites, localites] = await Promise.all([listerSpecialites(), listerLocalites()]);
  const spec = specialites.find((s) => s.slug === specialite);
  const loc = localites.find((l) => l.slug === ville);
  const quart = quartier ? loc?.quartiers.find((q) => q.slug === quartier) : undefined;

  if (!spec || !loc || (quartier && !quart)) return null;

  const resultats = await rechercherCabinets({ specialite, ville, quartier });
  if (resultats.total === 0) return null;

  const lieu = quart ? `${quart.quartier}, ${loc.ville}` : loc.ville;
  const titre = `${spec.nom} à ${lieu}`;
  const chemin = `/${spec.slug}/${loc.slug}${quart ? `/${quart.slug}` : ''}`;

  return { spec, loc, quart, resultats, lieu, titre, chemin };
}

export async function metadonneesListe(cle: Cle): Promise<Metadata> {
  const d = await charger(cle);
  if (!d) return { title: 'Page introuvable' };

  const n = d.resultats.total;
  return {
    title: `${d.titre} : ${n} praticien${n > 1 ? 's' : ''}`,
    description: `${n} ${d.spec.nom.toLowerCase()}${n > 1 ? 's' : ''} à ${d.lieu} : horaires, tarifs indicatifs, urgences. Demandez votre rendez-vous directement sur WhatsApp.`,
    alternates: { canonical: d.chemin },
  };
}

export async function PageListe(cle: Cle) {
  const d = await charger(cle);
  if (!d) notFound();

  const plan = await lirePlanDuSite();
  const autresQuartiers = d.loc.quartiers.filter((q) =>
    q.slug !== d.quart?.slug &&
    plan.pages.some((p) => p.specialite === d.spec.slug && p.ville === d.loc.slug && p.quartier === q.slug),
  );
  const specialites = await listerSpecialites();
  const autresSpecialites = specialites.filter((s) =>
    s.slug !== d.spec.slug && plan.pages.some((p) => p.specialite === s.slug && p.ville === d.loc.slug && p.quartier === null),
  );

  const miettes = [
    { nom: 'Accueil', href: '/' },
    { nom: `${d.spec.nom} à ${d.loc.ville}`, href: d.quart ? `/${d.spec.slug}/${d.loc.slug}` : undefined },
    ...(d.quart ? [{ nom: d.quart.quartier }] : []),
  ];

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
      <JsonLd
        data={{
          '@context': 'https://schema.org',
          '@type': 'BreadcrumbList',
          itemListElement: miettes.map((m, i) => ({
            '@type': 'ListItem',
            position: i + 1,
            name: m.nom,
            item: `${SITE_URL}${m.href ?? d.chemin}`,
          })),
        }}
      />
      <FilAriane miettes={miettes} />

      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-bold text-slate-900 sm:text-3xl">{d.titre}</h1>
        <p className="max-w-2xl text-slate-600">
          {d.resultats.total} praticien{d.resultats.total > 1 ? 's' : ''} référencé{d.resultats.total > 1 ? 's' : ''} sur DOKTIS.
          Consultez les horaires et les tarifs indicatifs, puis demandez votre rendez-vous sur WhatsApp.
        </p>
        <p className="text-sm">
          <Link
            href={`/recherche?specialite=${d.spec.slug}&ville=${d.loc.slug}${d.quart ? `&quartier=${d.quart.slug}` : ''}&ouvert=1`}
            className="font-medium text-teal-800 hover:underline"
          >
            Afficher seulement ceux ouverts maintenant
          </Link>
        </p>
      </header>

      <ul className="grid gap-4 md:grid-cols-2">
        {d.resultats.data.map((c) => <CabinetCard key={c.id} cabinet={c} />)}
      </ul>

      {(autresQuartiers.length > 0 || autresSpecialites.length > 0) && (
        <aside className="grid gap-6 border-t border-slate-200 pt-6 sm:grid-cols-2">
          {autresQuartiers.length > 0 && (
            <div className="flex flex-col gap-2">
              <h2 className="font-semibold text-slate-900">{d.spec.nom} dans d&apos;autres quartiers de {d.loc.ville}</h2>
              <ul className="flex flex-wrap gap-2 text-sm">
                {autresQuartiers.map((q) => (
                  <li key={q.slug}>
                    <Link href={`/${d.spec.slug}/${d.loc.slug}/${q.slug}`} className="rounded-full border border-slate-300 bg-white px-3 py-1 hover:border-teal-600">{q.quartier}</Link>
                  </li>
                ))}
              </ul>
            </div>
          )}
          {autresSpecialites.length > 0 && (
            <div className="flex flex-col gap-2">
              <h2 className="font-semibold text-slate-900">Autres spécialités à {d.loc.ville}</h2>
              <ul className="flex flex-wrap gap-2 text-sm">
                {autresSpecialites.map((s) => (
                  <li key={s.slug}>
                    <Link href={`/${s.slug}/${d.loc.slug}`} className="rounded-full border border-slate-300 bg-white px-3 py-1 hover:border-teal-600">{s.nom}</Link>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </aside>
      )}
    </div>
  );
}
