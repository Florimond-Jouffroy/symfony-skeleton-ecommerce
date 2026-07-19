import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, ChevronRight, Mail, Package, Phone, RotateCcw, ShoppingBag, User } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { api } from '../../utils/api';

const ORDER_STATUS = {
    pending:   { label: 'En attente', variant: 'secondary' },
    confirmed: { label: 'Confirmée',  variant: 'default' },
    shipped:   { label: 'Expédiée',   variant: 'default' },
    delivered: { label: 'Livrée',     variant: 'default' },
    cancelled: { label: 'Annulée',    variant: 'destructive' },
    refunded:  { label: 'Remboursée', variant: 'outline' },
};

const RETURN_STATUS = {
    requested: { label: 'Demandé',   class: 'bg-amber-100 text-amber-700' },
    approved:  { label: 'Approuvé',  class: 'bg-blue-100 text-blue-700' },
    rejected:  { label: 'Refusé',    class: 'bg-red-100 text-red-700' },
    refunded:  { label: 'Remboursé', class: 'bg-green-100 text-green-700' },
};

function formatPrice(cents) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(iso));
}

function StatCard({ icon: Icon, label, value }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2 text-muted-foreground">
                <Icon className="size-4" />
                <span className="text-xs font-medium uppercase tracking-wide">{label}</span>
            </div>
            <p className="mt-2 text-2xl font-bold">{value}</p>
        </div>
    );
}

export default function UserDetail() {
    const { id }   = useParams();
    const navigate = useNavigate();

    const [data, setData]       = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError]     = useState(null);

    useEffect(() => {
        setLoading(true);
        api.get(`/api/admin/utilisateurs/${id}`)
            .then(setData)
            .catch(() => setError("Impossible de charger la fiche de cet utilisateur."))
            .finally(() => setLoading(false));
    }, [id]);

    if (loading) {
        return (
            <div className="flex justify-center py-16">
                <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
        );
    }

    if (error || !data) {
        return (
            <div className="space-y-4">
                <Button variant="ghost" size="sm" onClick={() => navigate('/utilisateurs')}>
                    <ArrowLeft className="size-4" /> Retour
                </Button>
                <p className="text-sm text-destructive">{error ?? 'Utilisateur introuvable.'}</p>
            </div>
        );
    }

    const { user, customer, stats, orders, returns } = data;
    const isAdmin = user.roles.includes('ROLE_ADMIN');

    return (
        <div className="space-y-6">
            <Button variant="ghost" size="sm" className="-ml-2" onClick={() => navigate('/utilisateurs')}>
                <ArrowLeft className="size-4" /> Utilisateurs
            </Button>

            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2 className="text-2xl font-bold tracking-tight">
                        {customer?.fullName || user.email}
                    </h2>
                    <a
                        href={`mailto:${user.email}`}
                        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground hover:underline"
                    >
                        <Mail className="size-3.5" /> {user.email}
                    </a>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant={isAdmin ? 'default' : 'secondary'}>{isAdmin ? 'Admin' : 'Utilisateur'}</Badge>
                    <Badge
                        variant="outline"
                        className={user.isVerified ? 'text-green-600 border-green-600/40' : 'text-amber-600 border-amber-600/40'}
                    >
                        {user.isVerified ? 'Vérifié' : 'En attente'}
                    </Badge>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard icon={ShoppingBag} label="Commandes" value={stats.orderCount} />
                <StatCard icon={Package} label="Total dépensé" value={formatPrice(stats.totalSpent)} />
                <StatCard icon={RotateCcw} label="Retours" value={stats.returnCount} />
            </div>

            {/* Profil client — vide tant qu'aucune commande n'a été passée avec cet email */}
            <div className="rounded-lg border bg-card p-5 space-y-3">
                <div className="flex items-center gap-2">
                    <User className="size-4 text-muted-foreground" />
                    <h3 className="font-medium">Profil client</h3>
                </div>
                {customer ? (
                    <div className="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Nom</p>
                            <p>{customer.fullName || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Téléphone</p>
                            <p className="flex items-center gap-1.5">
                                {customer.phone ? (
                                    <>
                                        <Phone className="size-3.5 text-muted-foreground" />
                                        <a href={`tel:${customer.phone}`} className="hover:underline">{customer.phone}</a>
                                    </>
                                ) : '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Client depuis</p>
                            <p>{formatDate(customer.createdAt)}</p>
                        </div>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Cet utilisateur n'a pas encore passé de commande — aucune donnée client à afficher.
                    </p>
                )}
            </div>

            <div className="rounded-lg border bg-card p-5 space-y-3">
                <div className="flex items-center gap-2">
                    <ShoppingBag className="size-4 text-muted-foreground" />
                    <h3 className="font-medium">Commandes</h3>
                </div>
                {orders.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Aucune commande.</p>
                ) : (
                    <ul className="divide-y">
                        {orders.map((o) => {
                            const s = ORDER_STATUS[o.status] ?? { label: o.status, variant: 'secondary' };
                            return (
                                <li key={o.id}>
                                    <button
                                        type="button"
                                        onClick={() => navigate(`/commandes/${o.id}`)}
                                        className="flex w-full items-center justify-between gap-3 py-3 text-left hover:bg-muted/50"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-sm">#{o.orderNumber}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDate(o.createdAt)} · {o.itemCount} article{o.itemCount > 1 ? 's' : ''}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3">
                                            <Badge variant={s.variant}>{s.label}</Badge>
                                            <span className="text-sm font-medium">{formatPrice(o.total)}</span>
                                            <ChevronRight className="size-4 text-muted-foreground" />
                                        </div>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <div className="rounded-lg border bg-card p-5 space-y-3">
                <div className="flex items-center gap-2">
                    <RotateCcw className="size-4 text-muted-foreground" />
                    <h3 className="font-medium">Retours</h3>
                </div>
                {returns.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Aucune demande de retour.</p>
                ) : (
                    <ul className="divide-y">
                        {returns.map((r) => {
                            const s = RETURN_STATUS[r.status] ?? { label: r.status, class: 'bg-muted text-muted-foreground' };
                            return (
                                <li key={r.id} className="flex items-center justify-between gap-3 py-3">
                                    <div className="min-w-0">
                                        <button
                                            type="button"
                                            onClick={() => navigate(`/commandes/${r.orderId}`)}
                                            className="truncate text-sm font-medium hover:underline"
                                        >
                                            Commande #{r.orderNumber}
                                        </button>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDate(r.createdAt)} · {r.itemCount} article{r.itemCount > 1 ? 's' : ''}
                                        </p>
                                    </div>
                                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${s.class}`}>
                                        {s.label}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </div>
    );
}
