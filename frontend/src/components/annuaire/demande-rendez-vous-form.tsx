'use client';

import { useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { Button, ErrorText, Field, Input, Select, Textarea } from '@/components/ui';
import type { DirectoryCabinet } from '@/types';

const EMPTY = { nom: '', prenom: '', telephone: '', email: '', date_souhaitee: '', creneau_souhaite: '', praticien_id: '', motif: '' };

export function DemandeRendezVousForm({ cabinet }: { cabinet: DirectoryCabinet }) {
  const [form, setForm] = useState(EMPTY);
  const [error, setError] = useState<string | null>(null);
  const [envoyee, setEnvoyee] = useState(false);
  const [envoi, setEnvoi] = useState(false);

  function update<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (envoi) return;
    setError(null);
    setEnvoi(true);
    try {
      await api.post(`/directory/cabinets/${cabinet.id}/demandes-rendez-vous`, {
        ...form,
        praticien_id: form.praticien_id ? Number(form.praticien_id) : undefined,
      });
      setEnvoyee(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Votre demande n'a pas pu être envoyée, réessayez.");
    } finally {
      setEnvoi(false);
    }
  }

  if (envoyee) {
    return (
      <p className="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        Votre demande a bien été envoyée. {cabinet.nom} vous recontactera pour la confirmer.
      </p>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3">
      <ErrorText>{error}</ErrorText>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Nom">
          <Input required value={form.nom} onChange={(e) => update('nom', e.target.value)} />
        </Field>
        <Field label="Prénom">
          <Input required value={form.prenom} onChange={(e) => update('prenom', e.target.value)} />
        </Field>
      </div>
      <Field label="Téléphone">
        <Input required value={form.telephone} onChange={(e) => update('telephone', e.target.value)} placeholder="074112233" />
      </Field>
      <Field label="Email (facultatif)">
        <Input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} />
      </Field>
      {!!cabinet.users?.length && (
        <Field label="Praticien (facultatif)">
          <Select value={form.praticien_id} onChange={(e) => update('praticien_id', e.target.value)}>
            <option value="">Peu importe</option>
            {cabinet.users.map((u) => (
              <option key={u.id} value={u.id}>{u.name}</option>
            ))}
          </Select>
        </Field>
      )}
      <div className="grid grid-cols-2 gap-3">
        <Field label="Date souhaitée">
          <Input type="date" required min={new Date().toISOString().slice(0, 10)} value={form.date_souhaitee} onChange={(e) => update('date_souhaitee', e.target.value)} />
        </Field>
        <Field label="Créneau souhaité">
          <Select value={form.creneau_souhaite} onChange={(e) => update('creneau_souhaite', e.target.value)}>
            <option value="">Peu importe</option>
            <option value="matin">Matin</option>
            <option value="apres-midi">Après-midi</option>
          </Select>
        </Field>
      </div>
      <Field label="Motif (facultatif)">
        <Textarea rows={2} value={form.motif} onChange={(e) => update('motif', e.target.value)} placeholder="Décrivez brièvement le motif, sans détail médical sensible." />
      </Field>
      <Button type="submit" disabled={envoi}>
        {envoi ? 'Envoi…' : 'Envoyer la demande'}
      </Button>
      <p className="text-xs text-slate-400">
        Cette demande sera transmise à {cabinet.nom}, qui la confirmera ou proposera un autre créneau. Aucun compte n&apos;est créé.
      </p>
    </form>
  );
}
