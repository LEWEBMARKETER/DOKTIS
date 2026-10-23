export type Role = 'super_admin' | 'administrateur' | 'medecin' | 'secretaire' | 'assistant' | 'partenaire';

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

export interface Specialite {
  id: number;
  nom: string;
  slug: string;
}

export interface Mutuelle {
  id: number;
  nom: string;
}

export interface CabinetPhoto {
  id: number;
  chemin: string;
  disque: string;
  ordre: number;
}

export interface CabinetHoraire {
  id: number;
  jour_semaine: number;
  heure_ouverture: string | null;
  heure_fermeture: string | null;
  ferme: boolean;
}

export interface CabinetServicePublic {
  id: number;
  nom: string;
  description: string | null;
  prix_indicatif: string | null;
  duree_minutes: number | null;
}

export interface AvisPublic {
  id: number;
  note: number;
  commentaire: string | null;
  reponse_cabinet: string | null;
  reponse_at: string | null;
  created_at: string;
  auteur?: { nom: string; prenom: string } | null;
}

export interface PraticienPublic {
  id: number;
  name: string;
  role: Role;
  specialite: string | null;
}

export interface DirectoryCabinet {
  id: number;
  nom: string;
  slug: string;
  type: string;
  categorie_fiche: 'structure' | 'professionnel_independant';
  telephone: string | null;
  email: string | null;
  adresse: string | null;
  ville: string | null;
  quartier: string | null;
  description: string | null;
  langues_parlees: string[] | null;
  accepte_urgences: boolean;
  accessible_pmr: boolean;
  site_web: string | null;
  note_moyenne: string;
  nombre_avis: number;
  specialites?: Specialite[];
  mutuelles?: Mutuelle[];
  photos?: CabinetPhoto[];
  horaires?: CabinetHoraire[];
  services?: CabinetServicePublic[];
  avis?: AvisPublic[];
  users?: PraticienPublic[];
}

// --- Directory-first : administration et espace partenaire ---

export type CategorieFiche = 'structure' | 'professionnel_independant';
export type StatutVerificationFiche = 'non_verifiee' | 'en_attente_verification' | 'verifiee';
export type StatutPublicationFiche = 'brouillon' | 'publiee' | 'suspendue' | 'archivee';
export type StatutDemandeRendezVous = 'en_attente' | 'confirmee' | 'refusee' | 'annulee' | 'terminee';
export type StatutDemandeModification = 'en_attente' | 'validee' | 'refusee';
export type ProduitAbonnement = 'office' | 'directory_structure' | 'directory_professionnel';

export interface AdminFiche {
  id: number;
  nom: string;
  slug: string;
  categorie_fiche: CategorieFiche;
  statut_verification: StatutVerificationFiche;
  statut_publication: StatutPublicationFiche;
  type: string;
  telephone: string | null;
  email: string | null;
  adresse: string | null;
  ville: string | null;
  quartier: string | null;
  description: string | null;
  actif: boolean;
  created_at: string;
  users_count?: number;
  specialites?: Specialite[];
  users?: { id: number; name: string; email: string; role: Role; actif: boolean }[];
  invitations_partenaire?: { id: number; email: string; expire_at: string; utilise_at: string | null }[];
  abonnements?: AbonnementAdmin[];
}

export interface AbonnementAdmin {
  id: number;
  cabinet_id: number;
  produit: ProduitAbonnement;
  plan: string;
  prix: string;
  cycle_facturation: 'mensuel' | 'annuel';
  statut: 'actif' | 'suspendu' | 'annule' | 'expire';
  date_debut: string;
  date_fin: string | null;
  mode_paiement: string | null;
  cabinet?: { id: number; nom: string };
  paiements?: PaiementAbonnement[];
}

export interface PaiementAbonnement {
  id: number;
  abonnement_id: number;
  montant: string;
  mode_paiement: 'airtel_money' | 'moov_money' | 'virement' | 'especes';
  reference: string | null;
  date_paiement: string;
}

export interface DemandeRendezVousAdmin {
  id: number;
  cabinet_id: number;
  nom: string;
  prenom: string;
  telephone: string;
  email: string | null;
  date_souhaitee: string;
  creneau_souhaite: string | null;
  motif: string | null;
  statut: StatutDemandeRendezVous;
  notes_partenaire: string | null;
  date_proposee: string | null;
  creneau_propose: string | null;
  cabinet?: { id: number; nom: string; categorie_fiche?: CategorieFiche };
}

export interface DemandeModificationFicheAdmin {
  id: number;
  cabinet_id: number;
  partenaire_id: number;
  champs_proposes: Record<string, unknown>;
  statut: StatutDemandeModification;
  commentaire_admin: string | null;
  cabinet?: { id: number; nom: string };
  partenaire?: { id: number; name: string };
}

export interface PartenaireAdmin {
  id: number;
  name: string;
  email: string;
  actif: boolean;
  cabinet?: { id: number; nom: string; categorie_fiche: CategorieFiche };
}
