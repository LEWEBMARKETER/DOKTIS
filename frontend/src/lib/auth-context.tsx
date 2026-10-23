'use client';

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import type { User } from '@/types';

interface AuthContextValue {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  const refresh = useCallback(async () => {
    // L'identité est portée par le cookie de session HttpOnly (plus de
    // jeton côté client) : on ne peut savoir si une session est active
    // qu'en interrogeant l'API.
    try {
      const me = await api.get<User>('/auth/me');
      setUser(me);
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // Migration contrôlée : purge les anciens jetons Bearer (ancienne
    // authentification localStorage) sans jamais les transmettre nulle part.
    try {
      window.localStorage.removeItem('dokta_token');
    } catch {
      // ignore (stockage indisponible)
    }
    refresh();
  }, [refresh]);

  const login = useCallback(
    async (email: string, password: string) => {
      const res = await api.post<{ user: User }>('/auth/login', { email, password });
      setUser(res.user);
      // Un seul endpoit de connexion sert le personnel Office, le super
      // admin ET les partenaires Directory (même garde "web") : la
      // destination dépend du rôle renvoyé.
      if (res.user.role === 'super_admin') router.push('/admin/tableau-de-bord');
      else if (res.user.role === 'partenaire') router.push('/partenaire/tableau-de-bord');
      else router.push('/office');
    },
    [router],
  );

  const logout = useCallback(async () => {
    const role = user?.role;
    try {
      await api.post('/auth/logout');
    } catch {
      // ignore
    }
    setUser(null);
    router.push(role === 'partenaire' ? '/partenaire/connexion' : '/office/login');
  }, [router, user]);

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, refresh }}>{children}</AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth doit être utilisé dans un AuthProvider');
  return ctx;
}
