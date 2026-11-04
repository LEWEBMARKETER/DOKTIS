'use client';

import { useCallback, useEffect, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, Card, ErrorText, PageHeader, Select, Textarea } from '@/components/ui';

interface Demande {
  id: number;
  nom: string;
  email: string;
  telephone: string;
  fonction: string | null;
  message: string | null;
  statut: 'en_attente' | 'approuvee' | 'rejetee';
  motif_rejet: string | null;
  created_at: string;
  cabinet: { id: number; nom: string; slug: string; ville: string | null; quartier: string | null; telephone: string | null; whatsapp: string | null };
}

const TONS = { en_attente: 'amber', approuvee: 'green', rejetee: 'red' } as const;
const LIBELLES = { en_attente: 'À traiter', approuvee: 'Approuvée', rejetee: 'Rejetée' };

export default function RevendicationsPage() {
  const [statut, setStatut] = useState('en_attente');
  const [demandes, setDemandes] = useState<Demande[]>([]);
  const [erreur, setErreur] = useState<string | null>(null);
  const [rejet, setRejet] = useState<{ id: number; motif: string } | null>(null);

  const charger = useCallback(() => {
    api
      .get<{ data: Demande[] }>(`/super-admin/revendications${statut ? `?statut=${statut}` : ''}`)
      .then((r) => setDemandes(r.data))
      .catch(() => setErreur('Impossible de charger les demandes.'));
  }, [statut]);

  useEffect(charger, [charger]);

  async function traiter(action: () => Promise<unknown>) {
    setErreur(null);
    try {
      await action();
      setRejet(null);
      charger();
    } catch (err) {
      setErreur(err instanceof ApiError ? err.message : 'Action impossible.');
    }
  }

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <PageHeader
        title="Revendications de fiches"
        description="Appelez le cabinet au numéro de la fiche pour vérifier que le demandeur en fait partie, puis approuvez."
        actions={
          <Select id="filtre-revendications" value={statut} onChange={(e) => setStatut(e.target.value)} aria-label="Filtrer par statut">
            <option value="en_attente">À traiter</option>
            <option value="approuvee">Approuvées</option>
            <option value="rejetee">Rejetées</option>
            <option value="">Toutes</option>
          </Select>
        }
      />
      <ErrorText>{erreur}</ErrorText>

      {demandes.length === 0 && <p className="text-sm text-slate-400">Aucune demande pour ce filtre.</p>}

      {demandes.map((d) => (
        <Card key={d.id} className="flex flex-col gap-3">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p className="font-semibold text-slate-900">{d.cabinet.nom}</p>
              <p className="text-sm text-slate-500">
                {[d.cabinet.quartier, d.cabinet.ville].filter(Boolean).join(', ')}
                {d.cabinet.telephone && ` · Tél. fiche : ${d.cabinet.telephone}`}
                {d.cabinet.whatsapp && ` · WhatsApp fiche : +${d.cabinet.whatsapp}`}
              </p>
            </div>
            <Badge tone={TONS[d.statut]}>{LIBELLES[d.statut]}</Badge>
          </div>
          <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            <div><dt className="inline text-slate-500">Demandeur : </dt><dd className="inline text-slate-800">{d.nom}{d.fonction && ` (${d.fonction})`}</dd></div>
            <div><dt className="inline text-slate-500">Reçue le : </dt><dd className="inline text-slate-800">{new Date(d.created_at).toLocaleDateString('fr-FR')}</dd></div>
            <div><dt className="inline text-slate-500">E-mail : </dt><dd className="inline text-slate-800">{d.email}</dd></div>
            <div><dt className="inline text-slate-500">Téléphone : </dt><dd className="inline text-slate-800">{d.telephone}</dd></div>
          </dl>
          {d.message && <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{d.message}</p>}
          {d.motif_rejet && <p className="text-sm text-red-700">Motif du rejet : {d.motif_rejet}</p>}

          {d.statut === 'en_attente' && (
            rejet?.id === d.id ? (
              <div className="flex flex-col gap-2">
                <Textarea id={`motif-${d.id}`} rows={2} placeholder="Motif communiqué au demandeur (facultatif)" value={rejet.motif} onChange={(e) => setRejet({ id: d.id, motif: e.target.value })} />
                <div className="flex gap-2">
                  <Button variant="danger" onClick={() => traiter(() => api.post(`/super-admin/revendications/${d.id}/rejeter`, { motif_rejet: rejet.motif || null }))}>Confirmer le rejet</Button>
                  <Button variant="secondary" onClick={() => setRejet(null)}>Annuler</Button>
                </div>
              </div>
            ) : (
              <div className="flex flex-wrap gap-2">
                <Button onClick={() => traiter(() => api.post(`/super-admin/revendications/${d.id}/approuver`))}>
                  Approuver et créer le compte
                </Button>
                <Button variant="secondary" onClick={() => setRejet({ id: d.id, motif: '' })}>Rejeter</Button>
              </div>
            )
          )}
        </Card>
      ))}
    </div>
  );
}
