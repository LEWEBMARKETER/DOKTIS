'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { Badge, Button, Card, ErrorText, Field, Input, Modal, PageHeader, Select } from '@/components/ui';
import type { Role, User } from '@/types';

const ROLES: { value: Role; label: string }[] = [
  { value: 'administrateur', label: 'Administrateur' },
  { value: 'medecin', label: 'Médecin / Dentiste' },
  { value: 'secretaire', label: 'Secrétaire' },
  { value: 'assistant', label: 'Assistant médical' },
];

const EMPTY_FORM = { name: '', email: '', role: 'secretaire' as Role, password: '' };

export default function AdministrationPage() {
  const { user: currentUser } = useAuth();
  const [users, setUsers] = useState<User[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function load() {
    api.get<User[]>('/users').then(setUsers);
  }

  useEffect(() => {
    load();
  }, []);

  function update<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post('/users', form);
      setModalOpen(false);
      setForm(EMPTY_FORM);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de créer l'utilisateur.");
    } finally {
      setSaving(false);
    }
  }

  async function toggleActif(u: User) {
    await api.patch(`/users/${u.id}`, { actif: !u.actif });
    load();
  }

  async function remove(u: User) {
    if (!confirm(`Supprimer ${u.name} ?`)) return;
    await api.delete(`/users/${u.id}`);
    load();
  }

  return (
    <div>
      <PageHeader title="Équipe du cabinet" description="Gérez les comptes de votre personnel." actions={<Button onClick={() => setModalOpen(true)}>+ Ajouter un membre</Button>} />

      <Card className="overflow-x-auto p-0">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Nom</th>
              <th className="px-4 py-3">Email</th>
              <th className="px-4 py-3">Rôle</th>
              <th className="px-4 py-3">Statut</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {users.map((u) => (
              <tr key={u.id} className="hover:bg-slate-50">
                <td className="px-4 py-3 font-medium text-slate-800">{u.name}</td>
                <td className="px-4 py-3">{u.email}</td>
                <td className="px-4 py-3">{ROLES.find((r) => r.value === u.role)?.label ?? u.role}</td>
                <td className="px-4 py-3">
                  <Badge tone={u.actif ? 'green' : 'slate'}>{u.actif ? 'Actif' : 'Inactif'}</Badge>
                </td>
                <td className="px-4 py-3 text-right">
                  {u.id !== currentUser?.id && (
                    <div className="flex justify-end gap-2">
                      <Button variant="secondary" onClick={() => toggleActif(u)}>
                        {u.actif ? 'Désactiver' : 'Activer'}
                      </Button>
                      <Button variant="danger" onClick={() => remove(u)}>Supprimer</Button>
                    </div>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Ajouter un membre">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <Field label="Nom complet">
            <Input required value={form.name} onChange={(e) => update('name', e.target.value)} />
          </Field>
          <Field label="Email">
            <Input type="email" required value={form.email} onChange={(e) => update('email', e.target.value)} />
          </Field>
          <Field label="Rôle">
            <Select value={form.role} onChange={(e) => update('role', e.target.value as Role)}>
              {ROLES.map((r) => (
                <option key={r.value} value={r.value}>{r.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Mot de passe">
            <Input type="password" required minLength={8} value={form.password} onChange={(e) => update('password', e.target.value)} />
          </Field>
          <Button type="submit" disabled={saving}>
            {saving ? 'Création…' : 'Créer le compte'}
          </Button>
        </form>
      </Modal>
    </div>
  );
}
