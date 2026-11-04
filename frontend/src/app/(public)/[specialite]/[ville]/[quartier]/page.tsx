import type { Metadata } from 'next';
import { metadonneesListe, PageListe } from '@/components/annuaire/page-liste';

type Props = { params: Promise<{ specialite: string; ville: string; quartier: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  return metadonneesListe(await params);
}

export default async function SpecialiteVilleQuartier({ params }: Props) {
  return PageListe(await params);
}
