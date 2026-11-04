/**
 * Formats de dates pour les créneaux. Les dates arrivent déjà en heure
 * locale du Gabon (« 2026-11-04 », « 08:30 ») : on les affiche telles quelles,
 * sans conversion par le fuseau du navigateur.
 */
function dateUtc(date: string): Date {
  return new Date(`${date}T12:00:00Z`);
}

/** « mercredi 4 novembre » */
export function jourLong(date: string): string {
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(dateUtc(date));
}

/** « mer. 4 » */
export function jourCourt(date: string): string {
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', timeZone: 'UTC' }).format(dateUtc(date));
}

/** « 08 h 30 » */
export function heureLisible(heure: string): string {
  return heure.slice(0, 5).replace(':', ' h ');
}

/** Date du jour au Gabon, au format AAAA-MM-JJ. */
export function aujourdhuiLocal(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Libreville' }).format(new Date());
}

export function ajouterJours(date: string, jours: number): string {
  const d = dateUtc(date);
  d.setUTCDate(d.getUTCDate() + jours);
  return d.toISOString().slice(0, 10);
}
