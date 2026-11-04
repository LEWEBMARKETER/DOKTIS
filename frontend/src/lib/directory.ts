import { SERVER_API_URL } from './site';
import type { FicheCabinet, Localite, Page, PlanDuSite, Specialite } from '@/types/directory';

/** Les pages publiques sont régénérées au plus toutes les 5 minutes. */
const REVALIDATE_SECONDES = 300;

async function lire<T>(chemin: string): Promise<T | null> {
  try {
    const res = await fetch(`${SERVER_API_URL}${chemin}`, {
      headers: { Accept: 'application/json' },
      next: { revalidate: REVALIDATE_SECONDES },
    });
    if (res.status === 404) return null;
    if (!res.ok) throw new Error(`API ${res.status} sur ${chemin}`);
    return (await res.json()) as T;
  } catch (err) {
    // L'annuaire doit rester affichable même si l'API est momentanément indisponible.
    console.error('[directory]', err);
    return null;
  }
}

export interface FiltresRecherche {
  q?: string;
  specialite?: string;
  ville?: string;
  quartier?: string;
  ouvert?: boolean;
  urgence?: boolean;
  whatsapp?: boolean;
  page?: number;
}

export async function rechercherCabinets(filtres: FiltresRecherche): Promise<Page<FicheCabinet>> {
  const params = new URLSearchParams();
  for (const [cle, valeur] of Object.entries(filtres)) {
    if (valeur === undefined || valeur === '' || valeur === false) continue;
    params.set(cle, valeur === true ? '1' : String(valeur));
  }
  params.set('par_page', '24');

  return (
    (await lire<Page<FicheCabinet>>(`/directory/cabinets?${params.toString()}`)) ?? {
      data: [],
      current_page: 1,
      last_page: 1,
      total: 0,
    }
  );
}

export async function lireFiche(slug: string): Promise<FicheCabinet | null> {
  return lire<FicheCabinet>(`/directory/cabinets/${encodeURIComponent(slug)}`);
}

export async function listerSpecialites(): Promise<Specialite[]> {
  return (await lire<Specialite[]>('/directory/specialites')) ?? [];
}

export async function listerLocalites(): Promise<Localite[]> {
  return (await lire<Localite[]>('/directory/localites')) ?? [];
}

export async function lirePlanDuSite(): Promise<PlanDuSite> {
  return (await lire<PlanDuSite>('/directory/plan-du-site')) ?? { cabinets: [], pages: [] };
}
