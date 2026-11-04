import { PUBLIC_API_URL } from './site';

export interface DemandeRendezVous {
  cabinet: string;
  praticien?: string;
  motif?: string;
  moment?: string;
  nom?: string;
}

/**
 * Message pré-rempli envoyé au cabinet. Il reste administratif : pas de
 * symptômes ni de détail médical, qui n'ont pas leur place sur WhatsApp.
 */
export function messageRendezVous(d: DemandeRendezVous): string {
  const lignes = [
    `Bonjour, je souhaite prendre rendez-vous au ${d.cabinet}${d.praticien ? ` avec ${d.praticien}` : ''}.`,
  ];
  if (d.motif) lignes.push(`Motif : ${d.motif}.`);
  if (d.moment) lignes.push(`Moment souhaité : ${d.moment}.`);
  if (d.nom) lignes.push(`Nom : ${d.nom}.`);
  lignes.push('(Demande envoyée depuis DOKTIS)');
  return lignes.join('\n');
}

export function lienWhatsApp(numero: string, message: string): string {
  return `https://wa.me/${numero.replace(/\D/g, '')}?text=${encodeURIComponent(message)}`;
}

/** Affiche 237690123456 sous la forme +237 690 12 34 56. */
export function formaterNumero(numero: string): string {
  const chiffres = numero.replace(/\D/g, '');
  if (chiffres.startsWith('237') && chiffres.length === 12) {
    const n = chiffres.slice(3);
    return `+237 ${n.slice(0, 3)} ${n.slice(3, 5)} ${n.slice(5, 7)} ${n.slice(7, 9)}`;
  }
  return `+${chiffres}`;
}

export type EvenementFiche = 'vue' | 'clic_whatsapp' | 'clic_telephone';

/**
 * Compteur anonyme. Formulaire URL-encodé via sendBeacon : pas de requête
 * préalable CORS, et l'envoi survit à l'ouverture de WhatsApp.
 */
export function signalerEvenement(slug: string, type: EvenementFiche): void {
  try {
    const url = `${PUBLIC_API_URL}/directory/cabinets/${encodeURIComponent(slug)}/evenements`;
    const corps = new URLSearchParams({ type });
    if (typeof navigator !== 'undefined' && navigator.sendBeacon) {
      navigator.sendBeacon(url, corps);
    } else {
      void fetch(url, { method: 'POST', body: corps, keepalive: true });
    }
  } catch {
    // Les statistiques ne doivent jamais bloquer le patient.
  }
}
