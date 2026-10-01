'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { usePatientAuth } from '@/lib/patient-auth-context';

const NAV = [
  { href: '/patient', label: 'Tableau de bord', icon: '🏠' },
  { href: '/patient/rendez-vous', label: 'Rendez-vous', icon: '📅' },
  { href: '/patient/dossier', label: 'Dossier médical', icon: '📋' },
  { href: '/patient/messagerie', label: 'Messagerie', icon: '💬' },
  { href: '/patient/profil', label: 'Mon profil', icon: '🙍' },
];

export function PatientSidebar() {
  const pathname = usePathname();
  const { account, logout } = usePatientAuth();

  return (
    <aside className="flex h-screen w-64 flex-col border-r border-slate-200 bg-white">
      <div className="border-b border-slate-200 px-5 py-4">
        <p className="text-lg font-bold text-teal-700">DOKTA</p>
        <p className="text-xs text-slate-500">Espace patient</p>
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto p-3">
        {NAV.map((item) => {
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
        <p className="truncate px-2 text-sm font-medium text-slate-700">
          {account?.prenom} {account?.nom}
        </p>
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
