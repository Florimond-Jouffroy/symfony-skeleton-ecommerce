import React, { useEffect, useState } from 'react';
import { Send } from 'lucide-react';
import { api } from '../../utils/api';

const STATUS_LABELS = {
    open:        { label: 'Ouvert',     cls: 'bg-blue-100 text-blue-700' },
    in_progress: { label: 'En cours',   cls: 'bg-yellow-100 text-yellow-700' },
    closed:      { label: 'Fermé',      cls: 'bg-gray-100 text-gray-600' },
};

function StatusBadge({ status }) {
    const { label, cls } = STATUS_LABELS[status] ?? { label: status, cls: 'bg-muted' };
    return <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${cls}`}>{label}</span>;
}

function Message({ msg }) {
    return (
        <div className={`flex ${msg.isFromAdmin ? 'justify-start' : 'justify-end'}`}>
            <div className={`max-w-[80%] rounded-2xl px-4 py-3 text-sm ${
                msg.isFromAdmin
                    ? 'rounded-tl-sm bg-muted text-foreground'
                    : 'rounded-tr-sm bg-primary text-primary-foreground'
            }`}>
                <p className="font-medium text-xs mb-1 opacity-70">{msg.authorName}</p>
                <p className="whitespace-pre-line leading-relaxed">{msg.body}</p>
                <p className="mt-1 text-[10px] opacity-50">{msg.createdAt}</p>
            </div>
        </div>
    );
}

export default function SuiviPage({ urls }) {
    const params = new URLSearchParams(window.location.search);
    const [token, setToken]   = useState(params.get('token') ?? '');
    const [input, setInput]   = useState(token);
    const [ticket, setTicket] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError]   = useState(null);
    const [reply, setReply]   = useState('');
    const [sending, setSending] = useState(false);
    const [replyError, setReplyError] = useState(null);

    const fetchTicket = async (t) => {
        if (!t.trim()) return;
        setLoading(true);
        setError(null);
        try {
            const data = await api.get(urls.suivi, { token: t.trim() });
            setTicket(data);
            setToken(t.trim());
        } catch {
            setError('Aucun ticket trouvé pour ce lien. Vérifiez que vous avez utilisé le lien complet reçu par e-mail.');
            setTicket(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (token) fetchTicket(token);
    }, []);

    const handleReply = async () => {
        if (!reply.trim()) return;
        setReplyError(null);
        setSending(true);
        try {
            const updated = await api.post(urls.suiviReply, { token, body: reply });
            setTicket(updated);
            setReply('');
        } catch (err) {
            setReplyError(err.message ?? 'Erreur lors de l\'envoi.');
        } finally {
            setSending(false);
        }
    };

    return (
        <div className="mx-auto max-w-2xl px-4 py-16">
            <div className="mb-8 text-center">
                <h1 className="text-3xl font-bold tracking-tight">Suivi de demande</h1>
                <p className="mt-3 text-muted-foreground">Entrez le code de votre ticket ou utilisez le lien reçu par e-mail.</p>
            </div>

            {!ticket && (
                <div className="rounded-lg border bg-card p-6">
                    <label className="mb-2 block text-sm font-medium">Code de suivi</label>
                    <div className="flex gap-2">
                        <input
                            value={input}
                            onChange={e => setInput(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), fetchTicket(input))}
                            className="flex-1 rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary font-mono"
                            placeholder="Collez votre code ici…"
                        />
                        <button
                            type="button"
                            onClick={() => fetchTicket(input)}
                            disabled={loading}
                            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
                        >
                            {loading ? '…' : 'Accéder'}
                        </button>
                    </div>
                    {error && <p className="mt-3 text-sm text-destructive">{error}</p>}
                </div>
            )}

            {ticket && (
                <div className="space-y-4">
                    <div className="rounded-lg border bg-card p-4 flex items-start justify-between gap-4">
                        <div>
                            <p className="font-semibold">{ticket.subject}</p>
                            <p className="text-xs text-muted-foreground mt-0.5">Ouvert le {ticket.createdAt}</p>
                        </div>
                        <StatusBadge status={ticket.status} />
                    </div>

                    <div className="rounded-lg border bg-card p-4 space-y-3 min-h-[200px]">
                        {ticket.messages.map(msg => (
                            <Message key={msg.id} msg={msg} />
                        ))}
                    </div>

                    {ticket.status !== 'closed' && (
                        <div className="rounded-lg border bg-card p-4 space-y-3">
                            {replyError && <p className="text-sm text-destructive">{replyError}</p>}
                            <textarea
                                rows={3}
                                value={reply}
                                onChange={e => setReply(e.target.value)}
                                className="w-full resize-y rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                                placeholder="Votre réponse…"
                            />
                            <div className="flex justify-end">
                                <button
                                    type="button"
                                    onClick={handleReply}
                                    disabled={sending || !reply.trim()}
                                    className="flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
                                >
                                    <Send className="size-3.5" />
                                    {sending ? 'Envoi…' : 'Répondre'}
                                </button>
                            </div>
                        </div>
                    )}

                    {ticket.status === 'closed' && (
                        <p className="text-center text-sm text-muted-foreground">Ce ticket est fermé. Pour une nouvelle demande, <a href="/contact" className="text-primary underline underline-offset-2">contactez-nous</a>.</p>
                    )}

                    <button
                        type="button"
                        onClick={() => { setTicket(null); setInput(''); setToken(''); }}
                        className="text-xs text-muted-foreground hover:text-foreground underline underline-offset-2 transition-colors"
                    >
                        Accéder à un autre ticket
                    </button>
                </div>
            )}
        </div>
    );
}
