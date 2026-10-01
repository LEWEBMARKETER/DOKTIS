'use client';

import { useEffect, useState } from 'react';
import { patientApi, ApiError } from '@/lib/patient-api';
import { usePatientAuth } from '@/lib/patient-auth-context';
import { Badge, Button, Card, ErrorText, Field, Input, PageHeader, Select } from '@/components/ui';
import type { PatientAccountType, PatientAvis } from '@/types/patient';

export default function ProfilPage() {
  const { account, refresh } = usePatientAuth();
  const [form, setForm] = useState({
    nom: '',
    prenom: '',
    date_naissance: '',
    sexe: '' as 'M' | 'F' | '',
    contact_urgence_nom: '',
    contact_urgence_telephone: '',
  });
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [saving, setSaving] = useState(false);
  const [avis, setAvis] = useState<PatientAvis[]>([]);

  useEffect(() => {
    if (!account) return;
    setForm({
      nom: account.nom,
      prenom: account.prenom,
      date_naissance: account.date_naissance?.slice(0, 10) ?? '',
      sexe: account.sexe ?? '',
      contact_urgence_nom: account.contact_urgence_nom ?? '',
      contact_urgence_telephone: account.contact_urgence_telephone ?? '',
    });
  }, [account]);

  useEffect(() => {
    patientApi.get<PatientAvis[]>('/patient/mes-avis').then(setAvis);
  }, []);

  function update<K extends keyof typeof form>(key: K, value: typeof form[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSuccess(false);
    setSaving(true);
    try {
      await patientApi.patch<PatientAccountType>('/patient/profil', form);
      await refresh();
      setSuccess(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Mise à jour impossible.');
    } finally {
      setSaving(false);
    }
  }

  async function supprimerAvis(cabinetId: number) {
    await patientApi.delete(`/patient/cabinets/${cabinetId}/avis`);
    setAvis((a) => a.filter((item) => item.cabinet_id !== cabinetId));
  }

  return (
    <div>
      <PageHeader title="Mon profil" description="Gérez vos informations personnelles." />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 font-semibold text-slate-900">Informations personnelles</h2>
          <p className="mb-4 text-sm text-slate-500">
            {account?.email} · {account?.telephone}
          </p>
          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <ErrorText>{error}</ErrorText>
            {success && <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Profil mis à jour.</p>}
            <div className="grid grid-cols-2 gap-3">
              <Field label="Nom">
                <Input value={form.nom} onChange={(e) => update('nom', e.target.value)} />
              </Field>
              <Field label="Prénom">
                <Input value={form.prenom} onChange={(e) => update('prenom', e.target.value)} />
              </Field>
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
            <Field label="Contact d'urgence — nom">
              <Input value={form.contact_urgence_nom} onChange={(e) => update('contact_urgence_nom', e.target.value)} />
            </Field>
            <Field label="Contact d'urgence — téléphone">
              <Input value={form.contact_urgence_telephone} onChange={(e) => update('contact_urgence_telephone', e.target.value)} />
            </Field>
            <Button type="submit" disabled={saving}>
              {saving ? 'Enregistrement…' : 'Enregistrer'}
            </Button>
          </form>
        </Card>

        <Card>
          <h2 className="mb-3 font-semibold text-slate-900">Mes avis</h2>
          {avis.length === 0 && <p className="text-sm text-slate-400">Vous n&apos;avez laissé aucun avis.</p>}
          <ul className="space-y-3">
            {avis.map((a) => (
              <li key={a.id} className="border-b border-slate-100 pb-3 last:border-0">
                <div className="flex items-center justify-between">
                  <p className="font-medium text-slate-800">{a.cabinet.nom}</p>
                  <Badge tone="amber">{'★'.repeat(a.note)}</Badge>
                </div>
                {a.commentaire && <p className="mt-1 text-sm text-slate-600">{a.commentaire}</p>}
                <button onClick={() => supprimerAvis(a.cabinet_id)} className="mt-1 text-xs text-red-600 hover:underline">
                  Supprimer
                </button>
              </li>
            ))}
          </ul>
        </Card>
      </div>
    </div>
  );
}
