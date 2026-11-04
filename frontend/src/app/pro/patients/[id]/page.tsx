'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth-context';
import { Badge, Button, Card, ErrorText, Field, Input, Modal, PageHeader, Select, Textarea } from '@/components/ui';
import type { Consultation, Ordonnance, Patient, PlanTraitement } from '@/types';

interface OrdonnanceModele {
  id: number;
  titre: string;
  contenu: string;
}

interface DocumentItem {
  id: number;
  type: string;
  titre: string | null;
  url: string;
  created_at: string;
}

export default function PatientDetailPage() {
  const params = useParams<{ id: string }>();
  const patientId = params.id;

  const [patient, setPatient] = useState<Patient | null>(null);
  const [consultations, setConsultations] = useState<Consultation[]>([]);
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  const [plans, setPlans] = useState<PlanTraitement[]>([]);
  const [ordonnances, setOrdonnances] = useState<Ordonnance[]>([]);
  const [consultModalOpen, setConsultModalOpen] = useState(false);
  const [ordonnanceModalOpen, setOrdonnanceModalOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    const [p, c, d, plan, ord] = await Promise.all([
      api.get<Patient>(`/patients/${patientId}`),
      api.get<{ data: Consultation[] }>(`/consultations?patient_id=${patientId}`),
      api.get<DocumentItem[]>(`/documents?patient_id=${patientId}`),
      api.get<{ data: PlanTraitement[] }>(`/plans-traitement?patient_id=${patientId}`),
      api.get<Ordonnance[]>(`/ordonnances?patient_id=${patientId}`),
    ]);
    setPatient(p);
    setConsultations(c.data);
    setDocuments(d);
    setPlans(plan.data);
    setOrdonnances(ord);
  }, [patientId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleUpload(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('patient_id', patientId);
    formData.append('type', file.type === 'application/pdf' ? 'pdf' : 'photo');
    formData.append('fichier', file);

    try {
      await api.post('/documents', formData);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Échec de l'envoi du document.");
    } finally {
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  }

  if (!patient) return <p className="text-slate-400">Chargement…</p>;

  return (
    <div>
      <PageHeader
        title={`${patient.prenom} ${patient.nom}`}
        description={`Dossier n° ${patient.numero_dossier}`}
        actions={<Button onClick={() => setConsultModalOpen(true)}>+ Nouvelle consultation</Button>}
      />

      <ErrorText>{error}</ErrorText>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-1">
          <h2 className="mb-3 font-semibold text-slate-800">Informations</h2>
          <dl className="space-y-2 text-sm">
            <Row label="Téléphone" value={patient.telephone} />
            <Row label="Email" value={patient.email} />
            <Row label="Naissance" value={patient.date_naissance ? new Date(patient.date_naissance).toLocaleDateString('fr-FR') : null} />
            <Row label="Sexe" value={patient.sexe} />
            <Row label="Groupe sanguin" value={patient.groupe_sanguin} />
            <Row label="Allergies" value={patient.allergies} />
            <Row label="Antécédents" value={patient.antecedents_medicaux} />
            <Row label="Adresse" value={patient.adresse} />
          </dl>
        </Card>

        <div className="space-y-6 lg:col-span-2">
          <Card>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="font-semibold text-slate-800">Consultations</h2>
            </div>
            {consultations.length === 0 && <p className="text-sm text-slate-400">Aucune consultation enregistrée.</p>}
            <ul className="space-y-3">
              {consultations.map((c) => (
                <li key={c.id} className="rounded-lg border border-slate-100 p-3 text-sm">
                  <p className="font-medium text-slate-800">
                    {new Date(c.date_consultation).toLocaleDateString('fr-FR', { dateStyle: 'medium' })} — {c.motif ?? 'Consultation'}
                  </p>
                  {c.diagnostic && <p className="text-slate-600">Diagnostic : {c.diagnostic}</p>}
                  {c.traitement && <p className="text-slate-500">Traitement : {c.traitement}</p>}
                </li>
              ))}
            </ul>
          </Card>

          <Card>
            <h2 className="mb-3 font-semibold text-slate-800">Plans de traitement</h2>
            {plans.length === 0 && <p className="text-sm text-slate-400">Aucun plan de traitement.</p>}
            <ul className="space-y-3">
              {plans.map((plan) => (
                <li key={plan.id} className="rounded-lg border border-slate-100 p-3 text-sm">
                  <div className="flex items-center justify-between">
                    <p className="font-medium text-slate-800">{plan.titre}</p>
                    <Badge tone={plan.statut === 'termine' ? 'green' : plan.statut === 'abandonne' ? 'red' : 'blue'}>{plan.statut}</Badge>
                  </div>
                  <ul className="mt-2 space-y-1 pl-4 text-slate-600">
                    {plan.etapes?.map((etape) => (
                      <li key={etape.id} className="flex items-center justify-between">
                        <span>{etape.titre}</span>
                        <Badge tone={etape.statut === 'realisee' ? 'green' : 'slate'}>{etape.statut}</Badge>
                      </li>
                    ))}
                  </ul>
                </li>
              ))}
            </ul>
          </Card>

          <Card>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="font-semibold text-slate-800">Ordonnances</h2>
              <Button variant="secondary" onClick={() => setOrdonnanceModalOpen(true)}>+ Nouvelle ordonnance</Button>
            </div>
            {ordonnances.length === 0 && <p className="text-sm text-slate-400">Aucune ordonnance.</p>}
            <ul className="space-y-2 text-sm">
              {ordonnances.map((o) => (
                <li key={o.id} className="rounded-lg border border-slate-100 p-3">
                  <p className="text-slate-500">{new Date(o.date_emission).toLocaleDateString('fr-FR')}</p>
                  <p className="whitespace-pre-line text-slate-700">{o.contenu}</p>
                </li>
              ))}
            </ul>
          </Card>

          <Card>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="font-semibold text-slate-800">Documents &amp; suivi photographique</h2>
              <Button variant="secondary" onClick={() => fileInputRef.current?.click()}>
                + Ajouter
              </Button>
              <input ref={fileInputRef} type="file" accept="image/*,application/pdf" className="hidden" onChange={handleUpload} />
            </div>
            {documents.length === 0 && <p className="text-sm text-slate-400">Aucun document.</p>}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              {documents.map((doc) => (
                <a
                  key={doc.id}
                  href={doc.url}
                  target="_blank"
                  rel="noreferrer"
                  className="flex flex-col items-center gap-1 rounded-lg border border-slate-100 p-2 text-center text-xs text-slate-500 hover:border-teal-300"
                >
                  <span className="text-2xl">{doc.type === 'pdf' ? '📄' : '🖼️'}</span>
                  <span className="truncate w-full">{doc.titre}</span>
                </a>
              ))}
            </div>
          </Card>
        </div>
      </div>

      <NouvelleConsultationModal
        open={consultModalOpen}
        patientId={patientId}
        onClose={() => setConsultModalOpen(false)}
        onCreated={load}
      />

      <NouvelleOrdonnanceModal
        open={ordonnanceModalOpen}
        patientId={patientId}
        onClose={() => setOrdonnanceModalOpen(false)}
        onCreated={load}
      />
    </div>
  );
}

function Row({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="text-slate-500">{label}</dt>
      <dd className="text-right text-slate-800">{value || '—'}</dd>
    </div>
  );
}

function NouvelleConsultationModal({
  open,
  patientId,
  onClose,
  onCreated,
}: {
  open: boolean;
  patientId: string;
  onClose: () => void;
  onCreated: () => void;
}) {
  const { user } = useAuth();
  const [form, setForm] = useState({ motif: '', diagnostic: '', observations: '', traitement: '' });
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function update<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!user) return;
    setError(null);
    setSaving(true);
    try {
      await api.post('/consultations', {
        patient_id: Number(patientId),
        praticien_id: user.id,
        date_consultation: new Date().toISOString(),
        ...form,
      });
      setForm({ motif: '', diagnostic: '', observations: '', traitement: '' });
      onClose();
      onCreated();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de créer la consultation.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Nouvelle consultation">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <ErrorText>{error}</ErrorText>
        <Field label="Motif">
          <Input value={form.motif} onChange={(e) => update('motif', e.target.value)} />
        </Field>
        <Field label="Diagnostic">
          <Textarea rows={2} value={form.diagnostic} onChange={(e) => update('diagnostic', e.target.value)} />
        </Field>
        <Field label="Observations">
          <Textarea rows={2} value={form.observations} onChange={(e) => update('observations', e.target.value)} />
        </Field>
        <Field label="Traitement">
          <Textarea rows={2} value={form.traitement} onChange={(e) => update('traitement', e.target.value)} />
        </Field>
        <Button type="submit" disabled={saving}>
          {saving ? 'Enregistrement…' : 'Enregistrer'}
        </Button>
      </form>
    </Modal>
  );
}

function NouvelleOrdonnanceModal({
  open,
  patientId,
  onClose,
  onCreated,
}: {
  open: boolean;
  patientId: string;
  onClose: () => void;
  onCreated: () => void;
}) {
  const { user } = useAuth();
  const [modeles, setModeles] = useState<OrdonnanceModele[]>([]);
  const [modeleId, setModeleId] = useState('');
  const [contenu, setContenu] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (open) api.get<OrdonnanceModele[]>('/ordonnance-modeles').then(setModeles);
  }, [open]);

  function selectModele(id: string) {
    setModeleId(id);
    const modele = modeles.find((m) => String(m.id) === id);
    if (modele) setContenu(modele.contenu);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!user) return;
    setError(null);
    setSaving(true);
    try {
      await api.post('/ordonnances', {
        patient_id: Number(patientId),
        ordonnance_modele_id: modeleId ? Number(modeleId) : null,
        contenu,
        date_emission: new Date().toISOString().slice(0, 10),
      });
      setModeleId('');
      setContenu('');
      onClose();
      onCreated();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de créer l'ordonnance.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Nouvelle ordonnance">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <ErrorText>{error}</ErrorText>
        {modeles.length > 0 && (
          <Field label="Partir d'un modèle (optionnel)">
            <Select value={modeleId} onChange={(e) => selectModele(e.target.value)}>
              <option value="">— Aucun modèle —</option>
              {modeles.map((m) => (
                <option key={m.id} value={m.id}>{m.titre}</option>
              ))}
            </Select>
          </Field>
        )}
        <Field label="Contenu de l'ordonnance">
          <Textarea rows={6} required value={contenu} onChange={(e) => setContenu(e.target.value)} />
        </Field>
        <Button type="submit" disabled={saving}>
          {saving ? 'Enregistrement…' : "Enregistrer l'ordonnance"}
        </Button>
      </form>
    </Modal>
  );
}
