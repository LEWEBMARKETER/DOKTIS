'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, Card, ErrorText, Field, Input, PageHeader, Select } from '@/components/ui';
import type { Facture } from '@/types';

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

const MODES = [
  { value: 'especes', label: 'Espèces' },
  { value: 'carte', label: 'Carte' },
  { value: 'mobile_money', label: 'Mobile Money' },
  { value: 'virement', label: 'Virement' },
  { value: 'cheque', label: 'Chèque' },
];

function fmt(value: string | number) {
  return new Intl.NumberFormat('fr-FR').format(Number(value)) + ' FCFA';
}

export default function FactureDetailPage() {
  const params = useParams<{ id: string }>();
  const [facture, setFacture] = useState<Facture | null>(null);
  const [montant, setMontant] = useState('');
  const [mode, setMode] = useState('especes');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    const f = await api.get<Facture>(`/factures/${params.id}`);
    setFacture(f);
  }, [params.id]);

  useEffect(() => {
    load();
  }, [load]);

  if (!facture) return <p className="text-slate-400">Chargement…</p>;

  const solde = Number(facture.montant_total) - Number(facture.montant_paye);

  async function handlePaiement(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.post(`/factures/${params.id}/paiements`, {
        montant: Number(montant),
        mode_paiement: mode,
        date_paiement: new Date().toISOString().slice(0, 10),
        echeance_numero: (facture!.paiements?.length ?? 0) + 1,
      });
      setMontant('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible d'enregistrer le paiement.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={`Facture ${facture.numero}`}
        description={`${facture.patient?.prenom} ${facture.patient?.nom}`}
        actions={<Badge tone={STATUT_TONE[facture.statut]}>{STATUT_LABEL[facture.statut]}</Badge>}
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 font-semibold text-slate-800">Détail</h2>
          <table className="w-full text-sm">
            <thead className="text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="pb-2">Désignation</th>
                <th className="pb-2">Qté</th>
                <th className="pb-2 text-right">Montant</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {facture.lignes?.map((l) => (
                <tr key={l.id}>
                  <td className="py-2">{l.designation}</td>
                  <td className="py-2">{l.quantite}</td>
                  <td className="py-2 text-right">{fmt(l.montant)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="mt-4 space-y-1 border-t border-slate-100 pt-3 text-sm">
            <div className="flex justify-between"><span className="text-slate-500">Total</span><span className="font-medium">{fmt(facture.montant_total)}</span></div>
            <div className="flex justify-between"><span className="text-slate-500">Payé</span><span className="font-medium text-emerald-600">{fmt(facture.montant_paye)}</span></div>
            <div className="flex justify-between"><span className="text-slate-500">Solde</span><span className="font-semibold">{fmt(solde)}</span></div>
          </div>
        </Card>

        <Card>
          <h2 className="mb-3 font-semibold text-slate-800">Paiements (paiement échelonné possible)</h2>
          <ul className="mb-4 space-y-2 text-sm">
            {facture.paiements?.length === 0 && <p className="text-slate-400">Aucun paiement enregistré.</p>}
            {facture.paiements?.map((p) => (
              <li key={p.id} className="flex justify-between rounded-lg border border-slate-100 px-3 py-2">
                <span>
                  {p.echeance_numero ? `Échéance ${p.echeance_numero} — ` : ''}
                  {MODES.find((m) => m.value === p.mode_paiement)?.label ?? p.mode_paiement}
                </span>
                <span className="font-medium">{fmt(p.montant)}</span>
              </li>
            ))}
          </ul>

          {solde > 0 ? (
            <form onSubmit={handlePaiement} className="flex flex-col gap-3">
              <ErrorText>{error}</ErrorText>
              <Field label={`Montant (solde restant : ${fmt(solde)})`}>
                <Input type="number" min="0.01" step="0.01" max={solde} required value={montant} onChange={(e) => setMontant(e.target.value)} />
              </Field>
              <Field label="Mode de paiement">
                <Select value={mode} onChange={(e) => setMode(e.target.value)}>
                  {MODES.map((m) => (
                    <option key={m.value} value={m.value}>{m.label}</option>
                  ))}
                </Select>
              </Field>
              <Button type="submit" disabled={saving}>
                {saving ? 'Enregistrement…' : 'Enregistrer le paiement'}
              </Button>
            </form>
          ) : (
            <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">Facture entièrement payée.</p>
          )}
        </Card>
      </div>
    </div>
  );
}
