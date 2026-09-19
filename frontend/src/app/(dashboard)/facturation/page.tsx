'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, Card, ErrorText, Field, Input, Modal, PageHeader, Select } from '@/components/ui';
import type { Facture, Paginated, Patient } from '@/types';

const STATUT_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red' | 'blue'> = {
  brouillon: 'slate',
  envoyee: 'blue',
  partiellement_payee: 'amber',
  payee: 'green',
  annulee: 'red',
};

const STATUT_LABEL: Record<string, string> = {
  brouillon: 'Brouillon',
  envoyee: 'Envoyée',
  partiellement_payee: 'Partiellement payée',
  payee: 'Payée',
  annulee: 'Annulée',
};

function fmt(value: string | number) {
  return new Intl.NumberFormat('fr-FR').format(Number(value)) + ' FCFA';
}

interface LigneForm {
  designation: string;
  quantite: string;
  prix_unitaire: string;
}

export default function FacturationPage() {
  const [factures, setFactures] = useState<Facture[]>([]);
  const [patients, setPatients] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [patientId, setPatientId] = useState('');
  const [lignes, setLignes] = useState<LigneForm[]>([{ designation: '', quantite: '1', prix_unitaire: '' }]);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function load() {
    setLoading(true);
    api
      .get<Paginated<Facture>>('/factures')
      .then((res) => setFactures(res.data))
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    queueMicrotask(() => {
      load();
      void api.get<{ data: Patient[] }>('/patients?par_page=200').then((res) => setPatients(res.data));
    });
  }, []);

  function updateLigne(index: number, key: keyof LigneForm, value: string) {
    setLignes((prev) => prev.map((l, i) => (i === index ? { ...l, [key]: value } : l)));
  }

  function addLigne() {
    setLignes((prev) => [...prev, { designation: '', quantite: '1', prix_unitaire: '' }]);
  }

  function removeLigne(index: number) {
    setLignes((prev) => prev.filter((_, i) => i !== index));
  }

  const total = lignes.reduce((sum, l) => sum + (Number(l.quantite) || 0) * (Number(l.prix_unitaire) || 0), 0);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post('/factures', {
        patient_id: Number(patientId),
        date_emission: new Date().toISOString().slice(0, 10),
        lignes: lignes.map((l) => ({ ...l, quantite: Number(l.quantite), prix_unitaire: Number(l.prix_unitaire) })),
      });
      setModalOpen(false);
      setPatientId('');
      setLignes([{ designation: '', quantite: '1', prix_unitaire: '' }]);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de créer la facture.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader title="Facturation" description="Factures et paiements du cabinet." actions={<Button onClick={() => setModalOpen(true)}>+ Nouvelle facture</Button>} />

      <Card className="overflow-x-auto p-0">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">N°</th>
              <th className="px-4 py-3">Patient</th>
              <th className="px-4 py-3">Total</th>
              <th className="px-4 py-3">Payé</th>
              <th className="px-4 py-3">Statut</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-slate-400">Chargement…</td>
              </tr>
            )}
            {!loading && factures.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-slate-400">Aucune facture.</td>
              </tr>
            )}
            {factures.map((f) => (
              <tr key={f.id} className="hover:bg-slate-50">
                <td className="px-4 py-3">
                  <Link href={`/facturation/${f.id}`} className="font-medium text-teal-700 hover:underline">
                    {f.numero}
                  </Link>
                </td>
                <td className="px-4 py-3">
                  {f.patient?.prenom} {f.patient?.nom}
                </td>
                <td className="px-4 py-3">{fmt(f.montant_total)}</td>
                <td className="px-4 py-3">{fmt(f.montant_paye)}</td>
                <td className="px-4 py-3">
                  <Badge tone={STATUT_TONE[f.statut]}>{STATUT_LABEL[f.statut]}</Badge>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nouvelle facture">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <Field label="Patient">
            <Select required value={patientId} onChange={(e) => setPatientId(e.target.value)}>
              <option value="">Sélectionner…</option>
              {patients.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.prenom} {p.nom}
                </option>
              ))}
            </Select>
          </Field>

          <div className="space-y-2">
            <p className="text-sm font-medium text-slate-700">Lignes de facturation</p>
            {lignes.map((ligne, i) => (
              <div key={i} className="grid grid-cols-[1fr_70px_100px_28px] gap-2">
                <Input placeholder="Désignation" required value={ligne.designation} onChange={(e) => updateLigne(i, 'designation', e.target.value)} />
                <Input type="number" min="0.01" step="0.01" placeholder="Qté" required value={ligne.quantite} onChange={(e) => updateLigne(i, 'quantite', e.target.value)} />
                <Input type="number" min="0" step="1" placeholder="Prix unitaire" required value={ligne.prix_unitaire} onChange={(e) => updateLigne(i, 'prix_unitaire', e.target.value)} />
                <button type="button" onClick={() => removeLigne(i)} className="text-slate-400 hover:text-red-600" aria-label="Supprimer la ligne">
                  ✕
                </button>
              </div>
            ))}
            <Button type="button" variant="secondary" onClick={addLigne}>+ Ajouter une ligne</Button>
          </div>

          <p className="text-right text-lg font-semibold text-slate-800">Total : {fmt(total)}</p>

          <Button type="submit" disabled={saving}>
            {saving ? 'Création…' : 'Créer la facture'}
          </Button>
        </form>
      </Modal>
    </div>
  );
}
