'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, ErrorText, Field, Input, Modal, PageHeader, Select } from '@/components/ui';
import type { AdminFiche, CategorieFiche, Paginated } from '@/types';

const STATUT_PUB_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red'> = {
  brouillon: 'slate',
  publiee: 'green',
  suspendue: 'amber',
  archivee: 'red',
};

const STATUT_VERIF_LABEL: Record<string, string> = {
  non_verifiee: 'Non vérifiée',
  en_attente_verification: 'En attente',
  verifiee: 'Vérifiée',
};

interface FichesAdminPanelProps {
  categorieFixe?: CategorieFiche;
  titre: string;
  description: string;
}

const EMPTY_FORM = { nom: '', categorie_fiche: 'structure' as CategorieFiche, type: 'mixte', ville: '', telephone: '', email: '' };

export function FichesAdminPanel({ categorieFixe, titre, description }: FichesAdminPanelProps) {
  const [fiches, setFiches] = useState<AdminFiche[]>([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ ...EMPTY_FORM, categorie_fiche: categorieFixe ?? 'structure' });
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function load() {
    setLoading(true);
    const params = new URLSearchParams();
    if (categorieFixe) params.set('categorie_fiche', categorieFixe);
    api
      .get<Paginated<AdminFiche>>(`/admin/fiches?${params.toString()}`)
      .then((res) => setFiches(res.data))
      .finally(() => setLoading(false));
  }

  useEffect(load, [categorieFixe]);

  function update<K extends keyof typeof form>(key: K, value: typeof form[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post('/admin/fiches', form);
      setModalOpen(false);
      setForm({ ...EMPTY_FORM, categorie_fiche: categorieFixe ?? 'structure' });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de créer la fiche.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader title={titre} description={description} actions={<Button onClick={() => setModalOpen(true)}>+ Nouvelle fiche</Button>} />

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Nom</th>
              <th className="px-4 py-3">Catégorie</th>
              <th className="px-4 py-3">Vérification</th>
              <th className="px-4 py-3">Publication</th>
              <th className="px-4 py-3">Ville</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading && (
              <tr><td colSpan={5} className="px-4 py-6 text-center text-slate-400">Chargement…</td></tr>
            )}
            {!loading && fiches.length === 0 && (
              <tr><td colSpan={5} className="px-4 py-6 text-center text-slate-400">Aucune fiche.</td></tr>
            )}
            {fiches.map((f) => (
              <tr key={f.id} className="hover:bg-slate-50">
                <td className="px-4 py-3">
                  <Link href={`/admin/fiches/${f.id}`} className="font-medium text-doktis-petrol hover:underline">
                    {f.nom}
                  </Link>
                </td>
                <td className="px-4 py-3">{f.categorie_fiche === 'structure' ? 'Structure' : 'Professionnel'}</td>
                <td className="px-4 py-3">{STATUT_VERIF_LABEL[f.statut_verification]}</td>
                <td className="px-4 py-3"><Badge tone={STATUT_PUB_TONE[f.statut_publication]}>{f.statut_publication}</Badge></td>
                <td className="px-4 py-3 text-slate-500">{f.ville ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nouvelle fiche">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <Field label="Nom">
            <Input required value={form.nom} onChange={(e) => update('nom', e.target.value)} />
          </Field>
          {!categorieFixe && (
            <Field label="Catégorie">
              <Select value={form.categorie_fiche} onChange={(e) => update('categorie_fiche', e.target.value as CategorieFiche)}>
                <option value="structure">Structure sanitaire</option>
                <option value="professionnel_independant">Professionnel indépendant</option>
              </Select>
            </Field>
          )}
          <Field label="Type">
            <Select value={form.type} onChange={(e) => update('type', e.target.value)}>
              <option value="dentaire">Dentaire</option>
              <option value="medical">Médical</option>
              <option value="mixte">Mixte</option>
            </Select>
          </Field>
          <Field label="Ville">
            <Input value={form.ville} onChange={(e) => update('ville', e.target.value)} />
          </Field>
          <Field label="Téléphone">
            <Input value={form.telephone} onChange={(e) => update('telephone', e.target.value)} />
          </Field>
          <Field label="Email">
            <Input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} />
          </Field>
          <Button type="submit" disabled={saving}>
            {saving ? 'Création…' : 'Créer la fiche (brouillon)'}
          </Button>
        </form>
      </Modal>
    </div>
  );
}
