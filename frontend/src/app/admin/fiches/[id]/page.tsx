'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, Card, ErrorText, Field, Input, PageHeader } from '@/components/ui';
import type { AdminFiche } from '@/types';

const STATUT_PUB_TONE: Record<string, 'slate' | 'green' | 'amber' | 'red'> = {
  brouillon: 'slate', publiee: 'green', suspendue: 'amber', archivee: 'red',
};

export default function AdminFicheDetailPage() {
  const params = useParams<{ id: string }>();
  const [fiche, setFiche] = useState<AdminFiche | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [action, setAction] = useState<string | null>(null);

  const [emailInvitation, setEmailInvitation] = useState('');
  const [lienInvitation, setLienInvitation] = useState<string | null>(null);
  const [invitationError, setInvitationError] = useState<string | null>(null);
  const [envoiInvitation, setEnvoiInvitation] = useState(false);

  function load() {
    api.get<AdminFiche>(`/admin/fiches/${params.id}`).then(setFiche);
  }

  useEffect(load, [params.id]);

  async function transition(nom: 'verifier' | 'publier' | 'suspendre' | 'archiver') {
    if (action) return;
    setError(null);
    setAction(nom);
    try {
      await api.post(`/admin/fiches/${params.id}/${nom}`);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Action impossible.');
    } finally {
      setAction(null);
    }
  }

  async function envoyerInvitation(e: React.FormEvent) {
    e.preventDefault();
    if (envoiInvitation) return;
    setInvitationError(null);
    setEnvoiInvitation(true);
    try {
      const res = await api.post<{ lien_activation: string }>(`/admin/fiches/${params.id}/invitations`, { email: emailInvitation });
      setLienInvitation(res.lien_activation);
      setEmailInvitation('');
    } catch (err) {
      setInvitationError(err instanceof ApiError ? err.message : "Impossible d'envoyer l'invitation.");
    } finally {
      setEnvoiInvitation(false);
    }
  }

  if (!fiche) return <p className="text-slate-400">Chargement…</p>;

  return (
    <div>
      <PageHeader
        title={fiche.nom}
        description={fiche.categorie_fiche === 'structure' ? 'Structure sanitaire' : 'Professionnel indépendant'}
        actions={<Badge tone={STATUT_PUB_TONE[fiche.statut_publication]}>{fiche.statut_publication}</Badge>}
      />

      <ErrorText>{error}</ErrorText>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 font-semibold text-slate-900">Cycle de vie de la fiche</h2>
          <p className="mb-4 text-sm text-slate-600">
            Vérification : <strong>{fiche.statut_verification}</strong> · Publication : <strong>{fiche.statut_publication}</strong>
          </p>
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" disabled={!!action || fiche.statut_verification === 'verifiee'} onClick={() => transition('verifier')}>
              Marquer vérifiée
            </Button>
            <Button disabled={!!action || fiche.statut_publication === 'publiee'} onClick={() => transition('publier')}>
              Publier
            </Button>
            <Button variant="secondary" disabled={!!action || fiche.statut_publication === 'suspendue'} onClick={() => transition('suspendre')}>
              Suspendre
            </Button>
            <Button variant="danger" disabled={!!action || fiche.statut_publication === 'archivee'} onClick={() => transition('archiver')}>
              Archiver
            </Button>
          </div>
          {fiche.statut_publication === 'publiee' && (
            <Link href={`/fiche/${fiche.slug}`} target="_blank" className="mt-4 inline-block text-sm text-doktis-petrol hover:underline">
              Voir la fiche publique ↗
            </Link>
          )}
        </Card>

        <Card>
          <h2 className="mb-3 font-semibold text-slate-900">Coordonnées</h2>
          <ul className="space-y-1 text-sm text-slate-600">
            <li>{fiche.adresse ?? '—'}</li>
            <li>{[fiche.quartier, fiche.ville].filter(Boolean).join(', ') || '—'}</li>
            <li>{fiche.telephone ?? '—'}</li>
            <li>{fiche.email ?? '—'}</li>
          </ul>
        </Card>

        <Card>
          <h2 className="mb-3 font-semibold text-slate-900">Compte partenaire</h2>
          {fiche.users && fiche.users.length > 0 ? (
            <ul className="space-y-1 text-sm">
              {fiche.users.map((u) => (
                <li key={u.id} className="flex items-center justify-between">
                  <span>{u.name} ({u.email})</span>
                  <Badge tone={u.actif ? 'green' : 'slate'}>{u.actif ? 'Actif' : 'Inactif'}</Badge>
                </li>
              ))}
            </ul>
          ) : (
            <>
              <p className="mb-3 text-sm text-slate-400">Aucun compte partenaire activé pour cette fiche.</p>
              <form onSubmit={envoyerInvitation} className="flex flex-col gap-3">
                <ErrorText>{invitationError}</ErrorText>
                <Field label="E-mail du responsable à inviter">
                  <Input type="email" required value={emailInvitation} onChange={(e) => setEmailInvitation(e.target.value)} />
                </Field>
                <Button type="submit" disabled={envoiInvitation}>
                  {envoiInvitation ? 'Envoi…' : "Générer le lien d'invitation"}
                </Button>
              </form>
              {lienInvitation && (
                <div className="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                  <p className="mb-1 font-medium text-slate-700">Lien à transmettre vous-même au partenaire :</p>
                  <code className="break-all">{lienInvitation}</code>
                </div>
              )}
            </>
          )}
        </Card>

        <Card>
          <h2 className="mb-3 font-semibold text-slate-900">Spécialités</h2>
          <div className="flex flex-wrap gap-1.5">
            {fiche.specialites?.length ? fiche.specialites.map((s) => <Badge key={s.id} tone="blue">{s.nom}</Badge>) : <p className="text-sm text-slate-400">Aucune.</p>}
          </div>
        </Card>
      </div>
    </div>
  );
}
