'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/lib/auth-context';

const NAV = [
  { href: '/', label: 'Tableau de bord', icon: '📊', roles: ['administrateur', 'medecin', 'secretaire', 'assistant'] },
  { href: '/patients', label: 'Patients', icon: '🧑‍🤝‍🧑', roles: ['administrateur', 'medecin', 'secretaire', 'assistant'] },
  { href: '/agenda', label: 'Agenda', icon: '📅', roles: ['administrateur', 'medecin', 'secretaire', 'assistant'] },
  { href: '/consultations', label: 'Consultations', icon: '🩺', roles: ['administrateur', 'medecin', 'assistant'] },
  { href: '/facturation', label: 'Facturation', icon: '💳', roles: ['administrateur', 'secretaire'] },
  { href: '/statistiques', label: 'Statistiques', icon: '📈', roles: ['administrateur'] },
  { href: '/administration', label: 'Équipe du cabinet', icon: '⚙️', roles: ['administrateur'] },
  { href: '/cabinets', label: 'Cabinets DOKTA', icon: '🏥', roles: ['super_admin'] },
];

export function Sidebar() {
  const pathname = usePathname();
  const { user, logout } = useAuth();

  const items = NAV.filter((item) => user && item.roles.includes(user.role));

  return (
    <aside className="flex h-screen w-64 flex-col border-r border-slate-200 bg-white">
      <div className="border-b border-slate-200 px-5 py-4">
        <p className="text-lg font-bold text-teal-700">DOKTA</p>
        {user?.cabinet && <p className="truncate text-xs text-slate-500">{user.cabinet.nom}</p>}
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto p-3">
        {items.map((item) => {
          const active = pathname === item.href;
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                active ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              <span aria-hidden>{item.icon}</span>
              {item.label}
            </Link>
          );
        })}
      </nav>
      <div className="border-t border-slate-200 p-3">
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
