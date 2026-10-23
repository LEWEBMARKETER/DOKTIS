'use client';

import { Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { RechercheAnnuaire } from '@/components/annuaire/recherche-annuaire';

function RechercheAvecParametres() {
  const searchParams = useSearchParams();

  return (
    <RechercheAnnuaire
      titre="Trouvez le professionnel de santé qu'il vous faut"
      description="Structures sanitaires et professionnels indépendants partenaires de DOKTIS."
      rechercheInitiale={searchParams.get('q') ?? ''}
    />
  );
}

export default function RecherchePage() {
  return (
    <Suspense fallback={<p className="py-10 text-center text-slate-400">Chargement…</p>}>
      <RechercheAvecParametres />
    </Suspense>
  );
}
