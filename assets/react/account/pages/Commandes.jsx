import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowRight, Package } from 'lucide-react';
import { api } from '../../utils/api';

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

const PAGE_SIZE = 10;

export default function Commandes({ urls }) {
    const navigate = useNavigate();
    const [orders, setOrders]   = useState([]);
    const [total, setTotal]     = useState(0);
    const [page, setPage]       = useState(1);
    const [loading, setLoading] = useState(true);

    const totalPages = Math.ceil(total / PAGE_SIZE);

    useEffect(() => {
        setLoading(true);
        api.get(urls.orders, { page, pageSize: PAGE_SIZE })
            .then((data) => {
                setOrders(data?.items ?? []);
                setTotal(data?.total ?? 0);
            })
            .finally(() => setLoading(false));
    }, [page]);

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Mes commandes</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {loading ? ' ' : `${total} commande${total !== 1 ? 's' : ''}`}
                </p>
            </div>

            {loading ? (
                <div className="space-y-3 animate-pulse">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className="h-20 rounded-xl bg-muted" />
                    ))}
                </div>
            ) : orders.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border py-24 text-center">
                    <Package className="mb-3 h-10 w-10 text-muted-foreground/30" />
                    <p className="text-base font-medium text-muted-foreground">Aucune commande</p>
                    <p className="mt-1 text-sm text-muted-foreground">Vous n'avez pas encore passé de commande.</p>
                    <button
                        onClick={() => { window.location.href = '/boutique'; }}
                        className="mt-4 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                    >
                        Découvrir la boutique
                    </button>
                </div>
            ) : (
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <ul className="divide-y divide-border">
                        {orders.map((order) => {
                            const status = STATUS_LABELS[order.status] ?? { label: order.status, class: 'bg-muted text-muted-foreground' };
                            return (
                                <li
                                    key={order.id}
                                    onClick={() => navigate(`/commandes/${order.orderNumber}`)}
                                    className="flex flex-col gap-3 px-5 py-4 hover:bg-accent/50 cursor-pointer transition-colors sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="space-y-0.5">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-semibold">#{order.orderNumber}</p>
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${status.class}`}>
                                                {status.label}
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDate(order.createdAt)} · {order.itemCount} article{order.itemCount !== 1 ? 's' : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center justify-between gap-4 sm:justify-end">
                                        <span className="text-sm font-semibold">{formatPrice(order.total)}</span>
                                        <ArrowRight className="h-4 w-4 text-muted-foreground shrink-0" />
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}

            {totalPages > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button
                        disabled={page <= 1}
                        onClick={() => setPage(p => p - 1)}
                        className="rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40 transition-colors"
                    >
                        ← Précédent
                    </button>
                    <span className="px-3 text-sm text-muted-foreground">{page} / {totalPages}</span>
                    <button
                        disabled={page >= totalPages}
                        onClick={() => setPage(p => p + 1)}
                        className="rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40 transition-colors"
                    >
                        Suivant →
                    </button>
                </div>
            )}
        </div>
    );
}
