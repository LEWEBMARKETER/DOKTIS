import { notFound, redirect } from 'next/navigation';

/**
 * Ancienne URL de fiche (/directory/{id}) : redirige vers la nouvelle URL
 * publique par slug (/fiche/{slug}), sans boucle puisque ce composant ne
 * rend jamais rien lui-même — il résout toujours vers une URL différente
 * ou vers notFound(). Préserve les liens déjà partagés/indexés.
 */
export default async function AncienneFichePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const backendUrl = process.env.BACKEND_INTERNAL_URL ?? 'http://localhost:8000';

  const response = await fetch(`${backendUrl}/api/directory/cabinets/${id}`, { cache: 'no-store' });

  if (!response.ok) {
    notFound();
  }

  const cabinet: { slug: string } = await response.json();
  redirect(`/fiche/${cabinet.slug}`);
}
