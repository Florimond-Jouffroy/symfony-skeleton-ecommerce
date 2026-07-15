import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, Send } from 'lucide-react';
import { api } from '../../utils/api';

const STATUS_LABELS = {
    open:        { label: 'Ouvert',   cls: 'bg-blue-100 text-blue-700' },
    in_progress: { label: 'En cours', cls: 'bg-yellow-100 text-yellow-700' },
    closed:      { label: 'Fermé',    cls: 'bg-gray-100 text-gray-600' },
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

export default function SupportDetail({ urls, ticketId }) {
    const [ticket, setTicket]   = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError]     = useState(null);
    const [reply, setReply]     = useState('');
    const [sending, setSending] = useState(false);
    const [replyError, setReplyError] = useState(null);
    const bottomRef = useRef(null);

    const detailUrl = `${urls.support}/${ticketId}`;
    const replyUrl  = `${urls.support}/${ticketId}/repondre`;

    useEffect(() => {
        api.get(detailUrl)
            .then(data => setTicket(data))
            .catch(() => setError('Ticket introuvable.'))
            .finally(() => setLoading(false));
    }, [ticketId]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [ticket?.messages?.length]);

    const handleReply = async () => {
        if (!reply.trim()) return;
        setReplyError(null);
        setSending(true);
        try {
            const updated = await api.post(replyUrl, { body: reply });
            setTicket(updated);
            setReply('');
        } catch (err) {
            setReplyError(err.message ?? 'Erreur lors de l\'envoi.');
        } finally {
            setSending(false);
        }
    };

    if (loading) {
        return (
            <div className="flex justify-center py-12">
                <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
        );
    }

    if (error || !ticket) {
        return (
            <div className="text-center py-12">
                <p className="text-sm text-muted-foreground">{error ?? 'Ticket introuvable.'}</p>
                <Link to="/support" className="mt-4 inline-block text-sm text-primary underline underline-offset-2">Retour</Link>
            </div>
        );
    }

    return (
        <div className="space-y-5">
            <div className="flex items-center gap-3">
                <Link to="/support" className="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                    <ArrowLeft className="size-4" />
                </Link>
                <div className="flex-1 min-w-0">
                    <h2 className="font-semibold text-base truncate">{ticket.subject}</h2>
                    <p className="text-xs text-muted-foreground">Ouvert le {ticket.createdAt}</p>
                </div>
                <StatusBadge status={ticket.status} />
            </div>

            <div className="rounded-lg border bg-card p-4 space-y-3 min-h-[250px] max-h-[500px] overflow-y-auto">
                {ticket.messages.map(msg => (
                    <Message key={msg.id} msg={msg} />
                ))}
                <div ref={bottomRef} />
            </div>

            {ticket.status !== 'closed' ? (
                <div className="rounded-lg border bg-card p-4 space-y-3">
                    {replyError && <p className="text-sm text-destructive">{replyError}</p>}
                    <textarea
                        rows={3}
                        value={reply}
                        onChange={e => setReply(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && e.ctrlKey && (e.preventDefault(), handleReply())}
                        className="w-full resize-y rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Votre réponse… (Ctrl+Entrée pour envoyer)"
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
            ) : (
                <p className="text-center text-sm text-muted-foreground py-4">
                    Ce ticket est fermé.{' '}
                    <a href="/contact" className="text-primary underline underline-offset-2">Ouvrir une nouvelle demande</a>
                </p>
            )}
        </div>
    );
}
