import { FichesAdminPanel } from '@/components/admin/fiches-panel';

export default function AdminProfessionnelsPage() {
  return <FichesAdminPanel categorieFixe="professionnel_independant" titre="Professionnels indépendants" description="Médecins, dentistes, kinésithérapeutes, sages-femmes et autres praticiens indépendants." />;
}
