'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePatientAuth } from '@/lib/patient-auth-context';
import { ApiError } from '@/lib/api';
import { Button, Card, ErrorText, Field, Input } from '@/components/ui';

export default function PatientLoginPage() {
  const { login } = usePatientAuth();
  const [identifiant, setIdentifiant] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await login(identifiant, password);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Connexion impossible.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <Card className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold text-teal-700">DOKTA</h1>
          <p className="text-sm text-slate-500">Connectez-vous à votre espace patient</p>
        </div>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <Field label="Email ou téléphone">
            <Input required value={identifiant} onChange={(e) => setIdentifiant(e.target.value)} placeholder="vous@email.com" />
          </Field>
          <Field label="Mot de passe">
            <Input type="password" required value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" />
          </Field>
          <Button type="submit" disabled={loading}>
            {loading ? 'Connexion…' : 'Se connecter'}
          </Button>
        </form>
        <p className="mt-4 text-center text-sm text-slate-500">
          Pas encore de compte ?{' '}
          <Link href="/patient/register" className="font-medium text-teal-600 hover:underline">
            Créer un compte
          </Link>
        </p>
        <p className="mt-2 text-center text-sm text-slate-400">
          <Link href="/directory" className="hover:underline">
            ← Retour à l&apos;annuaire
          </Link>
        </p>
      </Card>
    </div>
  );
}
