'use client';

import { useEffect, useRef, useState } from 'react';
import { patientApi } from '@/lib/patient-api';
import { usePatientAuth } from '@/lib/patient-auth-context';
import { PageHeader } from '@/components/ui';
import type { PatientConversation, PatientMessage } from '@/types/patient';

export default function MessageriePage() {
  const { account } = usePatientAuth();
  const [conversations, setConversations] = useState<PatientConversation[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [messages, setMessages] = useState<PatientMessage[]>([]);
  const [loadingConversations, setLoadingConversations] = useState(true);
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    patientApi
      .get<PatientConversation[]>('/patient/conversations')
      .then((data) => {
        setConversations(data);
        if (data.length > 0) setSelectedId(data[0].id);
      })
      .finally(() => setLoadingConversations(false));
  }, []);

  useEffect(() => {
    if (!selectedId) return;
    setLoadingMessages(true);
    patientApi
      .get<{ messages: PatientMessage[] }>(`/patient/conversations/${selectedId}/messages`)
      .then((data) => setMessages(data.messages))
      .finally(() => setLoadingMessages(false));
  }, [selectedId]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  async function envoyer(e: React.FormEvent) {
    e.preventDefault();
    if (!selectedId || !draft.trim()) return;
    setSending(true);
    try {
      const message = await patientApi.post<PatientMessage>(`/patient/conversations/${selectedId}/messages`, {
        contenu: draft,
      });
      setMessages((m) => [...m, message]);
      setDraft('');
    } finally {
      setSending(false);
    }
  }

  const selected = conversations.find((c) => c.id === selectedId);

  return (
    <div>
      <PageHeader title="Messagerie" description="Échangez avec les cabinets où vous avez un dossier." />

      <div className="flex h-[70vh] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="w-72 shrink-0 overflow-y-auto border-r border-slate-200">
          {loadingConversations && <p className="p-4 text-sm text-slate-400">Chargement…</p>}
          {!loadingConversations && conversations.length === 0 && (
            <p className="p-4 text-sm text-slate-400">Aucune conversation pour le moment.</p>
          )}
          {conversations.map((c) => (
            <button
              key={c.id}
              onClick={() => setSelectedId(c.id)}
              className={`block w-full border-b border-slate-100 px-4 py-3 text-left text-sm ${
                selectedId === c.id ? 'bg-teal-50' : 'hover:bg-slate-50'
              }`}
            >
              <p className="font-medium text-slate-800">{c.cabinet.nom}</p>
            </button>
          ))}
        </div>

        <div className="flex flex-1 flex-col">
          {!selected && <div className="flex flex-1 items-center justify-center text-sm text-slate-400">Sélectionnez une conversation</div>}

          {selected && (
            <>
              <div className="border-b border-slate-200 px-4 py-3">
                <p className="font-medium text-slate-900">{selected.cabinet.nom}</p>
              </div>

              <div className="flex-1 space-y-2 overflow-y-auto p-4">
                {loadingMessages && <p className="text-sm text-slate-400">Chargement…</p>}
                {!loadingMessages &&
                  messages.map((m) => (
                    <div key={m.id} className={`flex ${m.expediteur_type === 'patient' ? 'justify-end' : 'justify-start'}`}>
                      <div
                        className={`max-w-[75%] rounded-lg px-3 py-2 text-sm ${
                          m.expediteur_type === 'patient' ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-700'
                        }`}
                      >
                        {m.contenu}
                      </div>
                    </div>
                  ))}
                <div ref={bottomRef} />
              </div>

              <form onSubmit={envoyer} className="flex gap-2 border-t border-slate-200 p-3">
                <input
                  value={draft}
                  onChange={(e) => setDraft(e.target.value)}
                  placeholder="Votre message…"
                  className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                />
                <button
                  type="submit"
                  disabled={sending || !draft.trim()}
                  className="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:bg-teal-300"
                >
                  Envoyer
                </button>
              </form>
            </>
          )}
        </div>
      </div>
      <p className="mt-2 text-xs text-slate-400">Connecté en tant que {account?.email}</p>
    </div>
  );
}
