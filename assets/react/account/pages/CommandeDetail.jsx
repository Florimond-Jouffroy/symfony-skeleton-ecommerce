import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../../utils/api';

const STATUS_LABELS = {
    pending:   { label: 'En attente',  class: 'bg-amber-100 text-amber-700' },
    confirmed: { label: 'Confirmée',   class: 'bg-blue-100 text-blue-700' },
    shipped:   { label: 'Expédiée',    class: 'bg-indigo-100 text-indigo-700' },
    delivered: { label: 'Livrée',      class: 'bg-green-100 text-green-700' },
    cancelled: { label: 'Annulée',     class: 'bg-red-100 text-red-700' },
    refunded:  { label: 'Remboursée',  class: 'bg-gray-100 text-gray-600' },
};

function formatPrice(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

export default function CommandeDetail({ urls }) {
    const { number } = useParams();
    const navigate   = useNavigate();
    const [order, setOrder]     = useState(null);
    const [loading, setLoading] = useState(true);
    const [notFound, setNotFound] = useState(false);

    useEffect(() => {
        setLoading(true);
        api.get(`${urls.orders}/${number}`)
            .then(setOrder)
            .catch((err) => { if (err instanceof ApiError && err.status === 404) setNotFound(true); })
            .finally(() => setLoading(false));
    }, [number]);

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
                <div className="px-5 py-3 border-b border-border bg-muted/30">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Articles</p>
                </div>
                <ul className="divide-y divide-border">
                    {order.items.map((item) => (
                        <li key={item.id} className="flex items-center justify-between gap-4 px-5 py-4">
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-foreground">{item.productName}</p>
                                {item.variantName && (
                                    <p className="text-xs text-muted-foreground">{item.variantName}</p>
                                )}
                            </div>
                            <div className="shrink-0 text-right">
                                <p className="text-sm font-medium">{formatPrice(item.total)}</p>
                                <p className="text-xs text-muted-foreground">
                                    {item.quantity} × {formatPrice(item.unitPrice)}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>

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
