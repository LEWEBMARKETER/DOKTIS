export type Role = 'super_admin' | 'administrateur' | 'medecin' | 'secretaire' | 'assistant';

export interface Cabinet {
  id: number;
  nom: string;
  slug: string;
  type: string;
  telephone: string | null;
  email: string | null;
  plan: string;
  actif: boolean;
}

export interface User {
  id: number;
  cabinet_id: number | null;
  name: string;
  email: string;
  telephone: string | null;
  role: Role;
  specialite: string | null;
  actif: boolean;
  cabinet?: Cabinet;
}

export interface Patient {
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
  contact_urgence_nom: string | null;
  contact_urgence_telephone: string | null;
  notes: string | null;
  actif: boolean;
}

export interface RendezVous {
  id: number;
  patient_id: number;
  praticien_id: number;
  motif: string | null;
  type: string;
  debut: string;
  fin: string;
  statut: 'planifie' | 'confirme' | 'termine' | 'annule' | 'absent';
  notes: string | null;
  patient?: Patient;
  praticien?: User;
}

export interface Consultation {
  id: number;
  patient_id: number;
  praticien_id: number;
  rendez_vous_id: number | null;
  date_consultation: string;
  motif: string | null;
  diagnostic: string | null;
  observations: string | null;
  traitement: string | null;
  poids: string | null;
  tension: string | null;
  temperature: string | null;
  prochaine_visite_recommandee: string | null;
  patient?: Patient;
  praticien?: User;
}

export interface Ordonnance {
  id: number;
  patient_id: number;
  consultation_id: number | null;
  praticien_id: number;
  ordonnance_modele_id: number | null;
  contenu: string;
  date_emission: string;
  patient?: Patient;
  praticien?: User;
}

export interface FactureLigne {
  id: number;
  designation: string;
  quantite: string;
  prix_unitaire: string;
  montant: string;
}

export interface Paiement {
  id: number;
  facture_id: number;
  montant: string;
  mode_paiement: string;
  reference: string | null;
  date_paiement: string;
  echeance_numero: number | null;
}

export interface Facture {
  id: number;
  patient_id: number;
  numero: string;
  montant_total: string;
  montant_paye: string;
  statut: 'brouillon' | 'envoyee' | 'partiellement_payee' | 'payee' | 'annulee';
  date_emission: string;
  date_echeance: string | null;
  notes: string | null;
  patient?: Patient;
  lignes?: FactureLigne[];
  paiements?: Paiement[];
}

export interface PlanTraitementEtape {
  id: number;
  titre: string;
  description: string | null;
  ordre: number;
  statut: 'a_faire' | 'en_cours' | 'realisee' | 'annulee';
  cout: string | null;
  date_prevue: string | null;
  date_realisee: string | null;
}

export interface PlanTraitement {
  id: number;
  patient_id: number;
  titre: string;
  description: string | null;
  statut: 'propose' | 'en_cours' | 'termine' | 'abandonne';
  cout_estime: string | null;
  date_debut: string | null;
  date_fin_prevue: string | null;
  patient?: Patient;
  etapes?: PlanTraitementEtape[];
}

export interface DashboardStats {
  total_patients: number;
  rendez_vous_aujourdhui: number;
  consultations_mois: number;
  chiffre_affaires_mois: number;
  montant_encaisse_mois: number;
  factures_impayees: number;
  prochains_rendez_vous: RendezVous[];
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}
