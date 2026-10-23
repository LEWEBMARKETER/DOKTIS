'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { Badge, Card } from '@/components/ui';
import type { DirectoryCabinet, Paginated, Specialite } from '@/types';

const ETAPES = [
  { titre: '1. Recherchez', texte: 'Trouvez une structure ou un professionnel par spécialité, ville ou quartier.' },
  { titre: '2. Consultez la fiche', texte: 'Horaires, services, langues parlées, avis — tout ce qu\'il faut avant de prendre rendez-vous.' },
  { titre: '3. Prenez rendez-vous', texte: 'Envoyez une demande de rendez-vous, le partenaire la confirme directement.' },
];

const AVANTAGES = [
  'Une fiche professionnelle visible par des milliers de patients potentiels.',
  'Un tableau de bord pour suivre vos visites, vos demandes et vos statistiques.',
  'La gestion des demandes de rendez-vous centralisée, sans effort technique.',
  'Un accompagnement administratif DOKTIS pour la mise en ligne de votre fiche.',
];

const FAQ = [
  { q: 'Qui peut créer une fiche sur DOKTIS ?', r: 'La création et la vérification des fiches sont assurées exclusivement par l\'équipe DOKTIS, pour garantir des informations fiables.' },
  { q: 'Comment prendre rendez-vous ?', r: 'Depuis la fiche d\'un professionnel ou d\'une structure, renseignez vos coordonnées et le créneau souhaité : la demande est transmise directement au partenaire, qui la confirme ou propose un autre horaire.' },
  { q: 'Quel est le prix de l\'abonnement ?', r: '100 000 FCFA par an pour une structure sanitaire, 50 000 FCFA par an pour un professionnel indépendant.' },
];

