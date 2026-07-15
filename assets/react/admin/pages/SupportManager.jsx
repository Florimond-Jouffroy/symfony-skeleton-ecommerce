import React, { useEffect, useRef, useState } from 'react';
import { ArrowLeft, ExternalLink, Send, X } from 'lucide-react';
import { api } from '../../utils/api';

const STATUS_LABELS = {
    open:        { label: 'Ouvert',   cls: 'bg-blue-100 text-blue-700' },
    in_progress: { label: 'En cours', cls: 'bg-yellow-100 text-yellow-700' },
    closed:      { label: 'Fermé',    cls: 'bg-gray-100 text-gray-600' },
};

const STATUS_OPTIONS = [
    { value: 'open',        label: 'Ouvert' },
    { value: 'in_progress', label: 'En cours' },
    { value: 'closed',      label: 'Fermé' },
];

const ORDER_STATUS_LABELS = {
    pending:   { label: 'En attente',  cls: 'bg-yellow-100 text-yellow-700' },
    confirmed: { label: 'Confirmée',   cls: 'bg-blue-100 text-blue-700' },
    shipped:   { label: 'Expédiée',    cls: 'bg-purple-100 text-purple-700' },
    delivered: { label: 'Livrée',      cls: 'bg-green-100 text-green-700' },
    cancelled: { label: 'Annulée',     cls: 'bg-gray-100 text-gray-500' },
    refunded:  { label: 'Remboursée',  cls: 'bg-red-100 text-red-700' },
};

