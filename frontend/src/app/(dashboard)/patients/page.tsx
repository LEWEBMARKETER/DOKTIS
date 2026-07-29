'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, ApiError } from '@/lib/api';
import { Button, Card, ErrorText, Field, Input, Modal, PageHeader, Select } from '@/components/ui';
import type { Paginated, Patient } from '@/types';

const EMPTY_FORM = {
  nom: '',
  prenom: '',
  date_naissance: '',
  sexe: '',
  telephone: '',
  email: '',
  adresse: '',
};

export default function PatientsPage() {
  const [patients, setPatients] = useState<Patient[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function load(recherche = '') {
    setLoading(true);
    try {
      const res = await api.get<Paginated<Patient>>(`/patients${recherche ? `?recherche=${encodeURIComponent(recherche)}` : ''}`);
      setPatients(res.data);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const timeout = setTimeout(() => load(search), search ? 300 : 0);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  function update<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post('/patients', form);
      setModalOpen(false);
      setForm(EMPTY_FORM);
      load(search);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de créer le patient.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader
        title="Patients"
        description="Dossiers patients du cabinet."
        actions={<Button onClick={() => setModalOpen(true)}>+ Nouveau patient</Button>}
      />

      <Input
        placeholder="Rechercher par nom, prénom, téléphone ou n° de dossier…"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="mb-4 w-full max-w-md"
      />

      <Card className="overflow-x-auto p-0">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">N° dossier</th>
              <th className="px-4 py-3">Nom</th>
              <th className="px-4 py-3">Téléphone</th>
              <th className="px-4 py-3">Naissance</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-slate-400">Chargement…</td>
              </tr>
            )}
            {!loading && patients.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-slate-400">Aucun patient trouvé.</td>
              </tr>
            )}
            {patients.map((p) => (
              <tr key={p.id} className="hover:bg-slate-50">
                <td className="px-4 py-3 text-slate-500">{p.numero_dossier}</td>
                <td className="px-4 py-3">
                  <Link href={`/patients/${p.id}`} className="font-medium text-teal-700 hover:underline">
                    {p.prenom} {p.nom}
                  </Link>
                </td>
                <td className="px-4 py-3">{p.telephone ?? '—'}</td>
                <td className="px-4 py-3">{p.date_naissance ? new Date(p.date_naissance).toLocaleDateString('fr-FR') : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nouveau patient">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Nom">
              <Input required value={form.nom} onChange={(e) => update('nom', e.target.value)} />
            </Field>
            <Field label="Prénom">
              <Input required value={form.prenom} onChange={(e) => update('prenom', e.target.value)} />
            </Field>
            <Field label="Date de naissance">
              <Input type="date" value={form.date_naissance} onChange={(e) => update('date_naissance', e.target.value)} />
            </Field>
            <Field label="Sexe">
              <Select value={form.sexe} onChange={(e) => update('sexe', e.target.value)}>
                <option value="">—</option>
                <option value="M">Masculin</option>
                <option value="F">Féminin</option>
              </Select>
            </Field>
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
          <Button type="submit" disabled={saving}>
            {saving ? 'Création…' : 'Créer le patient'}
          </Button>
        </form>
      </Modal>
    </div>
  );
}
