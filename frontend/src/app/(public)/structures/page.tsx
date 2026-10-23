import { RechercheAnnuaire } from '@/components/annuaire/recherche-annuaire';

export default function StructuresPage() {
  return (
    <RechercheAnnuaire
      categorieFixe="structure"
      titre="Structures sanitaires"
      description="Cabinets, cliniques, centres médicaux et laboratoires partenaires de DOKTIS."
    />
  );
}
