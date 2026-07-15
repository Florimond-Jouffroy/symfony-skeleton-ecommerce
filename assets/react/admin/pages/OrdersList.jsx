import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Eye, MoreHorizontal, Search, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { api, getErrorMessage } from '../../utils/api';

const PAGE_SIZE = 20;

const STATUS_LABELS = {
    pending:   { label: 'En attente',  variant: 'secondary' },
    confirmed: { label: 'Confirmée',   variant: 'default'   },
    shipped:   { label: 'Expédiée',    variant: 'default'   },
    delivered: { label: 'Livrée',      variant: 'default'   },
    cancelled: { label: 'Annulée',     variant: 'destructive' },
    refunded:  { label: 'Remboursée',  variant: 'outline'   },
};

function StatusBadge({ status }) {
    const s = STATUS_LABELS[status] ?? { label: status, variant: 'secondary' };
    return <Badge variant={s.variant}>{s.label}</Badge>;
}

function formatPrice(cents) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

export default function OrdersList({ permissions = {}, urls = {} }) {
    const navigate = useNavigate();

    const [orders, setOrders]         = useState([]);
    const [total, setTotal]           = useState(0);
    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: PAGE_SIZE });
    const [search, setSearch]         = useState('');
    const [query, setQuery]           = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [loading, setLoading]       = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const isFirstLoad                 = useRef(true);
    const [feedback, setFeedback]     = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting]     = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => {
            setQuery(search.trim());
            setPagination((p) => (p.pageIndex === 0 ? p : { ...p, pageIndex: 0 }));
        }, 350);
        return () => clearTimeout(timer);
    }, [search]);

    const fetchOrders = useCallback(async () => {
        if (isFirstLoad.current) setLoading(true);
        else setRefreshing(true);

        try {
            const params = { q: query, page: pagination.pageIndex + 1, pageSize: pagination.pageSize };
            if (statusFilter) params.status = statusFilter;

            const data = await api.get(urls.orders ?? '/api/admin/commandes', params);
            if (data.items.length === 0 && data.total > 0 && pagination.pageIndex > 0) {
                setPagination((p) => ({ ...p, pageIndex: 0 }));
                return;
            }
            setOrders(data.items);
            setTotal(data.total);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Impossible de charger les commandes.') });
        } finally {
            isFirstLoad.current = false;
            setLoading(false);
            setRefreshing(false);
        }
    }, [query, pagination, statusFilter, urls.orders]);

    useEffect(() => { fetchOrders(); }, [fetchOrders]);

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        setFeedback(null);
        try {
            await api.delete(`/api/admin/commandes/${deleteTarget.id}`);
            setFeedback({ type: 'success', message: `Commande ${deleteTarget.orderNumber} supprimée.` });
            setDeleteTarget(null);
            fetchOrders();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
            setDeleteTarget(null);
        } finally {
            setDeleting(false);
        }
    };

    const columns = useMemo(() => [
        {
            accessorKey: 'orderNumber',
            header: 'Commande',
            cell: ({ row }) => (
                <span className="font-mono font-medium">{row.original.orderNumber}</span>
            ),
        },
        {
            id: 'customer',
            header: 'Client',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.customer.fullName}</p>
                    <p className="text-xs text-muted-foreground">{row.original.customer.email}</p>
                </div>
            ),
        },
        {
            id: 'status',
            header: 'Statut',
            meta: { headerClassName: 'w-32' },
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        {
            id: 'total',
            header: 'Total',
            meta: { headerClassName: 'w-28' },
            cell: ({ row }) => (
                <span className="font-medium">{formatPrice(row.original.total)}</span>
            ),
        },
        {
            id: 'createdAt',
            header: 'Date',
            meta: { headerClassName: 'w-40', cellClassName: 'text-muted-foreground text-sm' },
            cell: ({ row }) => formatDate(row.original.createdAt),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { headerClassName: 'w-16', cellClassName: 'text-right' },
            cell: ({ row }) => {
                const order = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="size-8" onClick={e => e.stopPropagation()}>
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuItem onClick={() => navigate(`/commandes/${order.id}`)}>
                                <Eye className="size-4" />
                                Voir le détail
                            </DropdownMenuItem>
                            {permissions.canDeleteOrder && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive" onClick={() => setDeleteTarget(order)}>
                                        <Trash2 className="size-4" />
                                        Supprimer
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ], [navigate, permissions]);

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Commandes</h2>
                <p className="text-muted-foreground">Suivi et gestion des commandes.</p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <div className="relative w-full max-w-sm">
                    <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Numéro, client, email…"
                        className="pl-8"
                    />
                </div>

                <Select
                    value={statusFilter}
                    onValueChange={(v) => {
                        setStatusFilter(v === '__all__' ? '' : v);
                        setPagination((p) => ({ ...p, pageIndex: 0 }));
                    }}
                >
                    <SelectTrigger className="w-44">
                        <SelectValue placeholder="Tous les statuts" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all__">Tous les statuts</SelectItem>
                        {Object.entries(STATUS_LABELS).map(([value, { label }]) => (
                            <SelectItem key={value} value={value}>{label}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            <DataTable
                columns={columns}
                data={orders}
                pageCount={Math.max(1, Math.ceil(total / pagination.pageSize))}
                totalItems={total}
                pagination={pagination}
                onPaginationChange={setPagination}
                onRowClick={(order) => navigate(`/commandes/${order.id}`)}
                loading={loading}
                refreshing={refreshing}
                emptyMessage={query ? `Aucune commande pour « ${query} ».` : 'Aucune commande à afficher.'}
            />

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                {deleteTarget && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Supprimer la commande</DialogTitle>
                            <DialogDescription>
                                La commande <strong>{deleteTarget.orderNumber}</strong> sera définitivement supprimée.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDeleteTarget(null)} disabled={deleting}>Annuler</Button>
                            <Button variant="destructive" onClick={handleDelete} disabled={deleting}>
                                {deleting ? 'Suppression…' : 'Supprimer'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>
        </div>
    );
}
