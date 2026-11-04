import type { FicheCabinet } from '@/types/directory';

function Pastille({ children, ton }: { children: React.ReactNode; ton: 'vert' | 'gris' | 'ambre' | 'bleu' }) {
  const tons = {
    vert: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    gris: 'bg-slate-100 text-slate-600 ring-slate-200',
    ambre: 'bg-amber-50 text-amber-800 ring-amber-200',
    bleu: 'bg-sky-50 text-sky-800 ring-sky-200',
  }[ton];
  return <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${tons}`}>{children}</span>;
}

export function StatutOuverture({ ouvert }: { ouvert: boolean | null }) {
  if (ouvert === null) return null;
  return ouvert ? (
    <Pastille ton="vert">
      <span className="h-1.5 w-1.5 rounded-full bg-emerald-600" aria-hidden />
      Ouvert maintenant
    </Pastille>
  ) : (
    <Pastille ton="gris">Fermé actuellement</Pastille>
  );
}

export function BadgesFiche({ cabinet }: { cabinet: FicheCabinet }) {
  return (
    <div className="flex flex-wrap gap-1.5">
      <StatutOuverture ouvert={cabinet.ouvert_maintenant} />
      {cabinet.whatsapp && <Pastille ton="vert">Rendez-vous WhatsApp</Pastille>}
      {cabinet.accepte_urgences && <Pastille ton="ambre">Urgences</Pastille>}
      {cabinet.accessible_pmr && <Pastille ton="bleu">Accès PMR</Pastille>}
      {cabinet.statut_fiche === 'verifiee' ? (
        <Pastille ton="bleu">Fiche vérifiée</Pastille>
      ) : (
        <Pastille ton="gris">Informations non vérifiées</Pastille>
      )}
    </div>
  );
}
