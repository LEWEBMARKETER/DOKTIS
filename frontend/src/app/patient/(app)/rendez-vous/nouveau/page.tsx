'use client';

import { useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { patientApi, ApiError } from '@/lib/patient-api';
import { Badge, Button, Card, ErrorText, Field, Input, PageHeader, Select, Textarea } from '@/components/ui';
import type { DirectoryCabinet, Paginated } from '@/types';
import type { PraticienRef } from '@/types/patient';

const DUREES = [
  { label: '20 minutes', value: 20 },
  { label: '30 minutes', value: 30 },
  { label: '45 minutes', value: 45 },
  { label: '1 heure', value: 60 },
];

function addMinutes(datetimeLocal: string, minutes: number): string {
  const date = new Date(datetimeLocal);
  date.setMinutes(date.getMinutes() + minutes);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function NouveauRendezVousPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [step, setStep] = useState<'cabinet' | 'creneau'>('cabinet');

  const [q, setQ] = useState('');
  const [cabinets, setCabinets] = useState<DirectoryCabinet[]>([]);
  const [searching, setSearching] = useState(false);

  const [cabinet, setCabinet] = useState<DirectoryCabinet | null>(null);
  const [praticiens, setPraticiens] = useState<PraticienRef[]>([]);
  const [praticienId, setPraticienId] = useState('');
  const [debut, setDebut] = useState('');
  const [duree, setDuree] = useState(30);
  const [motif, setMotif] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const cabinetId = searchParams.get('cabinet');
    if (cabinetId) {
      patientApi.get<DirectoryCabinet>(`/directory/cabinets/${cabinetId}`).then((detail) => {
        setCabinet(detail);
        setPraticiens((detail.users as unknown as PraticienRef[]) ?? []);
        setStep('creneau');
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const timeout = setTimeout(() => {
      setSearching(true);
      const params = new URLSearchParams();
      if (q) params.set('q', q);
      patientApi
        .get<Paginated<DirectoryCabinet>>(`/directory/cabinets?${params.toString()}`)
        .then((res) => setCabinets(res.data))
        .finally(() => setSearching(false));
    }, 300);
    return () => clearTimeout(timeout);
  }, [q]);

  async function choisirCabinet(c: DirectoryCabinet) {
    const detail = await patientApi.get<DirectoryCabinet>(`/directory/cabinets/${c.id}`);
    setCabinet(detail);
    setPraticiens((detail.users as unknown as PraticienRef[]) ?? []);
    setStep('creneau');
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!cabinet) return;
    setError(null);
    setSaving(true);
    try {
      await patientApi.post('/patient/rendez-vous', {
        cabinet_id: cabinet.id,
        praticien_id: Number(praticienId),
        motif: motif || undefined,
        debut,
        fin: addMinutes(debut, duree),
      });
      router.push('/patient/rendez-vous');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de créer ce rendez-vous.');
    } finally {
      setSaving(false);
    }
  }

  if (step === 'cabinet') {
    return (
      <div>
        <PageHeader title="Nouveau rendez-vous" description="Choisissez un cabinet pour commencer." />
        <Input
          placeholder="Rechercher un cabinet par nom, ville…"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="mb-4 w-full max-w-md"
        />
        {searching && <p className="text-sm text-slate-400">Recherche…</p>}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {cabinets.map((c) => (
            <button key={c.id} onClick={() => choisirCabinet(c)} className="text-left">
              <Card className="h-full transition-shadow hover:shadow-md">
                <p className="font-semibold text-slate-900">{c.nom}</p>
                <p className="text-sm text-slate-500">{[c.quartier, c.ville].filter(Boolean).join(', ')}</p>
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {c.specialites?.map((s) => (
                    <Badge key={s.id} tone="blue">
                      {s.nom}
                    </Badge>
                  ))}
                </div>
              </Card>
            </button>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Nouveau rendez-vous" description={cabinet?.nom} />
      <button onClick={() => setStep('cabinet')} className="mb-4 text-sm text-teal-700 hover:underline">
        ← Changer de cabinet
      </button>

      <Card className="max-w-lg">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>

          <Field label="Praticien">
            <Select required value={praticienId} onChange={(e) => setPraticienId(e.target.value)}>
              <option value="">Sélectionner un praticien</option>
              {praticiens.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                  {p.specialite ? ` — ${p.specialite}` : ''}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Date et heure">
            <Input type="datetime-local" required value={debut} onChange={(e) => setDebut(e.target.value)} />
          </Field>

          <Field label="Durée">
            <Select value={duree} onChange={(e) => setDuree(Number(e.target.value))}>
              {DUREES.map((d) => (
                <option key={d.value} value={d.value}>
                  {d.label}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Motif (optionnel)">
            <Textarea rows={3} value={motif} onChange={(e) => setMotif(e.target.value)} placeholder="Décrivez brièvement le motif de la consultation…" />
          </Field>

          <Button type="submit" disabled={saving || !praticienId || !debut}>
            {saving ? 'Confirmation…' : 'Confirmer le rendez-vous'}
          </Button>
        </form>
      </Card>
    </div>
  );
}
