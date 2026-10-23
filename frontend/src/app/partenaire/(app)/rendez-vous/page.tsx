'use client';

import { useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, Card, ErrorText, Field, Input, Modal, PageHeader, Select, Textarea } from '@/components/ui';
import type { DemandeRendezVousAdmin, Paginated } from '@/types';

const STATUT_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red' | 'blue'> = {
  en_attente: 'amber', confirmee: 'green', refusee: 'red', annulee: 'slate', terminee: 'blue',
};

export default function PartenaireRendezVousPage() {
  const [demandes, setDemandes] = useState<DemandeRendezVousAdmin[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [propositionPour, setPropositionPour] = useState<DemandeRendezVousAdmin | null>(null);
  const [propForm, setPropForm] = useState({ date_proposee: '', creneau_propose: '', notes_partenaire: '' });

  function load() {
    api.get<Paginated<DemandeRendezVousAdmin>>('/partenaire/demandes-rendez-vous').then((res) => setDemandes(res.data));
  }

  useEffect(load, []);

  async function agir(demande: DemandeRendezVousAdmin, action: 'confirmer' | 'refuser' | 'terminer') {
    if (busyId !== null) return;
    setError(null);
    setBusyId(demande.id);
    try {
      await api.post(`/partenaire/demandes-rendez-vous/${demande.id}/${action}`);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Action impossible.');
    } finally {
      setBusyId(null);
    }
  }

  async function proposer(e: React.FormEvent) {
    e.preventDefault();
    if (!propositionPour) return;
    setError(null);
    setBusyId(propositionPour.id);
    try {
      await api.post(`/partenaire/demandes-rendez-vous/${propositionPour.id}/proposer-creneau`, propForm);
      setPropositionPour(null);
      setPropForm({ date_proposee: '', creneau_propose: '', notes_partenaire: '' });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de proposer ce créneau.');
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div>
      <PageHeader title="Demandes de rendez-vous" description="Confirmez, refusez ou proposez un autre créneau." />
      <ErrorText>{error}</ErrorText>

      <div className="space-y-3">
        {demandes.length === 0 && <p className="text-sm text-slate-400">Aucune demande pour le moment.</p>}
        {demandes.map((d) => (
          <Card key={d.id} className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="font-medium text-slate-800">{d.prenom} {d.nom} — {d.telephone}</p>
              <p className="text-sm text-slate-500">
                Souhaité : {new Date(d.date_souhaitee).toLocaleDateString('fr-FR')} {d.creneau_souhaite ? `(${d.creneau_souhaite})` : ''}
              </p>
              {d.date_proposee && (
                <p className="text-sm text-doktis-petrol">
                  Proposé : {new Date(d.date_proposee).toLocaleDateString('fr-FR')} {d.creneau_propose ? `(${d.creneau_propose})` : ''}
                </p>
              )}
              {d.motif && <p className="text-sm text-slate-400">{d.motif}</p>}
            </div>
            <div className="flex items-center gap-2">
              <Badge tone={STATUT_TONE[d.statut]}>{d.statut}</Badge>
              {d.statut === 'en_attente' && (
                <>
                  <Button disabled={busyId !== null} onClick={() => agir(d, 'confirmer')}>Confirmer</Button>
                  <Button variant="secondary" disabled={busyId !== null} onClick={() => setPropositionPour(d)}>Proposer un créneau</Button>
                  <Button variant="danger" disabled={busyId !== null} onClick={() => agir(d, 'refuser')}>Refuser</Button>
                </>
              )}
              {d.statut === 'confirmee' && (
                <Button variant="secondary" disabled={busyId !== null} onClick={() => agir(d, 'terminer')}>Marquer terminé</Button>
              )}
            </div>
          </Card>
        ))}
      </div>

      <Modal open={!!propositionPour} onClose={() => setPropositionPour(null)} title="Proposer un autre créneau">
        <form onSubmit={proposer} className="flex flex-col gap-4">
          <Field label="Date proposée">
            <Input type="date" required value={propForm.date_proposee} onChange={(e) => setPropForm((f) => ({ ...f, date_proposee: e.target.value }))} />
          </Field>
          <Field label="Créneau">
            <Select value={propForm.creneau_propose} onChange={(e) => setPropForm((f) => ({ ...f, creneau_propose: e.target.value }))}>
              <option value="">—</option>
              <option value="matin">Matin</option>
              <option value="apres-midi">Après-midi</option>
            </Select>
          </Field>
          <Field label="Note pour vous-même (facultatif)">
            <Textarea rows={2} value={propForm.notes_partenaire} onChange={(e) => setPropForm((f) => ({ ...f, notes_partenaire: e.target.value }))} />
          </Field>
          <p className="text-xs text-slate-400">
            Cette proposition reste &quot;en attente&quot; : contactez le patient par téléphone pour confirmer, puis validez la demande ici.
          </p>
          <Button type="submit" disabled={busyId !== null}>Enregistrer la proposition</Button>
        </form>
      </Modal>
    </div>
  );
}
