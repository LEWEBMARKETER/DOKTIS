'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, Card, ErrorText, Field, Input, PageHeader, Textarea } from '@/components/ui';
import type { AdminFiche } from '@/types';

const STATUT_PUB_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red'> = {
  brouillon: 'slate', publiee: 'green', suspendue: 'amber', archivee: 'red',
};

const EMPTY = { description: '', telephone: '', email: '', adresse: '', ville: '', quartier: '' };

export default function MaFichePage() {
  const [fiche, setFiche] = useState<AdminFiche | null>(null);
  const [form, setForm] = useState(EMPTY);
  const [error, setError] = useState<string | null>(null);
  const [succes, setSucces] = useState(false);
  const [envoi, setEnvoi] = useState(false);

  useEffect(() => {
    api.get<AdminFiche>('/partenaire/ma-fiche').then((data) => {
      setFiche(data);
      setForm({
        description: data.description ?? '',
        telephone: data.telephone ?? '',
        email: data.email ?? '',
        adresse: data.adresse ?? '',
        ville: data.ville ?? '',
        quartier: data.quartier ?? '',
      });
    });
  }, []);

  function update<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (envoi) return;
    setError(null);
    setSucces(false);
    setEnvoi(true);
    try {
      await api.post('/partenaire/ma-fiche/demande-modification', { champs: form });
      setSucces(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible d'envoyer la demande.");
    } finally {
      setEnvoi(false);
    }
  }

  if (!fiche) return <p className="text-slate-400">Chargement…</p>;

  return (
    <div>
      <PageHeader
        title={fiche.nom}
        description="Votre fiche publique DOKTIS Directory."
        actions={<Badge tone={STATUT_PUB_TONE[fiche.statut_publication]}>{fiche.statut_publication}</Badge>}
      />

      <Card className="mb-6">
        <p className="text-sm text-slate-500">
          Les informations vérifiées ne peuvent pas être modifiées directement : proposez un changement ci-dessous,
          un administrateur DOKTIS le validera avant mise à jour de votre fiche publique.
        </p>
      </Card>

      <Card>
        <h2 className="mb-4 font-semibold text-slate-900">Proposer une modification</h2>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          {succes && <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Demande envoyée, en attente de validation.</p>}
          <Field label="Description">
            <Textarea rows={3} value={form.description} onChange={(e) => update('description', e.target.value)} />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Téléphone">
              <Input value={form.telephone} onChange={(e) => update('telephone', e.target.value)} />
            </Field>
            <Field label="Email">
              <Input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} />
            </Field>
          </div>
          <Field label="Adresse">
            <Input value={form.adresse} onChange={(e) => update('adresse', e.target.value)} />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Ville">
              <Input value={form.ville} onChange={(e) => update('ville', e.target.value)} />
            </Field>
            <Field label="Quartier">
              <Input value={form.quartier} onChange={(e) => update('quartier', e.target.value)} />
            </Field>
          </div>
          <Button type="submit" disabled={envoi}>
            {envoi ? 'Envoi…' : 'Envoyer la demande de modification'}
          </Button>
        </form>
      </Card>
    </div>
  );
}
