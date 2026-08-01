'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api, ApiError, setToken } from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { Button, Card, ErrorText, Field, Input, Select } from '@/components/ui';
import type { Cabinet, User } from '@/types';

export default function RegisterPage() {
  const router = useRouter();
  const { refresh } = useAuth();
  const [form, setForm] = useState({
    cabinet_nom: '',
    cabinet_type: 'mixte',
    cabinet_telephone: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
  });
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  function update<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const res = await api.post<{ cabinet: Cabinet; user: User; token: string }>('/auth/register-cabinet', form);
      setToken(res.token);
      await refresh();
      router.push('/');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Inscription impossible.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
      <Card className="w-full max-w-md">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold text-teal-700">DOKTA</h1>
          <p className="text-sm text-slate-500">Créez le compte de votre cabinet (30 jours d&apos;essai gratuit)</p>
        </div>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <Field label="Nom du cabinet">
            <Input required value={form.cabinet_nom} onChange={(e) => update('cabinet_nom', e.target.value)} placeholder="Cabinet Dentaire Étoile" />
          </Field>
          <Field label="Type de cabinet">
            <Select value={form.cabinet_type} onChange={(e) => update('cabinet_type', e.target.value)}>
              <option value="dentaire">Dentaire</option>
              <option value="medical">Médical</option>
              <option value="mixte">Mixte</option>
            </Select>
          </Field>
          <Field label="Téléphone du cabinet">
            <Input value={form.cabinet_telephone} onChange={(e) => update('cabinet_telephone', e.target.value)} placeholder="+237 6XX XX XX XX" />
          </Field>
          <hr className="border-slate-200" />
          <Field label="Votre nom complet">
            <Input required value={form.admin_name} onChange={(e) => update('admin_name', e.target.value)} placeholder="Dr Alice Kamga" />
          </Field>
          <Field label="Adresse email">
            <Input type="email" required value={form.admin_email} onChange={(e) => update('admin_email', e.target.value)} placeholder="vous@cabinet.dokta" />
          </Field>
          <Field label="Mot de passe">
            <Input type="password" required minLength={8} value={form.admin_password} onChange={(e) => update('admin_password', e.target.value)} placeholder="8 caractères minimum" />
          </Field>
          <Button type="submit" disabled={loading}>
            {loading ? 'Création…' : 'Créer mon cabinet'}
          </Button>
        </form>
        <p className="mt-4 text-center text-sm text-slate-500">
          Déjà inscrit ?{' '}
          <Link href="/login" className="font-medium text-teal-600 hover:underline">
            Se connecter
          </Link>
        </p>
      </Card>
    </div>
  );
}
