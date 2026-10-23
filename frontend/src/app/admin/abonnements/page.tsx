'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, Card, ErrorText, Field, Input, Modal, PageHeader, Select } from '@/components/ui';
import type { AbonnementAdmin, AdminFiche, Paginated } from '@/types';

const STATUT_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red'> = {
  actif: 'green', suspendu: 'amber', annule: 'slate', expire: 'red',
};

const EMPTY_FORM = { cabinet_id: '', produit: 'directory_structure', plan: 'DOKTIS Structure', prix: '100000', cycle_facturation: 'annuel', date_debut: new Date().toISOString().slice(0, 10) };
const EMPTY_PAIEMENT = { montant: '', mode_paiement: 'airtel_money', reference: '', date_paiement: new Date().toISOString().slice(0, 10) };

export default function AdminAbonnementsPage() {
  const [abonnements, setAbonnements] = useState<AbonnementAdmin[]>([]);
  const [fiches, setFiches] = useState<AdminFiche[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [paiementPour, setPaiementPour] = useState<AbonnementAdmin | null>(null);
  const [paiementForm, setPaiementForm] = useState(EMPTY_PAIEMENT);
  const [paiementError, setPaiementError] = useState<string | null>(null);
  const [enregistrement, setEnregistrement] = useState(false);

  function load() {
    api.get<Paginated<AbonnementAdmin>>('/super-admin/abonnements?produit=directory_structure').then((res) => setAbonnements(res.data));
    api.get<Paginated<AbonnementAdmin>>('/super-admin/abonnements?produit=directory_professionnel').then((res) =>
      setAbonnements((prev) => [...prev.filter((a) => a.produit !== 'directory_professionnel'), ...res.data])
    );
    api.get<Paginated<AdminFiche>>('/admin/fiches?par_page=200').then((res) => setFiches(res.data));
  }

  useEffect(load, []);

  function update<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post('/super-admin/abonnements', { ...form, cabinet_id: Number(form.cabinet_id), prix: Number(form.prix) });
      setModalOpen(false);
      setForm(EMPTY_FORM);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de créer l'abonnement.");
    } finally {
      setSaving(false);
    }
  }

  async function enregistrerPaiement(e: React.FormEvent) {
    e.preventDefault();
    if (!paiementPour) return;
    setPaiementError(null);
    setEnregistrement(true);
    try {
      await api.post(`/super-admin/abonnements/${paiementPour.id}/paiements`, { ...paiementForm, montant: Number(paiementForm.montant) });
      setPaiementPour(null);
      setPaiementForm(EMPTY_PAIEMENT);
      load();
    } catch (err) {
      setPaiementError(err instanceof ApiError ? err.message : "Impossible d'enregistrer le paiement.");
    } finally {
      setEnregistrement(false);
    }
  }

  return (
    <div>
      <PageHeader
        title="Abonnements Directory"
        description="DOKTIS Structure (100 000 FCFA/an) et DOKTIS Professionnel (50 000 FCFA/an)."
        actions={<Button onClick={() => setModalOpen(true)}>+ Nouvel abonnement</Button>}
      />

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Fiche</th>
              <th className="px-4 py-3">Produit</th>
              <th className="px-4 py-3">Prix</th>
              <th className="px-4 py-3">Échéance</th>
              <th className="px-4 py-3">Statut</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {abonnements.map((a) => (
              <tr key={a.id} className="hover:bg-slate-50">
                <td className="px-4 py-3">{a.cabinet?.nom}</td>
                <td className="px-4 py-3">{a.produit === 'directory_structure' ? 'Structure' : 'Professionnel'}</td>
                <td className="px-4 py-3">{Number(a.prix).toLocaleString('fr-FR')} FCFA</td>
                <td className="px-4 py-3">{a.date_fin ? new Date(a.date_fin).toLocaleDateString('fr-FR') : '—'}</td>
                <td className="px-4 py-3"><Badge tone={STATUT_TONE[a.statut]}>{a.statut}</Badge></td>
                <td className="px-4 py-3 text-right">
                  <Button variant="secondary" onClick={() => setPaiementPour(a)}>Enregistrer un paiement</Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nouvel abonnement Directory">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <ErrorText>{error}</ErrorText>
          <Field label="Fiche">
            <Select required value={form.cabinet_id} onChange={(e) => update('cabinet_id', e.target.value)}>
              <option value="">Sélectionner…</option>
              {fiches.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
            </Select>
          </Field>
          <Field label="Produit">
            <Select value={form.produit} onChange={(e) => {
              update('produit', e.target.value);
              update('prix', e.target.value === 'directory_structure' ? '100000' : '50000');
              update('plan', e.target.value === 'directory_structure' ? 'DOKTIS Structure' : 'DOKTIS Professionnel');
            }}>
              <option value="directory_structure">DOKTIS Structure (100 000 FCFA/an)</option>
              <option value="directory_professionnel">DOKTIS Professionnel (50 000 FCFA/an)</option>
            </Select>
          </Field>
          <Field label="Prix (FCFA)">
            <Input type="number" required min="0" value={form.prix} onChange={(e) => update('prix', e.target.value)} />
          </Field>
          <Field label="Date de début">
            <Input type="date" required value={form.date_debut} onChange={(e) => update('date_debut', e.target.value)} />
          </Field>
          <Button type="submit" disabled={saving}>{saving ? 'Création…' : "Créer l'abonnement"}</Button>
        </form>
      </Modal>

      <Modal open={!!paiementPour} onClose={() => setPaiementPour(null)} title="Enregistrer un paiement">
        <form onSubmit={enregistrerPaiement} className="flex flex-col gap-4">
          <ErrorText>{paiementError}</ErrorText>
          <p className="text-sm text-slate-500">
            Paiement constaté manuellement (aucune passerelle en ligne) — prolonge automatiquement l&apos;échéance d&apos;un {paiementPour?.cycle_facturation === 'annuel' ? 'an' : 'mois'}.
          </p>
          <Field label="Montant (FCFA)">
            <Input type="number" required min="0" value={paiementForm.montant} onChange={(e) => setPaiementForm((f) => ({ ...f, montant: e.target.value }))} />
          </Field>
          <Field label="Mode de paiement">
            <Select value={paiementForm.mode_paiement} onChange={(e) => setPaiementForm((f) => ({ ...f, mode_paiement: e.target.value }))}>
              <option value="airtel_money">Airtel Money</option>
              <option value="moov_money">Moov Money</option>
              <option value="virement">Virement bancaire</option>
              <option value="especes">Espèces</option>
            </Select>
          </Field>
          <Field label="Référence (facultatif)">
            <Input value={paiementForm.reference} onChange={(e) => setPaiementForm((f) => ({ ...f, reference: e.target.value }))} />
          </Field>
          <Field label="Date du paiement">
            <Input type="date" required value={paiementForm.date_paiement} onChange={(e) => setPaiementForm((f) => ({ ...f, date_paiement: e.target.value }))} />
          </Field>
          <Button type="submit" disabled={enregistrement}>{enregistrement ? 'Enregistrement…' : 'Enregistrer le paiement'}</Button>
        </form>
      </Modal>
    </div>
  );
}
