import React, { useEffect, useState } from 'react';
import { Check, RotateCcw, X } from 'lucide-react';
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
    const returnsUrl = urls.returns;
    const canEdit    = permissions.canEditReturns !== false;
    const [returns, setReturns] = useState([]);
    const [loading, setLoading] = useState(true);
    const [tab, setTab]         = useState('requested');
    const [error, setError]     = useState(null);

    useEffect(() => {
        setLoading(true);
        api.get(returnsUrl)
            .then((data) => setReturns(data?.items ?? []))
            .catch(() => setError('Impossible de charger les retours.'))
            .finally(() => setLoading(false));
    }, []);

    const act = async (item, status) => {
        try {
            const updated = await api.patch(`${returnsUrl}/${item.id}/statut`, { status });
            setReturns((rs) => rs.map((r) => (r.id === updated.id ? updated : r)));
        } catch (err) {
            setError(getErrorMessage(err, 'Erreur lors de la mise à jour.'));
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
                                            <span className="font-medium">Commande #{r.orderNumber}</span>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${s.class}`}>{s.label}</span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">{r.customerName} · {r.customerEmail}</p>
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
                                                        onClick={() => act(r, 'rejected')}
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

                                <ul className="space-y-1 border-t border-border pt-3 text-sm">
                                    {r.items.map((it, idx) => (
                                        <li key={idx} className="flex items-center justify-between gap-3">
                                            <span className="min-w-0 truncate">
                                                {it.quantity}× {it.productName}{it.variantName ? ` — ${it.variantName}` : ''}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground">{formatPrice(it.unitPrice)}</span>
                                        </li>
                                    ))}
                                </ul>

                                <p className="text-sm"><span className="text-muted-foreground">Motif :</span> {r.reason}</p>
                                {r.adminNote && <p className="text-sm"><span className="text-muted-foreground">Note :</span> {r.adminNote}</p>}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
