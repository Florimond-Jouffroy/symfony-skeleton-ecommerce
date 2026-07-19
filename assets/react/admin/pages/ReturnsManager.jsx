import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Check, ChevronDown, ChevronUp, ExternalLink, ImageOff, MessageSquare, RotateCcw, Send, User, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { api, getErrorMessage } from '../../utils/api';

const STATUS = {
    requested: { label: 'Demandé',   class: 'bg-amber-100 text-amber-700' },
    approved:  { label: 'Approuvé',  class: 'bg-blue-100 text-blue-700' },
    rejected:  { label: 'Refusé',    class: 'bg-red-100 text-red-700' },
    refunded:  { label: 'Remboursé', class: 'bg-green-100 text-green-700' },
};

const TABS = [
    { key: 'all',       label: 'Tous' },
    { key: 'requested', label: 'Demandés' },
    { key: 'approved',  label: 'Approuvés' },
    { key: 'closed',    label: 'Clôturés' },
];

function formatPrice(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

export default function ReturnsManager({ urls, permissions = {} }) {
    const navigate   = useNavigate();
    const returnsUrl = urls.returns;
    const canEdit    = permissions.canEditReturns !== false;
    const [returns, setReturns] = useState([]);
    const [loading, setLoading] = useState(true);
    const [tab, setTab]         = useState('requested');
    const [error, setError]     = useState(null);
    const [expanded, setExpanded]         = useState({}); // id => fil déplié
    const [drafts, setDrafts]             = useState({}); // id => message en cours
    const [sending, setSending]           = useState(null);
    const [rejectTarget, setRejectTarget] = useState(null);
    const [rejectNote, setRejectNote]     = useState('');
    const [rejecting, setRejecting]       = useState(false);

    useEffect(() => {
        setLoading(true);
        api.get(returnsUrl)
            .then((data) => setReturns(data?.items ?? []))
            .catch(() => setError('Impossible de charger les retours.'))
            .finally(() => setLoading(false));
    }, []);

    const replace = (updated) => setReturns((rs) => rs.map((r) => (r.id === updated.id ? updated : r)));

    const act = async (item, status, adminNote) => {
        try {
            replace(await api.patch(`${returnsUrl}/${item.id}/statut`, { status, adminNote }));
        } catch (err) {
            setError(getErrorMessage(err, 'Erreur lors de la mise à jour.'));
        }
    };

    // Le refus doit être motivé : le motif est envoyé au client dans le fil.
    const confirmReject = async () => {
        const body = rejectNote.trim();
        if (!body) return;

        setRejecting(true);
        try {
            replace(await api.patch(`${returnsUrl}/${rejectTarget.id}/statut`, { status: 'rejected', adminNote: body }));
            setRejectTarget(null);
            setRejectNote('');
        } catch (err) {
            setError(getErrorMessage(err, 'Erreur lors du refus.'));
        } finally {
            setRejecting(false);
        }
    };

    const sendMessage = async (item) => {
        const body = (drafts[item.id] ?? '').trim();
        if (!body) return;

        setSending(item.id);
        try {
            replace(await api.post(`${returnsUrl}/${item.id}/message`, { body }));
            setDrafts((d) => ({ ...d, [item.id]: '' }));
        } catch (err) {
            setError(getErrorMessage(err, "Impossible d'envoyer le message."));
        } finally {
            setSending(null);
        }
    };

    const displayed = returns.filter((r) => {
        if (tab === 'all') return true;
        if (tab === 'closed') return r.status === 'rejected' || r.status === 'refunded';
        return r.status === tab;
    });

    return (
        <div className="space-y-5">
            {error && (
                <div className="flex items-center justify-between rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                    <button type="button" onClick={() => setError(null)}><X className="size-4" /></button>
                </div>
            )}

            <div className="flex gap-1 rounded-lg border bg-muted/30 p-1 w-fit">
                {TABS.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => setTab(t.key)}
                        className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                            tab === t.key ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            ) : displayed.length === 0 ? (
                <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                    Aucun retour dans cette catégorie.
                </div>
            ) : (
                <div className="space-y-3">
                    {displayed.map((r) => {
                        const s = STATUS[r.status] ?? { label: r.status, class: 'bg-muted text-muted-foreground' };
                        return (
                            <div key={r.id} className="rounded-lg border bg-card px-5 py-4 space-y-3">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => navigate(`/commandes/${r.orderId}`)}
                                                className="flex items-center gap-1 font-medium hover:underline"
                                            >
                                                Commande #{r.orderNumber}
                                                <ExternalLink className="size-3.5 text-muted-foreground" />
                                            </button>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${s.class}`}>{s.label}</span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {r.customerUserId ? (
                                                <button
                                                    type="button"
                                                    onClick={() => navigate(`/utilisateurs/${r.customerUserId}`)}
                                                    className="inline-flex items-center gap-1 text-foreground hover:underline"
                                                >
                                                    <User className="size-3.5" />
                                                    {r.customerName}
                                                </button>
                                            ) : (
                                                <span className="text-foreground">{r.customerName}</span>
                                            )}
                                            {' · '}
                                            <a href={`mailto:${r.customerEmail}`} className="hover:underline">{r.customerEmail}</a>
                                        </p>
                                        <p className="text-xs text-muted-foreground">{new Date(r.createdAt).toLocaleDateString('fr-FR')}</p>
                                    </div>

                                    {canEdit && (
                                        <div className="flex shrink-0 flex-wrap gap-2">
                                            {r.status === 'requested' && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() => act(r, 'approved')}
                                                        className="flex items-center gap-1 rounded border border-blue-200 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-50"
                                                    >
                                                        <Check className="size-3.5" /> Approuver
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => { setRejectTarget(r); setRejectNote(''); }}
                                                        className="flex items-center gap-1 rounded border border-red-200 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                                                    >
                                                        <X className="size-3.5" /> Refuser
                                                    </button>
                                                </>
                                            )}
                                            {r.status === 'approved' && (
                                                <button
                                                    type="button"
                                                    onClick={() => act(r, 'refunded')}
                                                    className="flex items-center gap-1 rounded border border-green-200 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-50"
                                                >
                                                    <RotateCcw className="size-3.5" /> Rembourser + restock
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <ul className="space-y-2 border-t border-border pt-3 text-sm">
                                    {r.items.map((it, idx) => (
                                        <li key={idx} className="flex items-center gap-3">
                                            <div className="size-12 shrink-0 overflow-hidden rounded border bg-muted">
                                                {it.imageUrl ? (
                                                    <img
                                                        src={it.imageUrl}
                                                        alt={it.productName}
                                                        className="size-full object-cover"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="flex size-full items-center justify-center">
                                                        <ImageOff className="size-4 text-muted-foreground" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium">{it.productName}</p>
                                                {it.variantName && (
                                                    <p className="truncate text-xs text-muted-foreground">{it.variantName}</p>
                                                )}
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <p className="text-muted-foreground">{formatPrice(it.unitPrice)}</p>
                                                <p className="text-xs text-muted-foreground">× {it.quantity}</p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>

                                {/* Le motif est aussi le premier message du fil ; on ne le répète
                                    que si la demande n'a pas de conversation (retours antérieurs). */}
                                {!r.support ? (
                                    <p className="text-sm"><span className="text-muted-foreground">Motif :</span> {r.reason}</p>
                                ) : (
                                    <div className="border-t border-border pt-3">
                                        <button
                                            type="button"
                                            onClick={() => setExpanded((e) => ({ ...e, [r.id]: !e[r.id] }))}
                                            className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
                                        >
                                            <MessageSquare className="size-4" />
                                            Conversation ({r.support.messages.length})
                                            {expanded[r.id] ? <ChevronUp className="size-4" /> : <ChevronDown className="size-4" />}
                                        </button>

                                        {expanded[r.id] && (
                                            <div className="mt-3 space-y-3">
                                                <div className="space-y-2">
                                                    {r.support.messages.map((m) => (
                                                        <div
                                                            key={m.id}
                                                            className={`rounded-lg px-3 py-2 text-sm ${
                                                                m.isFromAdmin ? 'bg-primary/10 ml-8' : 'bg-muted mr-8'
                                                            }`}
                                                        >
                                                            <div className="flex items-baseline justify-between gap-2">
                                                                <span className="text-xs font-medium">{m.authorName}</span>
                                                                <span className="text-xs text-muted-foreground">
                                                                    {new Date(m.createdAt).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}
                                                                </span>
                                                            </div>
                                                            <p className="mt-1 whitespace-pre-wrap">{m.body}</p>
                                                        </div>
                                                    ))}
                                                </div>

                                                {canEdit && (
                                                    <div className="flex items-end gap-2">
                                                        <textarea
                                                            value={drafts[r.id] ?? ''}
                                                            onChange={(e) => setDrafts((d) => ({ ...d, [r.id]: e.target.value }))}
                                                            rows={2}
                                                            placeholder="Répondre au client…"
                                                            className="flex-1 resize-none rounded-md border bg-background px-3 py-2 text-sm"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => sendMessage(r)}
                                                            disabled={sending === r.id || !(drafts[r.id] ?? '').trim()}
                                                            className="flex items-center gap-1 rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50"
                                                        >
                                                            <Send className="size-4" />
                                                            {sending === r.id ? 'Envoi…' : 'Envoyer'}
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            <Dialog open={rejectTarget !== null} onOpenChange={(open) => !open && setRejectTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Refuser la demande de retour</DialogTitle>
                        <DialogDescription>
                            Expliquez au client pourquoi sa demande est refusée. Ce message lui sera
                            envoyé et apparaîtra dans la conversation.
                        </DialogDescription>
                    </DialogHeader>
                    <textarea
                        value={rejectNote}
                        onChange={(e) => setRejectNote(e.target.value)}
                        rows={4}
                        autoFocus
                        placeholder="Ex : le délai de retour de 30 jours est dépassé."
                        className="w-full resize-none rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRejectTarget(null)} disabled={rejecting}>
                            Annuler
                        </Button>
                        <Button variant="destructive" onClick={confirmReject} disabled={rejecting || !rejectNote.trim()}>
                            {rejecting ? 'En cours…' : 'Refuser le retour'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
