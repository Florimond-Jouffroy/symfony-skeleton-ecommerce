import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { MoreHorizontal, Pencil, Plus, Search, Tag, Trash2 } from 'lucide-react';
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

function formatDate(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(iso));
}

export default function ArticlesList({ permissions = {}, urls = {} }) {
    const navigate = useNavigate();

    const [articles, setArticles]  = useState([]);
    const [total, setTotal]        = useState(0);
    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: PAGE_SIZE });
    const [search, setSearch]      = useState('');
    const [query, setQuery]        = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [categories, setCategories] = useState([]);
    const [loading, setLoading]    = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const isFirstLoad              = useRef(true);
    const [feedback, setFeedback]  = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting]  = useState(false);

    const pageCount = Math.max(1, Math.ceil(total / pagination.pageSize));

    useEffect(() => {
        api.get(urls.categories ?? '/api/admin/categories')
            .then(setCategories)
            .catch(() => {});
    }, [urls.categories]);

    useEffect(() => {
        const timer = setTimeout(() => {
            setQuery(search.trim());
            setPagination((p) => (p.pageIndex === 0 ? p : { ...p, pageIndex: 0 }));
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    const fetchArticles = useCallback(async () => {
        if (isFirstLoad.current) {
            setLoading(true);
        } else {
            setRefreshing(true);
        }
        try {
            const params = {
                q: query,
                page: pagination.pageIndex + 1,
                pageSize: pagination.pageSize,
            };
            if (categoryFilter) params.categoryId = categoryFilter;

            const data = await api.get(urls.articles ?? '/api/admin/articles', params);
            if (data.items.length === 0 && data.total > 0 && pagination.pageIndex > 0) {
                setPagination((p) => ({ ...p, pageIndex: 0 }));
                return;
            }
            setArticles(data.items);
            setTotal(data.total);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Impossible de charger les articles.') });
        } finally {
            isFirstLoad.current = false;
            setLoading(false);
            setRefreshing(false);
        }
    }, [query, pagination, categoryFilter]);

    useEffect(() => { fetchArticles(); }, [fetchArticles]);

    const handleTogglePublish = useCallback(async (article) => {
        setFeedback(null);
        const endpoint = article.status === 'published'
            ? `/api/admin/articles/${article.id}/depublier`
            : `/api/admin/articles/${article.id}/publier`;
        try {
            await api.post(endpoint);
            fetchArticles();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        }
    }, [fetchArticles]);

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        setFeedback(null);
        try {
            await api.delete(`/api/admin/articles/${deleteTarget.id}`);
            setFeedback({ type: 'success', message: `L'article « ${deleteTarget.title} » a été supprimé.` });
            setDeleteTarget(null);
            fetchArticles();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
            setDeleteTarget(null);
        } finally {
            setDeleting(false);
        }
    };

    const columns = useMemo(() => [
        {
            accessorKey: 'title',
            header: 'Titre',
            cell: ({ row }) => (
                <span className="font-medium">{row.original.title}</span>
            ),
        },
        {
            id: 'status',
            header: 'Statut',
            meta: { headerClassName: 'w-32' },
            cell: ({ row }) => (
                <Badge variant={row.original.status === 'published' ? 'default' : 'secondary'}>
                    {row.original.status === 'published' ? 'Publié' : 'Brouillon'}
                </Badge>
            ),
        },
        {
            id: 'categories',
            header: 'Catégories',
            meta: { headerClassName: 'w-48' },
            cell: ({ row }) => {
                const cats = row.original.categories ?? [];
                if (cats.length === 0) return <span className="text-muted-foreground text-xs">—</span>;
                return (
                    <div className="flex flex-wrap gap-1">
                        {cats.map((c) => (
                            <Badge key={c.id} variant="outline" className="text-xs py-0 px-1.5">{c.name}</Badge>
                        ))}
                    </div>
                );
            },
        },
        {
            accessorKey: 'authorEmail',
            header: 'Auteur',
            meta: { headerClassName: 'w-48', cellClassName: 'text-muted-foreground text-sm' },
        },
        {
            id: 'createdAt',
            header: 'Créé le',
            meta: { headerClassName: 'w-32', cellClassName: 'text-muted-foreground text-sm' },
            cell: ({ row }) => formatDate(row.original.createdAt),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { headerClassName: 'w-16', cellClassName: 'text-right' },
            cell: ({ row }) => {
                const article = row.original;
                const isPublished = article.status === 'published';

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="size-8">
                                <MoreHorizontal className="size-4" />
                                <span className="sr-only">Actions pour {article.title}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            {permissions.canEditArticle && (
                                <DropdownMenuItem onClick={() => navigate(`/articles/${article.id}/modifier`)}>
                                    <Pencil className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                            )}

                            {permissions.canPublishArticle && (
                                <DropdownMenuItem onClick={() => handleTogglePublish(article)}>
                                    {isPublished ? 'Repasser en brouillon' : 'Publier'}
                                </DropdownMenuItem>
                            )}

                            {permissions.canDeleteArticle && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={() => setDeleteTarget(article)}
                                    >
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
                    <h2 className="text-2xl font-bold tracking-tight">Articles</h2>
                    <p className="text-muted-foreground">Gestion des articles du blog.</p>
                </div>

                {permissions.canCreateArticle && (
                    <Button onClick={() => navigate('/articles/nouveau')}>
                        <Plus className="size-4" />
                        Nouvel article
                    </Button>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <div className="relative w-full max-w-sm">
                    <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rechercher par titre…"
                        className="pl-8"
                    />
                </div>

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
                data={articles}
                pageCount={pageCount}
                totalItems={total}
                pagination={pagination}
                onPaginationChange={setPagination}
                loading={loading}
                refreshing={refreshing}
                emptyMessage={query ? `Aucun article pour « ${query} ».` : 'Aucun article à afficher.'}
            />

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                {deleteTarget && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Supprimer l'article</DialogTitle>
                            <DialogDescription>
                                L'article <strong>{deleteTarget.title}</strong> sera définitivement supprimé.
                                Cette action est irréversible.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDeleteTarget(null)} disabled={deleting}>
                                Annuler
                            </Button>
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
