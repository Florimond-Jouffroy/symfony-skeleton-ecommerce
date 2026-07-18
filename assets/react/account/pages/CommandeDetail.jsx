import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Package } from 'lucide-react';
import { api, ApiError, getErrorMessage } from '../../utils/api';

const STATUS_LABELS = {
    pending:   { label: 'En attente',  class: 'bg-amber-100 text-amber-700' },
    confirmed: { label: 'Confirmée',   class: 'bg-blue-100 text-blue-700' },
    shipped:   { label: 'Expédiée',    class: 'bg-indigo-100 text-indigo-700' },
    delivered: { label: 'Livrée',      class: 'bg-green-100 text-green-700' },
    cancelled: { label: 'Annulée',     class: 'bg-red-100 text-red-700' },
    refunded:  { label: 'Remboursée',  class: 'bg-gray-100 text-gray-600' },
};

const RETURN_STATUS = {
    requested: { label: 'Demandé',    class: 'bg-amber-100 text-amber-700' },
    approved:  { label: 'Approuvé',   class: 'bg-blue-100 text-blue-700' },
    rejected:  { label: 'Refusé',     class: 'bg-red-100 text-red-700' },
    refunded:  { label: 'Remboursé',  class: 'bg-green-100 text-green-700' },
};

function formatPrice(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

export default function CommandeDetail({ urls, returnsEnabled = false }) {
    const { number } = useParams();
    const navigate   = useNavigate();
    const [order, setOrder]       = useState(null);
    const [loading, setLoading]   = useState(true);
    const [notFound, setNotFound] = useState(false);

    const [returns, setReturns]           = useState([]);
    const [returnMode, setReturnMode]     = useState(false);
    const [returnQty, setReturnQty]       = useState({});
    const [returnReason, setReturnReason] = useState('');
    const [submitting, setSubmitting]     = useState(false);
    const [feedback, setFeedback]         = useState(null);

    useEffect(() => {
        setLoading(true);
        api.get(`${urls.orders}/${number}`)
            .then(setOrder)
            .catch((err) => { if (err instanceof ApiError && err.status === 404) setNotFound(true); })
            .finally(() => setLoading(false));
    }, [number]);

    useEffect(() => {
        if (!returnsEnabled || !urls.returns) return;
        api.get(urls.returns)
            .then((data) => setReturns((data?.items ?? []).filter((r) => r.orderNumber === number)))
            .catch(() => {});
    }, [number, returnsEnabled]);

    const submitReturn = async () => {
        const items = order.items
            .map((it) => ({ orderItemId: it.id, quantity: parseInt(returnQty[it.id] || 0, 10) }))
            .filter((line) => line.quantity > 0);

        if (items.length === 0) { setFeedback({ type: 'error', message: 'Sélectionnez au moins un article à retourner.' }); return; }
        if (!returnReason.trim()) { setFeedback({ type: 'error', message: 'Indiquez le motif du retour.' }); return; }

        setSubmitting(true);
        setFeedback(null);
        try {
            await api.post(urls.returns, { orderNumber: order.orderNumber, reason: returnReason.trim(), items });
            setReturnMode(false);
            setReturnQty({});
            setReturnReason('');
            setFeedback({ type: 'success', message: 'Demande de retour envoyée.' });
            // Recharge la commande (quantités retournables) et la liste des retours.
            api.get(`${urls.orders}/${number}`).then(setOrder).catch(() => {});
            api.get(urls.returns).then((data) => setReturns((data?.items ?? []).filter((r) => r.orderNumber === number))).catch(() => {});
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Erreur lors de la demande de retour.') });
        } finally {
            setSubmitting(false);
        }
    };

    if (loading) return <Skeleton />;

    if (notFound || !order) {
        return (
            <div className="py-20 text-center">
                <p className="text-muted-foreground">Commande introuvable.</p>
                <button onClick={() => navigate('/commandes')} className="mt-3 text-sm underline">
                    Retour aux commandes
                </button>
            </div>
        );
    }

    const status  = STATUS_LABELS[order.status] ?? { label: order.status, class: 'bg-muted text-muted-foreground' };
    const address = order.shippingAddress ?? {};
    const canRequestReturn = returnsEnabled
        && order.status === 'delivered'
        && (order.items ?? []).some((i) => (i.returnableQuantity ?? 0) > 0);

    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <button
                    onClick={() => navigate('/commandes')}
                    className="mb-4 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Retour aux commandes
                </button>
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-bold tracking-tight">Commande #{order.orderNumber}</h1>
                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${status.class}`}>
                        {status.label}
                    </span>
                </div>
                <p className="mt-1 text-sm text-muted-foreground">Passée le {formatDate(order.createdAt)}</p>
            </div>

            {/* Items */}
            <div className="rounded-xl border border-border bg-card overflow-hidden">
                <div className="flex items-center justify-between px-5 py-3 border-b border-border bg-muted/30">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Articles</p>
                    {canRequestReturn && !returnMode && (
                        <button
                            type="button"
                            onClick={() => { setReturnMode(true); setFeedback(null); }}
                            className="text-sm font-medium text-primary hover:underline"
                        >
                            Demander un retour
                        </button>
                    )}
                    {returnMode && (
                        <span className="text-xs text-muted-foreground">Sélectionnez les articles à retourner</span>
                    )}
                </div>
                <ul className="divide-y divide-border">
                    {order.items.map((item) => (
                        <li key={item.id} className="flex items-center gap-4 px-5 py-4">
                            <div className="size-14 shrink-0 overflow-hidden rounded-md border border-border bg-muted">
                                {item.imageUrl ? (
                                    <img src={item.imageUrl} alt="" className="size-full object-cover" />
                                ) : (
                                    <div className="flex size-full items-center justify-center text-muted-foreground">
                                        <Package className="size-5" />
                                    </div>
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-foreground">{item.productName}</p>
                                {item.variantName && <p className="text-xs text-muted-foreground">{item.variantName}</p>}
                                <p className="text-xs text-muted-foreground">{item.quantity} × {formatPrice(item.unitPrice)}</p>
                            </div>
                            <div className="shrink-0 text-right">
                                {returnMode ? (
                                    (item.returnableQuantity ?? 0) > 0 ? (
                                        <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                            Retour
                                            <input
                                                type="number"
                                                min="0"
                                                max={item.returnableQuantity}
                                                value={returnQty[item.id] ?? 0}
                                                onChange={(e) => {
                                                    const v = Math.max(0, Math.min(item.returnableQuantity, parseInt(e.target.value || 0, 10)));
                                                    setReturnQty((q) => ({ ...q, [item.id]: v }));
                                                }}
                                                className="w-16 rounded border border-border px-2 py-1 text-sm text-foreground"
                                            />
                                        </label>
                                    ) : (
                                        <span className="text-xs text-muted-foreground">Déjà retourné</span>
                                    )
                                ) : (
                                    <p className="text-sm font-medium">{formatPrice(item.total)}</p>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
                {returnMode && (
                    <div className="space-y-3 border-t border-border px-5 py-4">
                        <textarea
                            value={returnReason}
                            onChange={(e) => setReturnReason(e.target.value)}
                            rows={3}
                            placeholder="Motif du retour (obligatoire)"
                            className="w-full rounded border border-border px-3 py-2 text-sm"
                        />
                        <div className="flex gap-2">
                            <button
                                type="button"
                                disabled={submitting}
                                onClick={submitReturn}
                                className="rounded bg-primary px-3 py-1.5 text-sm text-primary-foreground disabled:opacity-50"
                            >
                                {submitting ? 'Envoi…' : 'Envoyer la demande'}
                            </button>
                            <button
                                type="button"
                                onClick={() => { setReturnMode(false); setReturnQty({}); }}
                                className="rounded border border-border px-3 py-1.5 text-sm"
                            >
                                Annuler
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            {/* Existing returns */}
            {returns.length > 0 && (
                <div className="rounded-xl border border-border bg-card p-5 space-y-3">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Vos demandes de retour</p>
                    <ul className="space-y-2">
                        {returns.map((r) => {
                            const s = RETURN_STATUS[r.status] ?? { label: r.status, class: 'bg-muted text-muted-foreground' };
                            return (
                                <li key={r.id} className="flex items-center justify-between gap-3 text-sm">
                                    <span className="min-w-0 truncate text-muted-foreground">
                                        {r.items.map((i) => `${i.quantity}× ${i.productName}`).join(', ')}
                                    </span>
                                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${s.class}`}>{s.label}</span>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}

            <div className="grid gap-6 sm:grid-cols-2">
                {/* Address */}
                {Object.keys(address).length > 0 && (
                    <div className="rounded-xl border border-border bg-card p-5 space-y-2">
                        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Adresse de livraison</p>
                        <address className="not-italic text-sm text-foreground leading-relaxed">
                            <p className="font-medium">{address.firstName} {address.lastName}</p>
                            <p>{address.line1}</p>
                            {address.line2 && <p>{address.line2}</p>}
                            <p>{address.postalCode} {address.city}</p>
                            {address.country && <p>{address.country}</p>}
                        </address>
                    </div>
                )}

                {/* Totals */}
                <div className="rounded-xl border border-border bg-card p-5 space-y-3">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Récapitulatif</p>
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Sous-total</dt>
                            <dd>{formatPrice(order.subtotal)}</dd>
                        </div>
                        {order.discountAmount > 0 && (
                            <div className="flex justify-between text-green-600">
                                <dt>Remise</dt>
                                <dd>−{formatPrice(order.discountAmount)}</dd>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Livraison</dt>
                            <dd>{order.shippingAmount > 0 ? formatPrice(order.shippingAmount) : 'Offerte'}</dd>
                        </div>
                        <div className="flex justify-between border-t border-border pt-2 font-semibold">
                            <dt>Total</dt>
                            <dd>{formatPrice(order.total)}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {/* Customer note */}
            {order.customerNote && (
                <div className="rounded-xl border border-border bg-card p-5 space-y-2">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Votre note</p>
                    <p className="text-sm text-foreground">{order.customerNote}</p>
                </div>
            )}
        </div>
    );
}

function Skeleton() {
    return (
        <div className="space-y-6 animate-pulse">
            <div className="space-y-2">
                <div className="h-7 w-56 rounded bg-muted" />
                <div className="h-4 w-32 rounded bg-muted" />
            </div>
            <div className="h-48 rounded-xl bg-muted" />
            <div className="grid gap-6 sm:grid-cols-2">
                <div className="h-36 rounded-xl bg-muted" />
                <div className="h-36 rounded-xl bg-muted" />
            </div>
        </div>
    );
}
