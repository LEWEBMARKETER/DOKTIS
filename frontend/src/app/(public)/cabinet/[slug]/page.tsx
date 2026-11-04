import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { BadgesFiche } from '@/components/annuaire/badges';
import { FilAriane } from '@/components/annuaire/breadcrumb';
import { BarreRendezVousMobile, CompteurVue, DemandeWhatsApp } from '@/components/annuaire/demande-whatsapp';
import { TableHoraires } from '@/components/annuaire/horaires';
import { JsonLd } from '@/components/annuaire/json-ld';
import { lireFiche } from '@/lib/directory';
import { formatFCFA, SITE_URL } from '@/lib/site';
import type { FicheCabinet } from '@/types/directory';

type Props = { params: Promise<{ slug: string }> };

function resume(c: FicheCabinet): string {
  const specialites = c.specialites.map((s) => s.nom).join(', ');
  const lieu = [c.quartier, c.ville].filter(Boolean).join(', ');
  return [specialites, lieu].filter(Boolean).join(' à ');
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const cabinet = await lireFiche((await params).slug);
  if (!cabinet) return { title: 'Fiche introuvable' };

  const sousTitre = resume(cabinet);
  return {
    title: sousTitre ? `${cabinet.nom} : ${sousTitre}` : cabinet.nom,
    description:
      cabinet.description?.slice(0, 155) ??
      `${cabinet.nom}${sousTitre ? `, ${sousTitre}` : ''}. Horaires, tarifs indicatifs et demande de rendez-vous sur WhatsApp.`,
    alternates: { canonical: `/cabinet/${cabinet.slug}` },
  };
}

function donneesStructurees(c: FicheCabinet): Record<string, unknown> {
  const ouvertures = c.horaires
    .filter((h) => !h.ferme && h.heure_ouverture && h.heure_fermeture)
    .map((h) => ({
      '@type': 'OpeningHoursSpecification',
      dayOfWeek: `https://schema.org/${['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][h.jour_semaine]}`,
      opens: h.heure_ouverture!.slice(0, 5),
      closes: h.heure_fermeture!.slice(0, 5),
    }));

  return {
    '@context': 'https://schema.org',
    '@type': c.type === 'dentaire' ? 'Dentist' : 'MedicalClinic',
    name: c.nom,
    url: `${SITE_URL}/cabinet/${c.slug}`,
    description: c.description ?? undefined,
    telephone: c.telephone ?? (c.whatsapp ? `+${c.whatsapp}` : undefined),
    address: {
      '@type': 'PostalAddress',
      streetAddress: c.adresse ?? undefined,
      addressLocality: c.ville ?? undefined,
      addressRegion: c.quartier ?? undefined,
      addressCountry: c.pays ?? undefined,
    },
    geo: c.latitude && c.longitude ? { '@type': 'GeoCoordinates', latitude: Number(c.latitude), longitude: Number(c.longitude) } : undefined,
    medicalSpecialty: c.specialites.map((s) => s.nom),
    knowsLanguage: c.langues_parlees ?? undefined,
    isAccessibleForFree: false,
    openingHoursSpecification: ouvertures.length ? ouvertures : undefined,
    aggregateRating: c.nombre_avis > 0 ? { '@type': 'AggregateRating', ratingValue: Number(c.note_moyenne), reviewCount: c.nombre_avis } : undefined,
  };
}

