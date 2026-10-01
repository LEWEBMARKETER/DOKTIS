'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePatientAuth } from '@/lib/patient-auth-context';
import { ApiError } from '@/lib/api';
import { Button, Card, ErrorText, Field, Input, Select } from '@/components/ui';

const EMPTY_FORM = {
  nom: '',
  prenom: '',
  email: '',
  telephone: '',
  password: '',
  date_naissance: '',
  sexe: '' as 'M' | 'F' | '',
};

export default function PatientRegisterPage() {
  const { register } = usePatientAuth();
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  function update<K extends keyof typeof form>(key: K, value: typeof form[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await register(form);
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
          <p className="text-sm text-slate-500">Créez votre compte patient</p>
        </div>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Nom">
              <Input required value={form.nom} onChange={(e) => update('nom', e.target.value)} />
            </Field>
            <Field label="Prénom">
              <Input required value={form.prenom} onChange={(e) => update('prenom', e.target.value)} />
            </Field>
          </div>
          <Field label="Email">
            <Input type="email" required value={form.email} onChange={(e) => update('email', e.target.value)} />
          </Field>
          <Field label="Téléphone">
            <Input required value={form.telephone} onChange={(e) => update('telephone', e.target.value)} placeholder="690112233" />
          </Field>
          <Field label="Mot de passe">
            <Input
              type="password"
              required
              minLength={8}
              value={form.password}
              onChange={(e) => update('password', e.target.value)}
              placeholder="8 caractères minimum"
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Date de naissance">
              <Input type="date" value={form.date_naissance} onChange={(e) => update('date_naissance', e.target.value)} />
            </Field>
            <Field label="Sexe">
              <Select value={form.sexe} onChange={(e) => update('sexe', e.target.value as 'M' | 'F' | '')}>
                <option value="">—</option>
                <option value="M">Masculin</option>
                <option value="F">Féminin</option>
              </Select>
            </Field>
          </div>
          <Button type="submit" disabled={loading}>
            {loading ? 'Création…' : 'Créer mon compte'}
          </Button>
        </form>
        <p className="mt-4 text-center text-sm text-slate-500">
          Déjà un compte ?{' '}
          <Link href="/patient/login" className="font-medium text-teal-600 hover:underline">
            Se connecter
          </Link>
        </p>
      </Card>
    </div>
  );
}
