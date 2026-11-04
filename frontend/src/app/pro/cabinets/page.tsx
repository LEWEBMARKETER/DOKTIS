'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { api, ApiError } from '@/lib/api';
import { Badge, Button, Card, ErrorText, Field, Input, Modal, PageHeader, Select, Textarea } from '@/components/ui';
import type { Cabinet } from '@/types';
import type { Specialite } from '@/types/directory';

interface CabinetRow extends Cabinet {
  users_count: number;
  revendications_en_attente_count: number;
}

const STATUTS = {
  verifiee: { label: 'Vérifiée', ton: 'green' },
  en_attente: { label: 'Inscription à valider', ton: 'amber' },
  non_revendiquee: { label: 'Non revendiquée', ton: 'slate' },
} as const;

export default function FichesAnnuairePage() {
  const [cabinets, setCabinets] = useState<CabinetRow[]>([]);
  const [filtre, setFiltre] = useState('');
  const [creation, setCreation] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);

  const charger = useCallback(() => {
    api
      .get<CabinetRow[]>(`/cabinets${filtre ? `?statut_fiche=${filtre}` : ''}`)
      .then(setCabinets)
      .catch(() => setErreur('Impossible de charger les fiches.'));
  }, [filtre]);

  useEffect(charger, [charger]);

  async function modifier(cabinet: CabinetRow, valeurs: Record<string, unknown>) {
    setErreur(null);
    try {
      await api.patch(`/cabinets/${cabinet.id}`, valeurs);
      charger();
    } catch (err) {
      setErreur(err instanceof ApiError ? err.message : 'Modification impossible.');
    }
  }

  return (
    <div>
      <PageHeader
        title="Fiches annuaire"
        description="Créez les fiches d'une ville, validez les inscriptions et suivez les revendications."
        actions={<Button onClick={() => setCreation(true)}>Nouvelle fiche</Button>}
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <Select id="filtre-statut" value={filtre} onChange={(e) => setFiltre(e.target.value)} aria-label="Filtrer par statut">
          <option value="">Tous les statuts</option>
          <option value="en_attente">Inscriptions à valider</option>
          <option value="non_revendiquee">Non revendiquées</option>
          <option value="verifiee">Vérifiées</option>
        </Select>
        <span className="text-sm text-slate-500">{cabinets.length} fiche{cabinets.length > 1 ? 's' : ''}</span>
      </div>
      <ErrorText>{erreur}</ErrorText>

      <Card className="mt-2 overflow-x-auto p-0">
        <table className="w-full min-w-[760px] text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Cabinet</th>
              <th className="px-4 py-3">Lieu</th>
              <th className="px-4 py-3">Statut</th>
              <th className="px-4 py-3">WhatsApp</th>
              <th className="px-4 py-3">Annuaire</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {cabinets.map((c) => {
              const statut = STATUTS[c.statut_fiche ?? 'verifiee'];
              return (
                <tr key={c.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-800">{c.nom}</p>
                    <p className="text-xs text-slate-500">{c.users_count} compte{c.users_count > 1 ? 's' : ''}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{[c.quartier, c.ville].filter(Boolean).join(', ') || '–'}</td>
                  <td className="px-4 py-3">
                    <div className="flex flex-col items-start gap-1">
                      <Badge tone={statut.ton}>{statut.label}</Badge>
                      {c.revendications_en_attente_count > 0 && (
                        <Link href="/pro/revendications" className="text-xs font-medium text-amber-700 hover:underline">
                          {c.revendications_en_attente_count} revendication(s) à traiter
                        </Link>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{c.whatsapp ? `+${c.whatsapp}` : '–'}</td>
                  <td className="px-4 py-3">
                    <Badge tone={c.visible_annuaire && c.actif ? 'green' : 'slate'}>{c.visible_annuaire && c.actif ? 'Publiée' : 'Masquée'}</Badge>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap justify-end gap-2">
                      {c.statut_fiche === 'en_attente' && (
                        <Button variant="primary" className="px-3 py-1 text-xs" onClick={() => modifier(c, { statut_fiche: 'verifiee', visible_annuaire: true })}>
                          Valider et publier
                        </Button>
                      )}
                      {c.statut_fiche !== 'en_attente' && (
                        <Button variant="secondary" className="px-3 py-1 text-xs" onClick={() => modifier(c, { visible_annuaire: !c.visible_annuaire })}>
                          {c.visible_annuaire ? 'Masquer' : 'Publier'}
                        </Button>
                      )}
                      {c.visible_annuaire && c.statut_fiche !== 'en_attente' && (
                        <Link href={`/cabinet/${c.slug}`} target="_blank" className="self-center text-xs font-medium text-teal-700 hover:underline">
                          Voir ↗
                        </Link>
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}
            {cabinets.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-6 text-center text-slate-400">Aucune fiche pour ce filtre.</td>
              </tr>
            )}
          </tbody>
        </table>
      </Card>

      <NouvelleFiche open={creation} onClose={() => setCreation(false)} onCreated={() => { setCreation(false); charger(); }} />
    </div>
  );
}

function NouvelleFiche({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: () => void }) {
  const vide = { nom: '', type: 'medical', ville: '', quartier: '', adresse: '', telephone: '', whatsapp: '', description: '', accepte_urgences: false };
  const [form, setForm] = useState(vide);
  const [specialites, setSpecialites] = useState<Specialite[]>([]);
  const [choix, setChoix] = useState<number[]>([]);
  const [erreur, setErreur] = useState<string | null>(null);
  const [envoi, setEnvoi] = useState(false);

  useEffect(() => {
    if (open) api.get<Specialite[]>('/directory/specialites').then(setSpecialites).catch(() => setSpecialites([]));
  }, [open]);

  const maj = (cle: keyof typeof vide) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [cle]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value }));

  async function creer(e: React.FormEvent) {
    e.preventDefault();
    setEnvoi(true);
    setErreur(null);
    try {
      await api.post('/cabinets', {
        ...form,
        whatsapp: form.whatsapp || null,
        telephone: form.telephone || null,
        specialite_ids: choix,
      });
      setForm(vide);
      setChoix([]);
      onCreated();
    } catch (err) {
      setErreur(err instanceof ApiError ? err.message : 'Création impossible.');
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Nouvelle fiche annuaire">
      <form onSubmit={creer} className="flex flex-col gap-3">
        <p className="text-sm text-slate-500">
          La fiche est publiée comme « non revendiquée », avec la mention « informations non vérifiées », jusqu&apos;à ce que le cabinet la revendique.
        </p>
        <ErrorText>{erreur}</ErrorText>
        <Field label="Nom du cabinet"><Input id="nf-nom" required value={form.nom} onChange={maj('nom')} /></Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Ville"><Input id="nf-ville" required value={form.ville} onChange={maj('ville')} placeholder="Douala" /></Field>
          <Field label="Quartier"><Input id="nf-quartier" value={form.quartier} onChange={maj('quartier')} placeholder="Akwa" /></Field>
          <Field label="Téléphone"><Input id="nf-telephone" value={form.telephone} onChange={maj('telephone')} /></Field>
          <Field label="WhatsApp (international)"><Input id="nf-whatsapp" value={form.whatsapp} onChange={maj('whatsapp')} placeholder="+237 6XX XX XX XX" /></Field>
          <Field label="Adresse"><Input id="nf-adresse" value={form.adresse} onChange={maj('adresse')} /></Field>
          <Field label="Type">
            <Select id="nf-type" value={form.type} onChange={maj('type')}>
              <option value="medical">Médical</option>
              <option value="dentaire">Dentaire</option>
              <option value="mixte">Mixte</option>
            </Select>
          </Field>
        </div>
        <div className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-700">Spécialités</span>
          <div className="flex flex-wrap gap-2">
            {specialites.map((s) => {
              const actif = choix.includes(s.id);
              return (
                <label key={s.id} className={`cursor-pointer rounded-full border px-3 py-1 text-xs ${actif ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-300 text-slate-700'}`}>
                  <input id={`nf-specialite-${s.id}`} type="checkbox" className="sr-only" checked={actif} onChange={() => setChoix((c) => (actif ? c.filter((id) => id !== s.id) : [...c, s.id]))} />
                  {s.nom}
                </label>
              );
            })}
          </div>
        </div>
        <Field label="Présentation"><Textarea id="nf-description" rows={3} value={form.description} onChange={maj('description')} /></Field>
        <label className="inline-flex items-center gap-2 text-sm text-slate-700">
          <input id="nf-urgences" type="checkbox" checked={form.accepte_urgences} onChange={maj('accepte_urgences')} className="h-4 w-4 accent-teal-600" />
          Accepte les urgences
        </label>
        <Button type="submit" disabled={envoi}>{envoi ? 'Création…' : 'Créer la fiche'}</Button>
      </form>
    </Modal>
  );
}
