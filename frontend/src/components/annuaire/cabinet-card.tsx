import Link from 'next/link';
import type { FicheCabinet } from '@/types/directory';
import { BadgesFiche } from './badges';

export function CabinetCard({ cabinet }: { cabinet: FicheCabinet }) {
  const lieu = [cabinet.quartier, cabinet.ville].filter(Boolean).join(', ');

  return (
    <li className="rounded-xl border border-slate-200 bg-white p-4 transition-shadow hover:shadow-md sm:p-5">
      <div className="flex flex-col gap-2">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h3 className="text-base font-semibold text-slate-900">
            <Link href={`/cabinet/${cabinet.slug}`} className="hover:text-teal-800 hover:underline">
              {cabinet.nom}
            </Link>
          </h3>
          {lieu && <p className="text-sm text-slate-500">{lieu}</p>}
        </div>
        {cabinet.specialites.length > 0 && (
          <p className="text-sm text-teal-800">{cabinet.specialites.map((s) => s.nom).join(' · ')}</p>
        )}
        {cabinet.description && <p className="line-clamp-2 text-sm text-slate-600">{cabinet.description}</p>}
        <BadgesFiche cabinet={cabinet} />
        <div>
          <Link
            href={`/cabinet/${cabinet.slug}`}
            className="inline-flex items-center rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800"
          >
            Voir la fiche et demander un rendez-vous
          </Link>
        </div>
      </div>
    </li>
  );
}
