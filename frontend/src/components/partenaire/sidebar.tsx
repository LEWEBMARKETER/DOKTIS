'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/lib/auth-context';

const NAV = [
  { href: '/partenaire/tableau-de-bord', label: 'Tableau de bord', icon: '📊' },
  { href: '/partenaire/ma-fiche', label: 'Ma fiche', icon: '📋' },
  { href: '/partenaire/rendez-vous', label: 'Rendez-vous', icon: '📅' },
  { href: '/partenaire/statistiques', label: 'Statistiques', icon: '📈' },
  { href: '/partenaire/abonnement', label: 'Abonnement', icon: '💳' },
];

export function PartenaireSidebar() {
  const pathname = usePathname();
  const { user, logout } = useAuth();

  return (
    <aside className="flex h-screen w-64 flex-col border-r border-slate-200 bg-white">
      <div className="border-b border-slate-200 px-5 py-4">
        <p className="text-lg font-bold text-doktis-petrol">DOKTIS</p>
        <p className="text-xs text-slate-500">Espace partenaire</p>
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto p-3">
        {NAV.map((item) => {
          const active = pathname === item.href;
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ${
                active ? 'bg-doktis-petrol/10 text-doktis-petrol' : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              <span>{item.icon}</span>
              {item.label}
            </Link>
          );
        })}
      </nav>
      <div className="border-t border-slate-200 p-3">
        <p className="truncate px-3 text-xs text-slate-400">{user?.cabinet?.nom}</p>
        <button onClick={() => logout()} className="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-50">
          Déconnexion
        </button>
      </div>
    </aside>
  );
}
