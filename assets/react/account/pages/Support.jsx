import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus } from 'lucide-react';
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

export default function Support({ urls }) {
    const [tickets, setTickets] = useState([]);
    const [loading, setLoading] = useState(true);
    const [creating, setCreating] = useState(false);
    const [form, setForm]     = useState({ subject: '', body: '' });
    const [sending, setSending] = useState(false);
    const [error, setError]   = useState(null);

    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    useEffect(() => {
        api.get(urls.support)
            .then(data => setTickets(data ?? []))
            .finally(() => setLoading(false));
    }, []);

    const handleCreate = async (e) => {
        e.preventDefault();
        setError(null);
        setSending(true);
        try {
            const ticket = await api.post(urls.support, form);
            setTickets(t => [ticket, ...t]);
            setCreating(false);
            setForm({ subject: '', body: '' });
        } catch (err) {
            setError(err.message ?? 'Une erreur est survenue.');
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

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-semibold">Mes demandes</h2>
                {!creating && (
                    <button
                        type="button"
                        onClick={() => setCreating(true)}
                        className="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                    >
                        <Plus className="size-4" />
                        Nouvelle demande
                    </button>
                )}
            </div>

            {creating && (
                <form onSubmit={handleCreate} className="rounded-lg border bg-card p-5 space-y-4">
                    <h3 className="font-medium">Nouvelle demande</h3>
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <div>
                        <label className="mb-1 block text-sm font-medium">Sujet</label>
                        <input
                            required
                            value={form.subject}
                            onChange={e => set('subject', e.target.value)}
                            className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                            placeholder="Ex. Problème avec ma commande"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium">Message</label>
                        <textarea
                            required
                            rows={5}
                            value={form.body}
                            onChange={e => set('body', e.target.value)}
                            className="w-full resize-y rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                            placeholder="Décrivez votre problème en détail…"
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => { setCreating(false); setError(null); }}
                            className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent transition-colors"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            disabled={sending}
                            className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
                        >
                            {sending ? 'Envoi…' : 'Envoyer'}
                        </button>
                    </div>
                </form>
            )}

            {tickets.length === 0 && !creating && (
                <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                    Aucune demande pour le moment.
                </div>
            )}

            <div className="space-y-3">
                {tickets.map(ticket => (
                    <Link
                        key={ticket.id}
                        to={`/support/${ticket.id}`}
                        className="block rounded-lg border bg-card p-4 hover:bg-accent/30 transition-colors"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="font-medium text-sm truncate">{ticket.subject}</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    {ticket.messageCount} message{ticket.messageCount !== 1 ? 's' : ''} · Mis à jour le {ticket.updatedAt}
                                </p>
                            </div>
                            <StatusBadge status={ticket.status} />
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}