export default async function FicheCabinetPage({ params }: Props) {
  const cabinet = await lireFiche((await params).slug);
  if (!cabinet) notFound();

  const specialite = cabinet.specialites[0];
  const praticiens = cabinet.users ?? [];
  const services = cabinet.services ?? [];
  const lieu = [cabinet.adresse, cabinet.quartier, cabinet.ville].filter(Boolean).join(', ');
  const itineraire =
    cabinet.latitude && cabinet.longitude
      ? `https://www.google.com/maps/search/?api=1&query=${cabinet.latitude},${cabinet.longitude}`
      : lieu
        ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${cabinet.nom}, ${lieu}`)}`
        : null;

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
      <JsonLd data={donneesStructurees(cabinet)} />
      <CompteurVue slug={cabinet.slug} />

      <FilAriane
        miettes={[
          { nom: 'Accueil', href: '/' },
          ...(specialite && cabinet.ville_slug
            ? [{ nom: `${specialite.nom} à ${cabinet.ville}`, href: `/${specialite.slug}/${cabinet.ville_slug}` }]
            : []),
          { nom: cabinet.nom },
        ]}
      />

      {cabinet.statut_fiche !== 'verifiee' && (
        <div className="flex flex-col gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
          <p>
            Fiche créée par l&apos;équipe DOKTIS à partir d&apos;informations publiques. Ces informations n&apos;ont pas encore été vérifiées par le cabinet.
          </p>
          <Link href={`/cabinet/${cabinet.slug}/revendiquer`} className="shrink-0 font-semibold text-amber-900 underline">
            Vous êtes ce praticien ? Revendiquer cette fiche
          </Link>
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div className="flex min-w-0 flex-col gap-6">
          <header className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h1 className="text-2xl font-bold text-slate-900 sm:text-3xl">{cabinet.nom}</h1>
            {cabinet.specialites.length > 0 && (
              <p className="text-teal-800">{cabinet.specialites.map((s) => s.nom).join(' · ')}</p>
            )}
            {lieu && (
              <p className="text-sm text-slate-600">
                {lieu}
                {itineraire && (
                  <>
                    {' · '}
                    <a href={itineraire} target="_blank" rel="noopener noreferrer" className="font-medium text-teal-800 hover:underline">
                      Itinéraire
                    </a>
                  </>
                )}
              </p>
            )}
            <BadgesFiche cabinet={cabinet} />
            {cabinet.description && <p className="whitespace-pre-line text-slate-700">{cabinet.description}</p>}
            {cabinet.langues_parlees && cabinet.langues_parlees.length > 0 && (
              <p className="text-sm text-slate-600">Langues parlées : {cabinet.langues_parlees.join(', ')}</p>
            )}
          </header>

          {services.length > 0 && (
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
              <h2 className="mb-3 text-lg font-semibold text-slate-900">Soins et tarifs indicatifs</h2>
              <ul className="divide-y divide-slate-100 text-sm">
                {services.map((s) => (
                  <li key={s.id} className="flex flex-wrap items-baseline justify-between gap-2 py-2">
                    <span className="text-slate-800">
                      {s.nom}
                      {s.duree_minutes ? <span className="text-slate-500"> · {s.duree_minutes} min</span> : null}
                    </span>
                    <span className="tabular-nums font-medium text-slate-900">{formatFCFA(s.prix_indicatif) ?? 'Sur devis'}</span>
                  </li>
                ))}
              </ul>
              <p className="mt-3 text-xs text-slate-500">Tarifs donnés par le cabinet à titre indicatif ; le prix final est confirmé en consultation.</p>
            </section>
          )}

          {praticiens.length > 0 && (
            <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
              <h2 className="mb-3 text-lg font-semibold text-slate-900">Praticiens</h2>
              <ul className="grid gap-2 sm:grid-cols-2">
                {praticiens.map((p) => (
                  <li key={p.id} className="rounded-lg bg-slate-50 px-3 py-2 text-sm">
                    <p className="font-medium text-slate-900">{p.name}</p>
                    {p.specialite && <p className="text-slate-600">{p.specialite}</p>}
                  </li>
                ))}
              </ul>
            </section>
          )}

          <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 className="mb-3 text-lg font-semibold text-slate-900">Horaires</h2>
            <TableHoraires horaires={cabinet.horaires} />
            <p className="mt-3 text-xs text-slate-500">Horaires en heure locale, communiqués par le cabinet.</p>
          </section>
        </div>

        <aside className="lg:sticky lg:top-6 lg:self-start">
          <div id="prendre-rendez-vous" className="scroll-mt-4 rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
            <h2 className="mb-1 text-lg font-semibold text-slate-900">Prendre rendez-vous</h2>
            <p className="mb-4 text-sm text-slate-600">Préparez votre demande, puis envoyez-la au cabinet sur WhatsApp.</p>
            <DemandeWhatsApp
              slug={cabinet.slug}
              cabinet={cabinet.nom}
              whatsapp={cabinet.whatsapp}
              telephone={cabinet.telephone}
              praticiens={praticiens.map((p) => p.name)}
              services={services.map((s) => s.nom)}
            />
          </div>
        </aside>
      </div>

      {/* Sur mobile, le formulaire est en bas de page : un bouton fixe y mène directement. */}
      {(cabinet.whatsapp || cabinet.telephone) && (
        <BarreRendezVousMobile
          cible="prendre-rendez-vous"
          libelle={cabinet.whatsapp ? 'Demander un rendez-vous sur WhatsApp' : 'Contacter le cabinet'}
        />
      )}
    </div>
  );
}
