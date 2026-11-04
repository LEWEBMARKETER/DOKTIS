'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/lib/auth-context';
import { MODULES, type Module } from '@/lib/modules';
import type { Role } from '@/types';

const EQUIPE: Role[] = ['administrateur', 'medecin', 'secretaire', 'assistant'];

const NAV: { href: string; label: string; icon: string; roles: Role[]; module?: Module }[] = [
  { href: '/pro', label: 'Ma fiche annuaire', icon: '📍', roles: EQUIPE },
  { href: '/pro/tableau-de-bord', label: 'Tableau de bord', icon: '📊', roles: EQUIPE, module: 'saas' },
  { href: '/pro/patients', label: 'Patients', icon: '🧑‍🤝‍🧑', roles: EQUIPE, module: 'saas' },
  { href: '/pro/agenda', label: 'Agenda', icon: '📅', roles: EQUIPE, module: 'saas' },
  { href: '/pro/consultations', label: 'Consultations', icon: '🩺', roles: ['administrateur', 'medecin', 'assistant'], module: 'saas' },
  { href: '/pro/facturation', label: 'Facturation', icon: '💳', roles: ['administrateur', 'secretaire'], module: 'saas' },
  { href: '/pro/statistiques', label: 'Statistiques', icon: '📈', roles: ['administrateur'], module: 'saas' },
  { href: '/pro/administration', label: 'Équipe du cabinet', icon: '⚙️', roles: ['administrateur'] },
  { href: '/pro/cabinets', label: 'Fiches annuaire', icon: '🏥', roles: ['super_admin'] },
  { href: '/pro/revendications', label: 'Revendications', icon: '✅', roles: ['super_admin'] },
];

export function Sidebar() {
  const pathname = usePathname();
  const { user, logout } = useAuth();

  const items = NAV.filter((item) => user && item.roles.includes(user.role) && (!item.module || MODULES[item.module]));

  return (
    <aside className="flex w-full flex-col border-b border-slate-200 bg-white md:h-screen md:w-64 md:border-b-0 md:border-r">
      <div className="border-b border-slate-200 px-5 py-4">
        <Link href="/" className="text-lg font-bold text-teal-700">DOKTIS</Link>
        <p className="truncate text-xs text-slate-500">{user?.cabinet ? user.cabinet.nom : 'Espace praticien'}</p>
      </div>
      <nav className="flex gap-1 overflow-x-auto p-3 md:flex-1 md:flex-col md:overflow-y-auto">
        {items.map((item) => {
          const active = item.href === '/pro' ? pathname === '/pro' : pathname.startsWith(item.href);
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex shrink-0 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                active ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              <span aria-hidden>{item.icon}</span>
              {item.label}
            </Link>
          );
        })}
        <button
          onClick={() => logout()}
          className="shrink-0 rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-50 md:hidden"
        >
          Déconnexion
        </button>
      </nav>
      <div className="hidden border-t border-slate-200 p-3 md:block">
        <p className="truncate px-2 text-sm font-medium text-slate-700">{user?.name}</p>
        <p className="truncate px-2 text-xs text-slate-400">{user?.role}</p>
        <button
          onClick={() => logout()}
          className="mt-2 w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-500 hover:bg-slate-50"
        >
          Déconnexion
        </button>
      </div>
    </aside>
  );
}
