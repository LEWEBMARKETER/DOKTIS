'use client';

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { patientApi, getPatientToken, setPatientToken } from '@/lib/patient-api';
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
    if (!getPatientToken()) {
      setAccount(null);
      setLoading(false);
      return;
    }

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
    refresh();
  }, [refresh]);

  const login = useCallback(
    async (identifiant: string, password: string) => {
      const res = await patientApi.post<{ account: PatientAccountType; token: string }>('/patient/auth/login', {
        identifiant,
        password,
      });
      setPatientToken(res.token);
      setAccount(res.account);
      router.push('/patient');
    },
    [router],
  );

  const register = useCallback(
    async (data: RegisterPayload) => {
      const res = await patientApi.post<{ account: PatientAccountType; token: string }>('/patient/auth/register', data);
      setPatientToken(res.token);
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
    setPatientToken(null);
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
