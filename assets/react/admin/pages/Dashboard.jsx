import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
} from 'recharts';
import { ShoppingCart, Euro, Users, Clock } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { api } from '../../utils/api';

const STATUS_LABELS = {
    pending:   { label: 'En attente',  color: 'bg-yellow-100 text-yellow-800' },
    confirmed: { label: 'Confirmée',   color: 'bg-blue-100 text-blue-800' },
    shipped:   { label: 'Expédiée',    color: 'bg-purple-100 text-purple-800' },
    delivered: { label: 'Livrée',      color: 'bg-green-100 text-green-800' },
    cancelled: { label: 'Annulée',     color: 'bg-red-100 text-red-800' },
    refunded:  { label: 'Remboursée',  color: 'bg-gray-100 text-gray-700' },
};

function euros(cents) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function formatMonth(label) {
    const [year, month] = label.split('-');
    return new Date(year, month - 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
}

function TrendBadge({ current, previous }) {
    if (previous === 0) return null;
    const pct = Math.round(((current - previous) / previous) * 100);
    const up  = pct >= 0;
    return (
        <span className={`text-xs font-medium ${up ? 'text-green-600' : 'text-red-500'}`}>
            {up ? '▲' : '▼'} {Math.abs(pct)} %
        </span>
    );
}

const CustomTooltip = ({ active, payload, label }) => {
    if (!active || !payload?.length) return null;
    return (
        <div className="rounded-md border bg-background px-3 py-2 shadow-sm text-sm">
            <p className="font-medium mb-1">{label}</p>
            <p className="text-muted-foreground">{euros(payload[0].value)}</p>
        </div>
    );
};

export default function Dashboard({ urls = {}, permissions = {} }) {
    const [data, setData]       = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError]     = useState(null);

    useEffect(() => {
        api.get(urls.statistics ?? '/api/admin/statistiques')
            .then(setData)
            .catch(() => setError('Impossible de charger les statistiques.'))
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div className="space-y-6 animate-pulse">
                <div className="h-8 w-48 rounded bg-muted" />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[...Array(4)].map((_, i) => <div key={i} className="h-28 rounded-lg bg-muted" />)}
                </div>
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2 h-64 rounded-lg bg-muted" />
                    <div className="h-64 rounded-lg bg-muted" />
                </div>
            </div>
        );
    }

    if (error) {
        return <p className="text-destructive text-sm">{error}</p>;
    }

    const { kpis, ordersByStatus, revenueByMonth, recentOrders, lowStockProducts } = data;

    const chartData = revenueByMonth.map(r => ({ ...r, label: r.label, display: formatMonth(r.label) }));

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Dashboard</h2>
                <p className="text-muted-foreground text-sm mt-1">Vue d'ensemble de l'activité</p>
            </div>

            {/* KPIs */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">CA ce mois</CardTitle>
                        <Euro className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold">{euros(kpis.revenueThisMonth)}</p>
                        <div className="mt-1">
                            <TrendBadge current={kpis.revenueThisMonth} previous={kpis.revenueLastMonth} />
                            {kpis.revenueLastMonth > 0 && (
                                <span className="text-xs text-muted-foreground ml-1">vs mois dernier</span>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Commandes ce mois</CardTitle>
                        <ShoppingCart className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold">{kpis.ordersThisMonth}</p>
                        <p className="text-xs text-muted-foreground mt-1">hors annulées / remboursées</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">En attente</CardTitle>
                        <Clock className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold">{kpis.ordersPending}</p>
                        {kpis.ordersPending > 0 && permissions.canViewOrders !== false && (
                            <Link to="/commandes?status=pending" className="text-xs text-primary underline mt-1 block">
                                Voir les commandes
                            </Link>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Clients</CardTitle>
                        <Users className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold">{kpis.customersTotal}</p>
                        <p className="text-xs text-muted-foreground mt-1">au total</p>
                    </CardContent>
                </Card>
            </div>

            {/* Graphique + statuts */}
            <div className="grid gap-4 lg:grid-cols-3">

                {/* Graphique CA mensuel */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">Chiffre d'affaires — 6 derniers mois</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {chartData.length === 0 ? (
                            <p className="text-sm text-muted-foreground py-8 text-center">Aucune donnée pour cette période.</p>
                        ) : (
                            <ResponsiveContainer width="100%" height={220}>
                                <BarChart data={chartData} margin={{ top: 4, right: 8, left: 0, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                                    <XAxis
                                        dataKey="display"
                                        tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }}
                                        axisLine={false}
                                        tickLine={false}
                                    />
                                    <YAxis
                                        tickFormatter={v => `${(v / 100).toLocaleString('fr-FR')} €`}
                                        tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                                        axisLine={false}
                                        tickLine={false}
                                        width={72}
                                    />
                                    <Tooltip content={<CustomTooltip />} cursor={{ fill: 'hsl(var(--muted))', radius: 4 }} />
                                    <Bar dataKey="revenue" fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>

                {/* Statuts */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">Commandes par statut</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {Object.keys(ordersByStatus).length === 0 ? (
                            <p className="text-sm text-muted-foreground text-center py-8">Aucune commande.</p>
                        ) : (
                            <ul className="space-y-2">
                                {Object.entries(STATUS_LABELS).map(([key, { label, color }]) => {
                                    const count = ordersByStatus[key];
                                    if (!count) return null;
                                    return (
                                        <li key={key} className="flex items-center justify-between">
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${color}`}>
                                                {label}
                                            </span>
                                            <span className="text-sm font-semibold tabular-nums">{count}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Alertes stock bas */}
            {lowStockProducts.length > 0 && (
                <Card className="border-orange-200">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div className="flex items-center gap-2">
                            <CardTitle className="text-sm font-medium">Alertes stock</CardTitle>
                            <span className="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                                {lowStockProducts.length} produit{lowStockProducts.length > 1 ? 's' : ''}
                            </span>
                        </div>
                        {permissions.canViewProducts !== false && <Link to="/produits" className="text-xs text-primary underline">Gérer les stocks</Link>}
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-xs text-muted-foreground">
                                    <th className="px-6 py-3 text-left font-medium">Produit</th>
                                    <th className="px-6 py-3 text-right font-medium">Stock</th>
                                    <th className="px-6 py-3 text-right font-medium">Seuil d'alerte</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lowStockProducts.map(p => {
                                    const isEmpty = p.stock === 0;
                                    return (
                                        <tr key={p.id} className="border-b last:border-0 hover:bg-muted/40 transition-colors">
                                            <td className="px-6 py-3 font-medium">
                                                {permissions.canEditProduct !== false
                                                    ? <Link to={`/produits/${p.id}/modifier`} className="hover:underline text-foreground">{p.name}</Link>
                                                    : p.name
                                                }
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                    isEmpty
                                                        ? 'bg-red-100 text-red-700'
                                                        : 'bg-orange-100 text-orange-700'
                                                }`}>
                                                    {isEmpty ? 'Rupture' : p.stock}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3 text-right text-muted-foreground tabular-nums">
                                                {p.lowStockThreshold}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            )}

            {/* Commandes récentes */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="text-sm font-medium">Dernières commandes</CardTitle>
                    {permissions.canViewOrders !== false && <Link to="/commandes" className="text-xs text-primary underline">Voir tout</Link>}
                </CardHeader>
                <CardContent className="p-0">
                    {recentOrders.length === 0 ? (
                        <p className="text-sm text-muted-foreground text-center py-8">Aucune commande.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-xs text-muted-foreground">
                                    <th className="px-6 py-3 text-left font-medium">Numéro</th>
                                    <th className="px-6 py-3 text-left font-medium">Client</th>
                                    <th className="px-6 py-3 text-left font-medium">Statut</th>
                                    <th className="px-6 py-3 text-right font-medium">Total</th>
                                    <th className="px-6 py-3 text-right font-medium">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentOrders.map(o => {
                                    const s = STATUS_LABELS[o.status] ?? { label: o.status, color: 'bg-muted text-muted-foreground' };
                                    return (
                                        <tr key={o.id} className="border-b last:border-0 hover:bg-muted/40 transition-colors">
                                            <td className="px-6 py-3">
                                                {permissions.canViewOrders !== false
                                                    ? <Link to={`/commandes/${o.id}`} className="font-mono text-xs text-primary hover:underline">{o.orderNumber}</Link>
                                                    : <span className="font-mono text-xs">{o.orderNumber}</span>
                                                }
                                            </td>
                                            <td className="px-6 py-3 text-muted-foreground">
                                                {o.customer ? `${o.customer.firstName} ${o.customer.lastName}` : '—'}
                                            </td>
                                            <td className="px-6 py-3">
                                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${s.color}`}>
                                                    {s.label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3 text-right font-medium tabular-nums">{euros(o.total)}</td>
                                            <td className="px-6 py-3 text-right text-muted-foreground">
                                                {new Date(o.createdAt).toLocaleDateString('fr-FR')}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
