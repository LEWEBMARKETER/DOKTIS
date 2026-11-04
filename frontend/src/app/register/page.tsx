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
    cabinet_whatsapp: '',
    cabinet_ville: '',
    cabinet_quartier: '',
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
      router.push('/pro');
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
          <h1 className="text-2xl font-bold text-teal-700">DOKTIS</h1>
          <p className="text-sm text-slate-500">
            Inscrivez votre cabinet dans l&apos;annuaire, gratuitement. La fiche est publiée après vérification par l&apos;équipe DOKTIS.
          </p>
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
            <Input value={form.cabinet_telephone} onChange={(e) => update('cabinet_telephone', e.target.value)} placeholder="+241 77 12 34 56" />
          </Field>
          <Field label="Numéro WhatsApp pour les demandes de rendez-vous">
            <Input value={form.cabinet_whatsapp} onChange={(e) => update('cabinet_whatsapp', e.target.value)} placeholder="+241 77 12 34 56" />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Ville">
              <Input required value={form.cabinet_ville} onChange={(e) => update('cabinet_ville', e.target.value)} placeholder="Libreville" />
            </Field>
            <Field label="Quartier">
              <Input value={form.cabinet_quartier} onChange={(e) => update('cabinet_quartier', e.target.value)} placeholder="Glass" />
            </Field>
          </div>
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
            {loading ? 'Inscription…' : 'Inscrire mon cabinet'}
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
