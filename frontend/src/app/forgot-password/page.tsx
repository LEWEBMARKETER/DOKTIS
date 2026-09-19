'use client';

import { useState } from 'react';
import Link from 'next/link';
import { api, ApiError } from '@/lib/api';
import { Button, Card, ErrorText, Field, Input } from '@/components/ui';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const response = await api.post<{ message: string }>('/auth/mot-de-passe-oublie', { email });
      setMessage(response.message);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Demande impossible.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <Card className="w-full max-w-sm">
        <h1 className="mb-1 text-xl font-bold text-teal-700">Mot de passe oublié</h1>
        <p className="mb-5 text-sm text-slate-500">Saisissez l’adresse de votre compte DOKTA Office.</p>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          {message && <p className="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{message}</p>}
          <Field label="Adresse email"><Input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
          <Button type="submit" disabled={loading}>{loading ? 'Envoi…' : 'Envoyer le lien'}</Button>
        </form>
        <Link href="/login" className="mt-4 block text-center text-sm text-teal-600 hover:underline">Retour à la connexion</Link>
      </Card>
    </div>
  );
}
