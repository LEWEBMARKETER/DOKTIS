'use client';

import { Suspense, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { Button, Card, ErrorText, Field, Input } from '@/components/ui';

function ActivationForm() {
  const router = useRouter();
  const { refresh } = useAuth();
  const searchParams = useSearchParams();
  const invitation = searchParams.get('invitation');
  const jeton = searchParams.get('jeton');

  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  if (!invitation || !jeton) {
    return <ErrorText>Lien d&apos;activation invalide : paramètres manquants.</ErrorText>;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await api.post('/partenaire/auth/activer', { invitation: Number(invitation), jeton, password });
      await refresh();
      router.push('/partenaire/tableau-de-bord');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Ce lien d'invitation est invalide ou a expiré.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <ErrorText>{error}</ErrorText>
      <Field label="Choisissez votre mot de passe">
        <Input type="password" required minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} placeholder="8 caractères minimum" />
      </Field>
      <Button type="submit" disabled={loading}>
        {loading ? 'Activation…' : 'Activer mon compte'}
      </Button>
    </form>
  );
}

export default function ActiverPartenairePage() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-doktis-blanc px-4">
      <Card className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold text-doktis-petrol">DOKTIS</h1>
          <p className="text-sm text-slate-500">Activez votre espace partenaire</p>
        </div>
        <Suspense fallback={<p className="text-center text-slate-400">Chargement…</p>}>
          <ActivationForm />
        </Suspense>
      </Card>
    </div>
  );
}
