'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { Badge, Button, Card, ErrorText, Field, Input, Modal, PageHeader, Select } from '@/components/ui';
import type { Patient, RendezVous, User } from '@/types';

const STATUTS: Record<string, { label: string; tone: 'slate' | 'green' | 'amber' | 'red' | 'blue' }> = {
  planifie: { label: 'Planifié', tone: 'blue' },
  confirme: { label: 'Confirmé', tone: 'blue' },
  termine: { label: 'Terminé', tone: 'green' },
  annule: { label: 'Annulé', tone: 'red' },
  absent: { label: 'Absent', tone: 'amber' },
};

const EMPTY_FORM = { patient_id: '', praticien_id: '', motif: '', date: '', heure_debut: '', heure_fin: '' };

export default function AgendaPage() {
  const { user } = useAuth();
  const [rendezVous, setRendezVous] = useState<RendezVous[]>([]);
  const [patients, setPatients] = useState<Patient[]>([]);
  const [praticiens, setPraticiens] = useState<User[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function load() {
    const debut = new Date();
    debut.setHours(0, 0, 0, 0);
    const res = await api.get<RendezVous[]>(`/rendez-vous?debut=${debut.toISOString()}`);
    setRendezVous(res);
  }

  useEffect(() => {
    queueMicrotask(() => {
      void load();
      void api.get<{ data: Patient[] }>('/patients?par_page=200').then((res) => setPatients(res.data));
      void api.get<User[]>('/users').then(setPraticiens).catch(() => setPraticiens(user ? [user] : []));
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function update<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post('/rendez-vous', {
        patient_id: Number(form.patient_id),
        praticien_id: Number(form.praticien_id),
        motif: form.motif,
        debut: `${form.date}T${form.heure_debut}:00`,
        fin: `${form.date}T${form.heure_fin}:00`,
      });
      setModalOpen(false);
      setForm(EMPTY_FORM);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de créer le rendez-vous.');
    } finally {
      setSaving(false);
    }
  }

  async function updateStatut(rdv: RendezVous, statut: string) {
    await api.patch(`/rendez-vous/${rdv.id}`, { statut });
    load();
  }

  return (
    <div>
      <PageHeader title="Agenda" description="Rendez-vous à venir et du jour." actions={<Button onClick={() => setModalOpen(true)}>+ Nouveau rendez-vous</Button>} />

      <div className="space-y-3">
        {rendezVous.length === 0 && <p className="text-sm text-slate-400">Aucun rendez-vous à venir.</p>}
        {rendezVous.map((rdv) => {
          const statut = STATUTS[rdv.statut] ?? STATUTS.planifie;
          return (
            <Card key={rdv.id} className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="font-medium text-slate-800">
                  {rdv.patient?.prenom} {rdv.patient?.nom}
                </p>
                <p className="text-sm text-slate-500">
                  {new Date(rdv.debut).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })} —{' '}
                  {new Date(rdv.fin).toLocaleTimeString('fr-FR', { timeStyle: 'short' })} · {rdv.praticien?.name}
                </p>
                {rdv.motif && <p className="text-sm text-slate-400">{rdv.motif}</p>}
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={statut.tone}>{statut.label}</Badge>
                {rdv.statut === 'planifie' && (
                  <>
                    <Button variant="secondary" onClick={() => updateStatut(rdv, 'confirme')}>Confirmer</Button>
                    <Button variant="danger" onClick={() => updateStatut(rdv, 'annule')}>Annuler</Button>
                  </>
                )}
              </div>
            </Card>
          );
        })}
      </div>

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nouveau rendez-vous">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <Field label="Patient">
            <Select required value={form.patient_id} onChange={(e) => update('patient_id', e.target.value)}>
              <option value="">Sélectionner…</option>
              {patients.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.prenom} {p.nom}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Praticien">
            <Select required value={form.praticien_id} onChange={(e) => update('praticien_id', e.target.value)}>
              <option value="">Sélectionner…</option>
              {praticiens.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Motif">
            <Input value={form.motif} onChange={(e) => update('motif', e.target.value)} />
          </Field>
          <div className="grid grid-cols-3 gap-3">
            <Field label="Date">
              <Input type="date" required value={form.date} onChange={(e) => update('date', e.target.value)} />
            </Field>
            <Field label="Heure début">
              <Input type="time" required value={form.heure_debut} onChange={(e) => update('heure_debut', e.target.value)} />
            </Field>
            <Field label="Heure fin">
              <Input type="time" required value={form.heure_fin} onChange={(e) => update('heure_fin', e.target.value)} />
            </Field>
          </div>
          <Button type="submit" disabled={saving}>
            {saving ? 'Création…' : 'Créer le rendez-vous'}
          </Button>
        </form>
      </Modal>
    </div>
  );
}
