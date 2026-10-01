import type { FactureLigne, Paiement, PlanTraitementEtape } from './index';

export interface PatientAccountType {
  id: number;
  nom: string;
  prenom: string;
  email: string;
  telephone: string;
  date_naissance: string | null;
  sexe: 'M' | 'F' | null;
  photo_path: string | null;
  contact_urgence_nom: string | null;
  contact_urgence_telephone: string | null;
  actif: boolean;
}

export interface CabinetSummary {
  id: number;
  nom: string;
  logo_path: string | null;
  telephone?: string | null;
  ville?: string | null;
}

export interface Dossier {
  id: number;
  numero_dossier: string;
  nom: string;
  prenom: string;
  date_naissance: string | null;
  sexe: 'M' | 'F' | null;
  telephone: string | null;
  email: string | null;
  adresse: string | null;
  groupe_sanguin: string | null;
  allergies: string | null;
  antecedents_medicaux: string | null;
  cabinet: CabinetSummary;
}

interface PatientRef {
  id: number;
  cabinet_id: number;
  cabinet: { id: number; nom: string };
}

export interface PraticienRef {
  id: number;
  name: string;
  specialite: string | null;
}

export interface PatientOrdonnance {
  id: number;
  contenu: string;
  date_emission: string;
  praticien: PraticienRef;
  patient: PatientRef;
}

export interface PatientDocument {
  id: number;
  type: string;
  titre: string;
  nom_fichier: string;
  mime_type: string | null;
  url: string;
  created_at: string;
  patient: PatientRef;
}

export interface PatientFacture {
  id: number;
  numero: string;
  montant_total: string;
  montant_paye: string;
  statut: 'brouillon' | 'envoyee' | 'partiellement_payee' | 'payee' | 'annulee';
  date_emission: string;
  date_echeance: string | null;
  lignes: FactureLigne[];
  paiements: Paiement[];
  patient: PatientRef;
}

export interface PatientPlanTraitement {
  id: number;
  titre: string;
  description: string | null;
  statut: 'propose' | 'en_cours' | 'termine' | 'abandonne';
  cout_estime: string | null;
  etapes: PlanTraitementEtape[];
  praticien: PraticienRef;
  patient: PatientRef;
}

export interface PatientRendezVous {
  id: number;
  motif: string | null;
  type: string;
  debut: string;
  fin: string;
  statut: 'planifie' | 'confirme' | 'termine' | 'annule' | 'absent';
  praticien: PraticienRef;
  patient: { id: number; cabinet_id: number };
}

export interface PatientConversation {
  id: number;
  cabinet_id: number;
  dernier_message_at: string | null;
  cabinet: CabinetSummary;
}

export interface PatientMessage {
  id: number;
  conversation_id: number;
  expediteur_type: 'patient' | 'staff';
  expediteur_id: number;
  contenu: string;
  lu_at: string | null;
  created_at: string;
}

export interface PatientAvis {
  id: number;
  cabinet_id: number;
  note: number;
  commentaire: string | null;
  reponse_cabinet: string | null;
  cabinet: CabinetSummary;
}
