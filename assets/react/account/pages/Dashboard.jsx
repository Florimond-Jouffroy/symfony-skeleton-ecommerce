import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Package, User, ArrowRight } from 'lucide-react';
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

export default function Dashboard({ urls, userEmail }) {
    const navigate = useNavigate();
    const [profile, setProfile]   = useState(null);
    const [orders, setOrders]     = useState([]);
    const [loading, setLoading]   = useState(true);

    useEffect(() => {
        Promise.all([
            api.get(urls.profile),
            api.get(urls.orders, { pageSize: 3 }),
        ]).then(([profileData, ordersData]) => {
            setProfile(profileData);
            setOrders(ordersData?.items ?? []);
        }).finally(() => setLoading(false));
    }, []);

    const displayName = profile?.firstName
        ? `${profile.firstName} ${profile.lastName}`.trim()
        : userEmail;

    if (loading) return <DashboardSkeleton />;

    return (
        <div className="space-y-8">
            {/* Header */}
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Bonjour, {displayName} 👋</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Bienvenue dans votre espace personnel.
                </p>
            </div>

            {/* Profile incomplete banner */}
            {!profile?.hasProfile && (
                <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    <svg className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div>
                        <p className="font-medium text-amber-800">Profil incomplet</p>
                        <p className="text-amber-700 mt-0.5">
                            Complétez vos informations pour faciliter vos prochaines commandes.{' '}
                            <button
                                onClick={() => navigate('/profil')}
                                className="font-medium underline underline-offset-2 hover:no-underline"
                            >
                                Compléter mon profil
                            </button>
                        </p>
                    </div>
                </div>
            )}

            {/* Quick access cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <QuickCard
                    icon={Package}
                    title="Mes commandes"
                    description={orders.length > 0 ? `${orders.length} commande${orders.length > 1 ? 's' : ''} récente${orders.length > 1 ? 's' : ''}` : 'Aucune commande'}
                    onClick={() => navigate('/commandes')}
                />
                <QuickCard
                    icon={User}
                    title="Mon profil"
                    description={profile?.hasProfile ? `${profile.firstName} ${profile.lastName}` : 'À compléter'}
                    onClick={() => navigate('/profil')}
                />
            </div>

            {/* Recent orders */}
            {orders.length > 0 && (
                <div className="rounded-xl border border-border bg-card">
                    <div className="flex items-center justify-between px-5 py-4 border-b border-border">
                        <h2 className="text-sm font-semibold">Dernières commandes</h2>
                        <button
                            onClick={() => navigate('/commandes')}
                            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                        >
                            Tout voir <ArrowRight className="h-3 w-3" />
                        </button>
                    </div>
                    <ul className="divide-y divide-border">
                        {orders.map((order) => {
                            const status = STATUS_LABELS[order.status] ?? { label: order.status, class: 'bg-muted text-muted-foreground' };
                            return (
                                <li
                                    key={order.id}
                                    onClick={() => navigate(`/commandes/${order.orderNumber}`)}
                                    className="flex items-center justify-between gap-4 px-5 py-4 hover:bg-accent/50 cursor-pointer transition-colors"
                                >
                                    <div>
                                        <p className="text-sm font-medium">#{order.orderNumber}</p>
                                        <p className="text-xs text-muted-foreground">{formatDate(order.createdAt)}</p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${status.class}`}>
                                            {status.label}
                                        </span>
                                        <span className="text-sm font-semibold">{formatPrice(order.total)}</span>
                                        <ArrowRight className="h-4 w-4 text-muted-foreground" />
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </div>
    );
}

function QuickCard({ icon: Icon, title, description, onClick }) {
    return (
        <button
            onClick={onClick}
            className="group flex items-center gap-4 rounded-xl border border-border bg-card p-5 text-left hover:shadow-md transition-all duration-200 w-full"
        >
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                <Icon className="h-5 w-5" />
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-foreground">{title}</p>
                <p className="text-xs text-muted-foreground truncate">{description}</p>
            </div>
            <ArrowRight className="h-4 w-4 text-muted-foreground group-hover:text-foreground group-hover:translate-x-0.5 transition-all shrink-0" />
        </button>
    );
}

function DashboardSkeleton() {
    return (
        <div className="space-y-8 animate-pulse">
            <div className="space-y-2">
                <div className="h-7 w-48 rounded bg-muted" />
                <div className="h-4 w-64 rounded bg-muted" />
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="h-20 rounded-xl bg-muted" />
                <div className="h-20 rounded-xl bg-muted" />
            </div>
            <div className="h-48 rounded-xl bg-muted" />
        </div>
    );
}
