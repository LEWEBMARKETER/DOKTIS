'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { usePatientAuth } from '@/lib/patient-auth-context';
import { PatientSidebar } from '@/components/patient-sidebar';

export default function PatientAppLayout({ children }: { children: React.ReactNode }) {
  const { account, loading } = usePatientAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !account) {
      router.replace('/patient/login');
    }
  }, [loading, account, router]);

  if (loading || !account) {
    return <div className="flex min-h-screen items-center justify-center text-slate-400">Chargement…</div>;
  }

  return (
    <div className="flex min-h-screen">
      <PatientSidebar />
      <main className="flex-1 overflow-y-auto p-8">{children}</main>
    </div>
  );
}
