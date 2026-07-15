import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { MoreHorizontal, Package, Pencil, Plus, Search, Tag, Trash2 } from 'lucide-react';
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

function formatPrice(cents) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

export default function ProductsList({ permissions = {}, urls = {} }) {
    const navigate = useNavigate();

    const [products, setProducts]     = useState([]);
    const [total, setTotal]           = useState(0);
    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: PAGE_SIZE });
    const [search, setSearch]         = useState('');
    const [query, setQuery]           = useState('');
    const [statusFilter, setStatusFilter]     = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [categories, setCategories] = useState([]);
    const [loading, setLoading]       = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const isFirstLoad                 = useRef(true);
    const [feedback, setFeedback]     = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting]     = useState(false);

    useEffect(() => {
        api.get(urls.productCategories ?? '/api/admin/categories-produits')
            .then(setCategories)
            .catch(() => {});
    }, [urls.productCategories]);

    useEffect(() => {
        const timer = setTimeout(() => {
            setQuery(search.trim());
            setPagination((p) => (p.pageIndex === 0 ? p : { ...p, pageIndex: 0 }));
        }, 350);
        return () => clearTimeout(timer);
    }, [search]);

    const fetchProducts = useCallback(async () => {
        if (isFirstLoad.current) setLoading(true);
        else setRefreshing(true);

        try {
            const params = { q: query, page: pagination.pageIndex + 1, pageSize: pagination.pageSize };
            if (statusFilter) params.status = statusFilter;
            if (categoryFilter) params.categoryId = categoryFilter;

            const data = await api.get(urls.products ?? '/api/admin/produits', params);
            if (data.items.length === 0 && data.total > 0 && pagination.pageIndex > 0) {
                setPagination((p) => ({ ...p, pageIndex: 0 }));
                return;
            }
            setProducts(data.items);
            setTotal(data.total);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Impossible de charger les produits.') });
        } finally {
            isFirstLoad.current = false;
            setLoading(false);
            setRefreshing(false);
        }
    }, [query, pagination, statusFilter, categoryFilter, urls.products]);

    useEffect(() => { fetchProducts(); }, [fetchProducts]);

    const handleTogglePublish = useCallback(async (product) => {
        setFeedback(null);
        const endpoint = product.status === 'published'
            ? `/api/admin/produits/${product.id}/depublier`
            : `/api/admin/produits/${product.id}/publier`;
        try {
            await api.post(endpoint);
            fetchProducts();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        }
    }, [fetchProducts]);

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        setFeedback(null);
        try {
            await api.delete(`/api/admin/produits/${deleteTarget.id}`);
            setFeedback({ type: 'success', message: `Le produit « ${deleteTarget.name} » a été supprimé.` });
            setDeleteTarget(null);
            fetchProducts();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
            setDeleteTarget(null);
        } finally {
            setDeleting(false);
        }
    };

    const columns = useMemo(() => [
        {
            id: 'product',
            header: 'Produit',
            cell: ({ row }) => {
                const p = row.original;
                return (
                    <div className="flex items-center gap-3">
                        {p.coverImage ? (
                            <img src={p.coverImage} alt={p.name} className="size-10 rounded-md object-cover shrink-0 border" />
                        ) : (
                            <div className="size-10 rounded-md bg-muted flex items-center justify-center shrink-0">
                                <Package className="size-4 text-muted-foreground" />
                            </div>
                        )}
                        <div>
                            <p className="font-medium">{p.name}</p>
                            <p className="text-xs text-muted-foreground font-mono">{p.slug}</p>
                        </div>
                    </div>
                );
            },
        },
        {
            id: 'price',
            header: 'Prix',
            meta: { headerClassName: 'w-32' },
            cell: ({ row }) => {
                const p = row.original;
                return (
                    <div>
                        <span className="font-medium">{formatPrice(p.price)}</span>
                        {p.compareAtPrice && (
                            <span className="ml-2 text-xs text-muted-foreground line-through">{formatPrice(p.compareAtPrice)}</span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'stock',
            header: 'Stock',
            meta: { headerClassName: 'w-24' },
            cell: ({ row }) => {
                const p = row.original;
                const isLow = p.stock <= 5 && p.stock > 0;
                const isEmpty = p.stock === 0;
                return (
                    <span className={`text-sm font-medium ${isEmpty ? 'text-destructive' : isLow ? 'text-amber-600' : ''}`}>
                        {p.hasVariants ? `${p.stock} (var.)` : p.stock}
                    </span>
                );
            },
        },
        {
            id: 'status',
            header: 'Statut',
            meta: { headerClassName: 'w-28' },
            cell: ({ row }) => (
                <Badge variant={row.original.status === 'published' ? 'default' : 'secondary'}>
                    {row.original.status === 'published' ? 'Publié' : 'Brouillon'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { headerClassName: 'w-16', cellClassName: 'text-right' },
            cell: ({ row }) => {
                const product = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="size-8">
                                <MoreHorizontal className="size-4" />
                                <span className="sr-only">Actions pour {product.name}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            {permissions.canEditProduct && (
                                <DropdownMenuItem onClick={() => navigate(`/produits/${product.id}/modifier`)}>
                                    <Pencil className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                            )}
                            {permissions.canPublishProduct && (
                                <DropdownMenuItem onClick={() => handleTogglePublish(product)}>
                                    {product.status === 'published' ? 'Repasser en brouillon' : 'Publier'}
                                </DropdownMenuItem>
                            )}
                            {permissions.canDeleteProduct && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive" onClick={() => setDeleteTarget(product)}>
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
    ], [navigate, handleTogglePublish, permissions]);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Produits</h2>
                    <p className="text-muted-foreground">Gestion du catalogue produits.</p>
                </div>
                {permissions.canCreateProduct && (
                    <Button onClick={() => navigate('/produits/nouveau')}>
                        <Plus className="size-4" />
                        Nouveau produit
                    </Button>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <div className="relative w-full max-w-sm">
                    <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rechercher par nom…"
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
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="Tous les statuts" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all__">Tous les statuts</SelectItem>
                        <SelectItem value="published">Publié</SelectItem>
                        <SelectItem value="draft">Brouillon</SelectItem>
                    </SelectContent>
                </Select>

                {categories.length > 0 && (
                    <Select
                        value={categoryFilter}
                        onValueChange={(v) => {
                            setCategoryFilter(v === '__all__' ? '' : v);
                            setPagination((p) => ({ ...p, pageIndex: 0 }));
                        }}
                    >
                        <SelectTrigger className="w-48">
                            <Tag className="size-4 text-muted-foreground" />
                            <SelectValue placeholder="Toutes les catégories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">Toutes les catégories</SelectItem>
                            {categories.map((cat) => (
                                <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            <DataTable
                columns={columns}
                data={products}
                pageCount={Math.max(1, Math.ceil(total / pagination.pageSize))}
                totalItems={total}
                pagination={pagination}
                onPaginationChange={setPagination}
                loading={loading}
                refreshing={refreshing}
                emptyMessage={query ? `Aucun produit pour « ${query} ».` : 'Aucun produit à afficher.'}
            />

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                {deleteTarget && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Supprimer le produit</DialogTitle>
                            <DialogDescription>
                                Le produit <strong>{deleteTarget.name}</strong> sera définitivement supprimé. Cette action est irréversible.
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
