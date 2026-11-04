import Link from 'next/link';

export interface Miette {
  nom: string;
  href?: string;
}

export function FilAriane({ miettes }: { miettes: Miette[] }) {
  return (
    <nav aria-label="Fil d'Ariane" className="text-sm text-slate-500">
      <ol className="flex flex-wrap items-center gap-1">
        {miettes.map((m, i) => (
          <li key={`${m.nom}-${i}`} className="flex items-center gap-1">
            {i > 0 && <span aria-hidden>›</span>}
            {m.href ? (
              <Link href={m.href} className="hover:text-teal-800 hover:underline">{m.nom}</Link>
            ) : (
              <span className="text-slate-700">{m.nom}</span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
