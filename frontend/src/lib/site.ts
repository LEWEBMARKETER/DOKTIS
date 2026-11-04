/** Nom public de la plateforme et adresses utilisées côté serveur. */
export const SITE_NAME = 'DOKTIS';

export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL ?? 'http://localhost:3000').replace(/\/$/, '');

/** Adresse de l'API vue depuis le navigateur. */
export const PUBLIC_API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

/** Adresse de l'API vue depuis le serveur Next.js (peut être interne). */
export const SERVER_API_URL = process.env.API_URL ?? PUBLIC_API_URL;

export const JOURS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

/** Ordre d'affichage des jours : lundi en premier. */
export const ORDRE_JOURS = [1, 2, 3, 4, 5, 6, 0];

export function heure(h: string | null): string {
  return h ? h.slice(0, 5).replace(':', ' h ') : '';
}

export function formatFCFA(value: string | number | null): string | null {
  if (value === null || value === '') return null;
  return `${new Intl.NumberFormat('fr-FR').format(Number(value))} FCFA`;
}
