export type StatutFiche = 'non_revendiquee' | 'en_attente' | 'verifiee';

export interface Specialite {
  id: number;
  nom: string;
  slug: string;
}

export interface Horaire {
  jour_semaine: number;
  heure_ouverture: string | null;
  heure_fermeture: string | null;
  ferme: boolean;
}

export interface ServiceCabinet {
  id: number;
  nom: string;
  description: string | null;
  prix_indicatif: string | number | null;
  duree_minutes: number | null;
  actif?: boolean;
}

export interface Praticien {
  id: number;
  name: string;
  role: string;
  specialite: string | null;
}

export interface FicheCabinet {
  id: number;
  nom: string;
  slug: string;
  type: 'dentaire' | 'medical' | 'mixte';
  telephone: string | null;
  whatsapp: string | null;
  email: string | null;
  adresse: string | null;
  ville: string | null;
  ville_slug: string | null;
  quartier: string | null;
  quartier_slug: string | null;
  pays: string | null;
  latitude: string | null;
  longitude: string | null;
  description: string | null;
  langues_parlees: string[] | null;
  accepte_urgences: boolean;
  accessible_pmr: boolean;
  site_web: string | null;
  note_moyenne: string;
  nombre_avis: number;
  statut_fiche: StatutFiche;
  ouvert_maintenant: boolean | null;
  updated_at: string;
  specialites: Specialite[];
  horaires: Horaire[];
  services?: ServiceCabinet[];
  users?: Praticien[];
}

export interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface Localite {
  ville: string;
  slug: string;
  total: number;
  quartiers: { quartier: string; slug: string; total: number }[];
}

export interface PlanDuSite {
  cabinets: { slug: string; updated_at: string }[];
  pages: { specialite: string; ville: string; quartier: string | null; total: number }[];
}
