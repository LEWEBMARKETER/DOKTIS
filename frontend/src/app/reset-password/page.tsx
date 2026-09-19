'use client';

import { Suspense, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { api, ApiError } from '@/lib/api';
import { Button, Card, ErrorText, Field, Input } from '@/components/ui';

function ResetPasswordForm() {
  const params = useSearchParams();
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await api.post('/auth/reinitialiser-mot-de-passe', {
        token: params.get('token'), email: params.get('email'), password,
        password_confirmation: confirmation,
      });
      setDone(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Réinitialisation impossible.');
    }
  }

  return (
    <Card className="w-full max-w-sm">
      <h1 className="mb-5 text-xl font-bold text-teal-700">Nouveau mot de passe</h1>
      {done ? <Link href="/login" className="text-sm font-medium text-teal-600 hover:underline">Mot de passe modifié — se connecter</Link> : (
        <form onSubmit={submit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <Field label="Nouveau mot de passe"><Input type="password" minLength={8} required value={password} onChange={(e) => setPassword(e.target.value)} /></Field>
          <Field label="Confirmation"><Input type="password" minLength={8} required value={confirmation} onChange={(e) => setConfirmation(e.target.value)} /></Field>
          <Button type="submit">Modifier le mot de passe</Button>
        </form>
      )}
    </Card>
  );
}

export default function ResetPasswordPage() {
  return <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4"><Suspense><ResetPasswordForm /></Suspense></div>;
}
