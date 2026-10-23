'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/lib/auth-context';
import { PartenaireSidebar } from '@/components/partenaire/sidebar';

export default function PartenaireAppLayout({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace('/partenaire/connexion');
      return;
    }
    if (user.role !== 'partenaire') {
      router.replace('/');
    }
  }, [loading, user, router]);

  if (loading || !user || user.role !== 'partenaire') {
    return (
      <div className="flex min-h-screen items-center justify-center text-slate-400">
        Chargement…
      </div>
    );
  }

  return (
    <div className="flex min-h-screen">
      <PartenaireSidebar />
      <main className="flex-1 overflow-y-auto bg-slate-50 p-8">{children}</main>
    </div>
  );
}
