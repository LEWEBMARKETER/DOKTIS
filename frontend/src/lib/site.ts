/** Nom public de la plateforme et adresses utilisées côté serveur. */
export const SITE_NAME = 'DOKTIS';

/** Adresse publique du site (voir next.config.ts). */
export const SITE_URL = (process.env.DOKTIS_SITE_URL ?? 'http://localhost:3000').replace(/\/$/, '');

/**
 * Adresse de l'API vue depuis le navigateur. En production : « /api », relayé
 * par Next.js vers l'API (même domaine). En local : http://localhost:8000/api.
 */
export const PUBLIC_API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

/** Adresse de l'API vue depuis le serveur Next.js (réseau privé sur Render). */
export const SERVER_API_URL =
  process.env.API_URL ?? (PUBLIC_API_URL.startsWith('http') ? PUBLIC_API_URL : `${process.env.DOKTIS_API_ORIGIN}/api`);

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
