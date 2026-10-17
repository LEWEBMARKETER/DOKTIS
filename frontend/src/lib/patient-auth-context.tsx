'use client';

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { patientApi } from '@/lib/patient-api';
import type { PatientAccountType } from '@/types/patient';

interface RegisterPayload {
  nom: string;
  prenom: string;
  email: string;
  telephone: string;
  password: string;
  date_naissance?: string;
  sexe?: 'M' | 'F' | '';
}

interface PatientAuthContextValue {
  account: PatientAccountType | null;
  loading: boolean;
  login: (identifiant: string, password: string) => Promise<void>;
  register: (data: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
}

const PatientAuthContext = createContext<PatientAuthContextValue | null>(null);

export function PatientAuthProvider({ children }: { children: ReactNode }) {
  const [account, setAccount] = useState<PatientAccountType | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  const refresh = useCallback(async () => {
    // L'identité est portée par le cookie de session HttpOnly (garde
    // "patient", distinct du garde "web" de DOKTIS Office) : on ne peut
    // savoir si une session est active qu'en interrogeant l'API.
    try {
      const me = await patientApi.get<PatientAccountType>('/patient/auth/me');
      setAccount(me);
    } catch {
      setAccount(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // Migration contrôlée : purge l'ancien jeton Bearer patient (ancienne
    // authentification localStorage) sans jamais le transmettre nulle part.
    try {
      window.localStorage.removeItem('dokta_patient_token');
    } catch {
      // ignore (stockage indisponible)
    }
    refresh();
  }, [refresh]);

  const login = useCallback(
    async (identifiant: string, password: string) => {
      const res = await patientApi.post<{ account: PatientAccountType }>('/patient/auth/login', {
        identifiant,
        password,
      });
      setAccount(res.account);
      router.push('/patient');
    },
    [router],
  );

  const register = useCallback(
    async (data: RegisterPayload) => {
      const res = await patientApi.post<{ account: PatientAccountType }>('/patient/auth/register', data);
      setAccount(res.account);
      router.push('/patient');
    },
    [router],
  );

  const logout = useCallback(async () => {
    try {
      await patientApi.post('/patient/auth/logout');
    } catch {
      // ignore
    }
    setAccount(null);
    router.push('/patient/login');
  }, [router]);

  return (
    <PatientAuthContext.Provider value={{ account, loading, login, register, logout, refresh }}>
      {children}
    </PatientAuthContext.Provider>
  );
}

export function usePatientAuth(): PatientAuthContextValue {
  const ctx = useContext(PatientAuthContext);
  if (!ctx) throw new Error('usePatientAuth doit être utilisé dans un PatientAuthProvider');
  return ctx;
}
