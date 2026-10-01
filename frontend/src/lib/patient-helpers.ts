import type { Dossier } from '@/types/patient';

export function buildCabinetNameMap(dossiers: Dossier[]): Record<number, string> {
  return Object.fromEntries(dossiers.map((d) => [d.cabinet.id, d.cabinet.nom]));
}

export function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
}

export function formatDate(value: string | null): string {
  if (!value) return '—';
  return new Date(value).toLocaleDateString('fr-FR');
}

export function formatMontant(montant: string | number | null): string {
  if (montant === null || montant === undefined) return '—';
  return `${Number(montant).toLocaleString('fr-FR')} FCFA`;
}

export const STATUT_RDV_LABELS: Record<string, string> = {
  planifie: 'Planifié',
  confirme: 'Confirmé',
  termine: 'Terminé',
  annule: 'Annulé',
  absent: 'Absent',
};

export const STATUT_FACTURE_LABELS: Record<string, string> = {
  brouillon: 'Brouillon',
  envoyee: 'Envoyée',
  partiellement_payee: 'Partiellement payée',
  payee: 'Payée',
  annulee: 'Annulée',
};