export default function AccueilPage() {
  const router = useRouter();
  const [q, setQ] = useState('');
  const [specialites, setSpecialites] = useState<Specialite[]>([]);
  const [recents, setRecents] = useState<DirectoryCabinet[]>([]);

  useEffect(() => {
    api.get<Specialite[]>('/directory/specialites').then((data) => setSpecialites(data.slice(0, 8))).catch(() => {});
    api
      .get<Paginated<DirectoryCabinet>>('/directory/cabinets?tri=recent&par_page=4')
      .then((res) => setRecents(res.data))
      .catch(() => {});
  }, []);

  function rechercher(e: React.FormEvent) {
    e.preventDefault();
    router.push(q ? `/recherche?q=${encodeURIComponent(q)}` : '/recherche');
  }

  return (
    <div className="space-y-20">
      {/* Section principale */}
      <section className="pt-8 text-center">
        <h1 className="mx-auto max-w-2xl text-3xl font-extrabold text-doktis-petrol sm:text-4xl">
          Trouvez le professionnel de santé qu&apos;il vous faut.
        </h1>
        <p className="mx-auto mt-4 max-w-xl text-slate-600">
          Découvrez les structures sanitaires et professionnels de santé près de chez vous au Gabon.
        </p>

        <form onSubmit={rechercher} className="mx-auto mt-8 flex max-w-xl flex-col gap-2 sm:flex-row">
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Nom, spécialité, ville ou quartier…"
            className="flex-1 rounded-lg border border-slate-300 px-4 py-3 text-sm focus:border-doktis-turquoise focus:outline-none focus:ring-1 focus:ring-doktis-turquoise"
          />
          <button
            type="submit"
            className="rounded-lg bg-doktis-turquoise px-6 py-3 text-sm font-semibold text-white hover:bg-doktis-turquoise/90"
          >
            Rechercher
          </button>
        </form>
      </section>

      {/* Catégories de spécialités */}
      {specialites.length > 0 && (
        <section>
          <h2 className="mb-4 text-xl font-bold text-doktis-anthracite">Catégories de spécialités</h2>
          <div className="flex flex-wrap gap-2">
            {specialites.map((s) => (
              <Link
                key={s.id}
                href={`/recherche?specialite=${s.slug}`}
                className="rounded-full border border-doktis-petrol/20 px-4 py-2 text-sm text-doktis-petrol hover:bg-doktis-petrol hover:text-white"
              >
                {s.nom}
              </Link>
            ))}
          </div>
        </section>
      )}

      {/* Récemment ajoutés */}
      {recents.length > 0 && (
        <section>
          <h2 className="mb-4 text-xl font-bold text-doktis-anthracite">Récemment ajoutés</h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {recents.map((c) => (
              <Link key={c.id} href={`/fiche/${c.slug}`}>
                <Card className="h-full transition-shadow hover:shadow-md">
                  <p className="font-semibold text-slate-900">{c.nom}</p>
                  <p className="text-sm text-slate-500">{[c.quartier, c.ville].filter(Boolean).join(', ')}</p>
                  <Badge tone={c.categorie_fiche === 'structure' ? 'blue' : 'green'}>
                    {c.categorie_fiche === 'structure' ? 'Structure' : 'Professionnel'}
                  </Badge>
                </Card>
              </Link>
            ))}
          </div>
        </section>
      )}

      {/* Comment fonctionne DOKTIS */}
      <section>
        <h2 className="mb-6 text-xl font-bold text-doktis-anthracite">Comment fonctionne DOKTIS ?</h2>
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
          {ETAPES.map((e) => (
            <Card key={e.titre}>
              <p className="font-semibold text-doktis-petrol">{e.titre}</p>
              <p className="mt-2 text-sm text-slate-600">{e.texte}</p>
            </Card>
          ))}
        </div>
      </section>

      {/* Avantages pour les professionnels */}
      <section className="rounded-2xl bg-doktis-petrol px-6 py-10 text-white sm:px-10">
        <h2 className="mb-6 text-xl font-bold">Avantages pour les professionnels</h2>
        <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {AVANTAGES.map((a) => (
            <li key={a} className="flex gap-2 text-sm text-white/90">
              <span className="text-doktis-turquoise">✓</span>
              {a}
            </li>
          ))}
        </ul>
        <Link
          href="/contact"
          className="mt-8 inline-block rounded-lg bg-doktis-turquoise px-6 py-3 text-sm font-semibold text-white hover:bg-doktis-turquoise/90"
        >
          Faire référencer ma structure
        </Link>
      </section>

      {/* Abonnement annuel */}
      <section>
        <h2 className="mb-6 text-xl font-bold text-doktis-anthracite">Un abonnement annuel simple</h2>
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          <Card>
            <p className="text-sm font-medium text-slate-500">DOKTIS STRUCTURE</p>
            <p className="mt-1 text-3xl font-extrabold text-doktis-petrol">100 000 FCFA<span className="text-base font-normal text-slate-500"> / an</span></p>
            <p className="mt-2 text-sm text-slate-600">Pour les structures sanitaires : cabinets, cliniques, centres médicaux, laboratoires.</p>
          </Card>
          <Card>
            <p className="text-sm font-medium text-slate-500">DOKTIS PROFESSIONNEL</p>
            <p className="mt-1 text-3xl font-extrabold text-doktis-petrol">50 000 FCFA<span className="text-base font-normal text-slate-500"> / an</span></p>
            <p className="mt-2 text-sm text-slate-600">Pour les professionnels indépendants : médecins, dentistes, kinésithérapeutes, sages-femmes…</p>
          </Card>
        </div>
      </section>

      {/* FAQ */}
      <section>
        <h2 className="mb-4 text-xl font-bold text-doktis-anthracite">Questions fréquentes</h2>
        <div className="space-y-2">
          {FAQ.map((f) => (
            <details key={f.q} className="rounded-lg border border-slate-200 bg-white p-4">
              <summary className="cursor-pointer font-medium text-slate-800">{f.q}</summary>
              <p className="mt-2 text-sm text-slate-600">{f.r}</p>
            </details>
          ))}
        </div>
      </section>
    </div>
  );
}
