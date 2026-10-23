import { RechercheAnnuaire } from '@/components/annuaire/recherche-annuaire';

export default function ProfessionnelsPage() {
  return (
    <RechercheAnnuaire
      categorieFixe="professionnel"
      titre="Professionnels de santé indépendants"
      description="Médecins, chirurgiens-dentistes, kinésithérapeutes, sages-femmes et autres professionnels partenaires de DOKTIS."
    />
  );
}
