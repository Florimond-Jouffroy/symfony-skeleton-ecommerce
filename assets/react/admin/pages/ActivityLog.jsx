import React, { useCallback, useEffect, useState } from 'react';
import { Clock, Filter, RefreshCw, X } from 'lucide-react';
import { api } from '../../utils/api';

function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const ENTITY_TYPES = [
    { value: '',        label: 'Tous' },
    { value: 'order',   label: 'Commandes' },
    { value: 'product', label: 'Produits' },
    { value: 'ticket',  label: 'Tickets' },
    { value: 'review',  label: 'Avis' },
];

const ACTION_CONFIG = {
    'order.status_changed':  { label: 'Statut modifié',    color: 'bg-blue-100 text-blue-800' },
    'product.published':     { label: 'Publié',             color: 'bg-green-100 text-green-800' },
    'product.unpublished':   { label: 'Dépublié',           color: 'bg-yellow-100 text-yellow-800' },
    'product.deleted':       { label: 'Supprimé',           color: 'bg-red-100 text-red-800' },
    'ticket.status_changed': { label: 'Statut modifié',    color: 'bg-blue-100 text-blue-800' },
    'review.approved':       { label: 'Approuvé',           color: 'bg-green-100 text-green-800' },
    'review.unapproved':     { label: 'Désapprouvé',        color: 'bg-yellow-100 text-yellow-800' },
    'review.deleted':        { label: 'Supprimé',           color: 'bg-red-100 text-red-800' },
};

const ENTITY_LABELS = {
    order:   'Commande',
    product: 'Produit',
    ticket:  'Ticket',
    review:  'Avis',
};

function ActionBadge({ action }) {
    const cfg = ACTION_CONFIG[action] ?? { label: action, color: 'bg-gray-100 text-gray-700' };
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${cfg.color}`}>
            {cfg.label}
        </span>
    );
}

function ContextDetail({ action, context }) {
    if (!context || Object.keys(context).length === 0) return null;

    if (action === 'order.status_changed' || action === 'ticket.status_changed') {
        return (
            <span className="text-xs text-muted-foreground">
                {context.from} → {context.to}
                {context.comment && <span className="ml-1 italic">"{context.comment}"</span>}
            </span>
        );
    }

    if (action === 'review.approved' || action === 'review.unapproved' || action === 'review.deleted') {
        return (
            <span className="text-xs text-muted-foreground">
                {context.author} — {context.rating}/5
            </span>
        );
    }

    return null;
}

export default function ActivityLog({ urls }) {
    const [items, setItems]       = useState([]);
    const [total, setTotal]       = useState(0);
    const [page, setPage]         = useState(1);
    const [loading, setLoading]   = useState(true);
    const [error, setError]       = useState(null);
    const [entityType, setEntityType] = useState('');
    const [from, setFrom]         = useState('');
    const [to, setTo]             = useState('');

    const PAGE_SIZE = 50;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

    const load = useCallback(() => {
        setLoading(true);
        setError(null);

        const params = new URLSearchParams({ page, pageSize: PAGE_SIZE });
        if (entityType) params.set('entityType', entityType);
        if (from)       params.set('from', from);
        if (to)         params.set('to', to);

        api.get(`${urls.activityLog}?${params}`)
            .then(data => {
                setItems(data.items ?? []);
                setTotal(data.total ?? 0);
            })
            .catch(() => setError('Impossible de charger le journal.'))
            .finally(() => setLoading(false));
    }, [urls.activityLog, page, entityType, from, to]);

    useEffect(() => { load(); }, [load]);

    const handleFilterChange = (setter) => (e) => {
        setter(e.target.value);
        setPage(1);
    };

    const resetFilters = () => {
        setEntityType('');
        setFrom('');
        setTo('');
        setPage(1);
    };

    const hasActiveFilters = entityType || from || to;

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Journal d'activité</h1>
                    <p className="text-sm text-muted-foreground mt-0.5">
                        Historique des actions importantes réalisées dans l'administration.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={load}
                    className="inline-flex items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm hover:bg-accent"
                >
                    <RefreshCw className={`size-4 ${loading ? 'animate-spin' : ''}`} />
                    Actualiser
                </button>
            </div>

            {/* Filtres */}
            <div className="flex flex-wrap gap-3 rounded-lg border bg-muted/30 p-3">
                <div className="flex items-center gap-2">
                    <Filter className="size-4 text-muted-foreground" />
                    <span className="text-sm font-medium">Filtres</span>
                </div>

                <select
                    value={entityType}
                    onChange={handleFilterChange(setEntityType)}
                    className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                >
                    {ENTITY_TYPES.map(t => (
                        <option key={t.value} value={t.value}>{t.label}</option>
                    ))}
                </select>

                <input
                    type="date"
                    value={from}
                    onChange={handleFilterChange(setFrom)}
                    className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                    placeholder="Depuis"
                />
                <input
                    type="date"
                    value={to}
                    onChange={handleFilterChange(setTo)}
                    className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                    placeholder="Jusqu'au"
                />

                {hasActiveFilters && (
                    <button
                        type="button"
                        onClick={resetFilters}
                        className="inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <X className="size-3.5" />
                        Effacer
                    </button>
                )}
            </div>

            {error && (
                <div className="flex items-center justify-between rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                    <button type="button" onClick={() => setError(null)}><X className="size-4" /></button>
                </div>
            )}

            {/* Table */}
            <div className="rounded-lg border overflow-hidden">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/50">
                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Action</th>
                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Élément</th>
                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Détail</th>
                            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Opérateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && (
                            <tr>
                                <td colSpan={6} className="px-4 py-10 text-center text-muted-foreground">
                                    <RefreshCw className="size-5 animate-spin mx-auto mb-2" />
                                    Chargement…
                                </td>
                            </tr>
                        )}
                        {!loading && items.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-10 text-center text-muted-foreground">
                                    <Clock className="size-8 mx-auto mb-2 opacity-30" />
                                    Aucune activité enregistrée.
                                </td>
                            </tr>
                        )}
                        {!loading && items.map(item => (
                            <tr key={item.id} className="border-b last:border-0 hover:bg-muted/20">
                                <td className="px-4 py-3 whitespace-nowrap text-muted-foreground text-xs">
                                    {fmtDate(item.createdAt)}
                                </td>
                                <td className="px-4 py-3 whitespace-nowrap text-xs text-muted-foreground">
                                    {ENTITY_LABELS[item.entityType] ?? item.entityType}
                                </td>
                                <td className="px-4 py-3">
                                    <ActionBadge action={item.action} />
                                </td>
                                <td className="px-4 py-3 font-medium">
                                    {item.entityLabel}
                                </td>
                                <td className="px-4 py-3">
                                    <ContextDetail action={item.action} context={item.context} />
                                </td>
                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                    {item.performedByEmail}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">
                        {total} entrée{total > 1 ? 's' : ''} au total
                    </span>
                    <div className="flex gap-1">
                        <button
                            type="button"
                            onClick={() => setPage(p => Math.max(1, p - 1))}
                            disabled={page === 1}
                            className="rounded-md border px-3 py-1.5 disabled:opacity-40 hover:bg-accent"
                        >
                            Précédent
                        </button>
                        <span className="flex items-center px-3 text-muted-foreground">
                            {page} / {totalPages}
                        </span>
                        <button
                            type="button"
                            onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                            disabled={page === totalPages}
                            className="rounded-md border px-3 py-1.5 disabled:opacity-40 hover:bg-accent"
                        >
                            Suivant
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
