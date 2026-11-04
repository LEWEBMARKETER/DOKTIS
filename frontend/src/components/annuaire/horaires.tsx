import { heure, JOURS, ORDRE_JOURS } from '@/lib/site';
import type { Horaire } from '@/types/directory';

export function TableHoraires({ horaires }: { horaires: Horaire[] }) {
  if (horaires.length === 0) {
    return <p className="text-sm text-slate-500">Horaires non renseignés. Contactez le cabinet pour les connaître.</p>;
  }

  const parJour = new Map(horaires.map((h) => [h.jour_semaine, h]));

  return (
    <table className="w-full text-sm">
      <tbody className="divide-y divide-slate-100">
        {ORDRE_JOURS.map((jour) => {
          const h = parJour.get(jour);
          const ferme = !h || h.ferme || !h.heure_ouverture || !h.heure_fermeture;
          return (
            <tr key={jour}>
              <th scope="row" className="py-1.5 pr-4 text-left font-medium text-slate-700">{JOURS[jour]}</th>
              <td className={`py-1.5 text-right tabular-nums ${ferme ? 'text-slate-400' : 'text-slate-800'}`}>
                {ferme ? 'Fermé' : `${heure(h.heure_ouverture)} – ${heure(h.heure_fermeture)}`}
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}