function euros(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

function StatusBadge({ status }) {
    const { label, cls } = STATUS_LABELS[status] ?? { label: status, cls: 'bg-muted' };
    return <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${cls}`}>{label}</span>;
}

function Message({ msg }) {
    return (
        <div className={`flex ${msg.isFromAdmin ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-[80%] rounded-2xl px-4 py-3 text-sm ${
                msg.isFromAdmin
                    ? 'rounded-tr-sm bg-primary text-primary-foreground'
                    : 'rounded-tl-sm bg-muted text-foreground'
            }`}>
                <p className="font-medium text-xs mb-1 opacity-70">{msg.authorName}</p>
                <p className="whitespace-pre-line leading-relaxed">{msg.body}</p>
                <p className="mt-1 text-[10px] opacity-50">{msg.createdAt}</p>
            </div>
        </div>
    );
}

function TicketDetail({ ticket: initial, supportUrl, onUpdate, onBack, onSelectTicket, permissions = {} }) {
    const [ticket, setTicket]   = useState(initial);
    const [reply, setReply]     = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError]     = useState(null);
    const [changingStatus, setChangingStatus] = useState(false);
    const bottomRef = useRef(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [ticket.messages?.length]);

    const handleReply = async () => {
        if (!reply.trim()) return;
        setError(null);
        setSending(true);
        try {
            const updated = await api.post(`${supportUrl}/${ticket.id}/repondre`, { body: reply });
            setTicket(updated);
            setReply('');
            onUpdate(updated);
        } catch (err) {
            setError(err.message ?? 'Erreur lors de l\'envoi.');
        } finally {
            setSending(false);
        }
    };

    const handleStatus = async (status) => {
        setChangingStatus(true);
        try {
            const updated = await api.patch(`${supportUrl}/${ticket.id}/statut`, { status });
            setTicket(updated);
            onUpdate(updated);
        } catch {
            // silently ignore
        } finally {
            setChangingStatus(false);
        }
    };

    return (
        <div className="flex gap-5 items-start">

            {/* ── Colonne principale : fil de discussion ── */}
            <div className="min-w-0 flex-1 space-y-4">
                {/* En-tête */}
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={onBack}
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="size-4" />
                    </button>
                    <div className="min-w-0">
                        <h3 className="font-semibold truncate">{ticket.subject}</h3>
                        <p className="text-xs text-muted-foreground">Ticket #{ticket.id} · {ticket.messageCount} message{ticket.messageCount !== 1 ? 's' : ''}</p>
                    </div>
                </div>

                {/* Messages */}
                <div className="rounded-lg border bg-card p-4 space-y-3 min-h-[300px] max-h-[480px] overflow-y-auto">
                    {(ticket.messages ?? []).map(msg => (
                        <Message key={msg.id} msg={msg} />
                    ))}
                    <div ref={bottomRef} />
                </div>

                {/* Zone de réponse */}
                {ticket.status !== 'closed' && permissions.canReplySupport !== false ? (
                    <div className="space-y-2">
                        {error && <p className="text-sm text-destructive">{error}</p>}
                        <textarea
                            rows={4}
                            value={reply}
                            onChange={e => setReply(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && e.ctrlKey && (e.preventDefault(), handleReply())}
                            className="w-full resize-y rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                            placeholder="Votre réponse au client… (Ctrl+Entrée pour envoyer)"
                        />
                        <div className="flex justify-end">
                            <button
                                type="button"
                                onClick={handleReply}
                                disabled={sending || !reply.trim()}
                                className="flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
                            >
                                <Send className="size-3.5" />
                                {sending ? 'Envoi…' : 'Répondre et notifier'}
                            </button>
                        </div>
                    </div>
                ) : (
                    <p className="text-center text-sm text-muted-foreground py-2">
                        {ticket.status === 'closed' ? 'Ce ticket est fermé.' : ''}
                    </p>
                )}
            </div>

            {/* ── Colonne droite : statut + infos contact ── */}
            <div className="w-64 shrink-0 space-y-4">

                {/* Statut */}
                <div className="rounded-lg border bg-card p-4 space-y-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Statut</p>
                    <div className="space-y-1.5">
                        {STATUS_OPTIONS.map(o => {
                            const isActive = ticket.status === o.value;
                            const { cls } = STATUS_LABELS[o.value] ?? { cls: 'bg-muted text-muted-foreground' };
                            return (
                                <button
                                    key={o.value}
                                    type="button"
                                    disabled={changingStatus || isActive || permissions.canEditSupport === false}
                                    onClick={() => handleStatus(o.value)}
                                    className={`w-full rounded-md px-3 py-2 text-left text-xs font-medium transition-colors disabled:cursor-default ${
                                        isActive
                                            ? cls
                                            : 'text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-60'
                                    }`}
                                >
                                    {isActive && <span className="mr-1.5">●</span>}{o.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Infos contact */}
                <div className="rounded-lg border bg-card p-4 space-y-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Contact</p>

                    <div className="flex items-center gap-2.5">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold text-foreground">
                            {(ticket.contactName?.[0] ?? '?').toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-medium truncate">{ticket.contactName}</p>
                            <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded-full ${ticket.isGuest ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700'}`}>
                                {ticket.isGuest ? 'Invité' : 'Client connecté'}
                            </span>
                        </div>
                    </div>

                    <div className="space-y-2 text-xs">
                        <div>
                            <p className="text-muted-foreground mb-0.5">E-mail</p>
                            <a
                                href={`mailto:${ticket.contactEmail}`}
                                className="text-primary underline underline-offset-2 break-all hover:opacity-75 transition-opacity"
                            >
                                {ticket.contactEmail}
                            </a>
                        </div>
                        <div>
                            <p className="text-muted-foreground mb-0.5">Ouvert le</p>
                            <p className="text-foreground">{ticket.createdAt}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground mb-0.5">Dernière activité</p>
                            <p className="text-foreground">{ticket.updatedAt}</p>
                        </div>
                    </div>
                </div>

                {/* Tickets précédents */}
                {ticket.otherTickets?.length > 0 && (
                    <div className="rounded-lg border bg-card p-4 space-y-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Tickets précédents
                        </p>
                        <div className="space-y-1.5">
                            {ticket.otherTickets.map(ot => {
                                const { label, cls } = STATUS_LABELS[ot.status] ?? { label: ot.status, cls: 'bg-muted text-muted-foreground' };
                                return (
                                    <button
                                        key={ot.id}
                                        type="button"
                                        onClick={() => onSelectTicket(ot.id)}
                                        className="w-full rounded-md p-2 text-left hover:bg-accent transition-colors group"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="text-xs font-medium truncate group-hover:text-primary transition-colors leading-snug">
                                                {ot.subject}
                                            </p>
                                            <span className={`shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded-full ${cls}`}>{label}</span>
                                        </div>
                                        <p className="text-[10px] text-muted-foreground mt-0.5">{ot.updatedAt}</p>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Commandes récentes — uniquement pour les clients connectés */}
                {!ticket.isGuest && (
                    <div className="rounded-lg border bg-card p-4 space-y-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Commandes récentes</p>

                        {(!ticket.recentOrders || ticket.recentOrders.length === 0) ? (
                            <p className="text-xs text-muted-foreground">Aucune commande.</p>
                        ) : (
                            <div className="space-y-2">
                                {ticket.recentOrders.map(order => {
                                    const { label, cls } = ORDER_STATUS_LABELS[order.status] ?? { label: order.status, cls: 'bg-muted text-muted-foreground' };
                                    const Tag = permissions.canViewOrders !== false ? 'a' : 'div';
                                    const linkProps = permissions.canViewOrders !== false
                                        ? { href: `/admin/commandes/${order.id}` }
                                        : {};
                                    return (
                                        <Tag
                                            key={order.id}
                                            {...linkProps}
                                            className="flex items-start justify-between gap-2 rounded-md p-2 hover:bg-accent transition-colors group"
                                        >
                                            <div className="min-w-0">
                                                <p className="text-xs font-medium font-mono truncate group-hover:text-primary transition-colors">
                                                    #{order.orderNumber}
                                                </p>
                                                <p className="text-[10px] text-muted-foreground mt-0.5">{order.createdAt}</p>
                                            </div>
                                            <div className="flex flex-col items-end gap-1 shrink-0">
                                                <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded-full ${cls}`}>{label}</span>
                                                <span className="text-[10px] font-medium text-foreground">{euros(order.total)}</span>
                                            </div>
                                        </Tag>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function SupportManager({ urls, permissions = {} }) {
    const supportUrl = urls.support;
    const [tickets, setTickets]       = useState([]);
    const [loading, setLoading]       = useState(true);
    const [selected, setSelected]     = useState(null);
    const [loadingTicket, setLoadingTicket] = useState(false);
    const [statusFilter, setStatusFilter]   = useState('');
    const [search, setSearch]         = useState('');
    const [error, setError]           = useState(null);

    const load = (status = statusFilter, q = search) => {
        setLoading(true);
        const params = {};
        if (status) params.status = status;
        if (q)      params.search = q;
        api.get(supportUrl, params)
            .then(data => setTickets(data ?? []))
            .catch(() => setError('Impossible de charger les tickets.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { load(); }, []);

    const handleUpdate = (updated) => {
        setTickets(ts => ts.map(t => t.id === updated.id ? { ...t, ...updated } : t));
    };

    const handleFilterStatus = (s) => {
        setStatusFilter(s);
        load(s, search);
    };

    const handleSearch = (q) => {
        setSearch(q);
        load(statusFilter, q);
    };

    const handleSelectTicket = async (id) => {
        setSelected(null);
        setLoadingTicket(true);
        try {
            const [detail] = await Promise.all([
                api.get(`${supportUrl}/${id}`),
                new Promise(r => setTimeout(r, 500)),
            ]);
            setSelected(detail);
        } catch {
            setError('Impossible de charger ce ticket.');
        } finally {
            setLoadingTicket(false);
        }
    };

    if (loadingTicket) {
        return (
            <div className="flex flex-col items-center justify-center py-24 gap-3 text-muted-foreground">
                <div className="size-7 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                <p className="text-sm">Chargement du ticket…</p>
            </div>
        );
    }

    if (selected) {
        return (
            <TicketDetail
                key={selected.id}
                ticket={selected}
                supportUrl={supportUrl}
                onUpdate={handleUpdate}
                onBack={() => setSelected(null)}
                onSelectTicket={handleSelectTicket}
                permissions={permissions}
            />
        );
    }

    return (
        <div className="space-y-5">
            {error && (
                <div className="flex items-center justify-between rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                    <button type="button" onClick={() => setError(null)}><X className="size-4" /></button>
                </div>
            )}

            {/* Filtres */}
            <div className="flex flex-wrap items-center gap-3">
                <input
                    value={search}
                    onChange={e => handleSearch(e.target.value)}
                    className="rounded-md border bg-background px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary w-56"
                    placeholder="Rechercher…"
                />
                <div className="flex gap-1.5">
                    {[{ value: '', label: 'Tous' }, ...STATUS_OPTIONS].map(o => (
                        <button
                            key={o.value}
                            type="button"
                            onClick={() => handleFilterStatus(o.value)}
                            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                statusFilter === o.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'
                            }`}
                        >
                            {o.label}
                        </button>
                    ))}
                </div>
                <span className="ml-auto text-xs text-muted-foreground">{tickets.length} ticket{tickets.length !== 1 ? 's' : ''}</span>
            </div>

            {loading && (
                <div className="flex justify-center py-12">
                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            )}

            {!loading && tickets.length === 0 && (
                <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                    Aucun ticket trouvé.
                </div>
            )}

            <div className="space-y-2">
                {tickets.map(ticket => (
                    <button
                        key={ticket.id}
                        type="button"
                        onClick={() => handleSelectTicket(ticket.id)}
                        className="w-full rounded-lg border bg-card p-4 text-left hover:bg-accent/30 transition-colors"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="font-medium text-sm truncate">{ticket.subject}</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    {ticket.contactName}
                                    {ticket.isGuest && <span className="ml-1 text-[10px] bg-muted px-1.5 py-0.5 rounded">invité</span>}
                                    {' · '}{ticket.messageCount} message{ticket.messageCount !== 1 ? 's' : ''}
                                    {' · '}mis à jour le {ticket.updatedAt}
                                </p>
                            </div>
                            <StatusBadge status={ticket.status} />
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
