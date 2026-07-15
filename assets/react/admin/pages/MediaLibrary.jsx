import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Search, Trash2, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { api, getErrorMessage } from '../../utils/api';

const PAGE_SIZE = 24;

function formatSize(bytes) {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}

function formatDate(iso) {
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short' }).format(new Date(iso));
}

export default function MediaLibrary({ permissions = {}, urls = {} }) {
    const [items, setItems]           = useState([]);
    const [total, setTotal]           = useState(0);
    const [page, setPage]             = useState(1);
    const [search, setSearch]         = useState('');
    const [query, setQuery]           = useState('');
    const [loading, setLoading]       = useState(true);
    const [uploading, setUploading]   = useState(false);
    const [feedback, setFeedback]     = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting]     = useState(false);
    const fileInputRef                = useRef(null);

    const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE));

    useEffect(() => {
        const t = setTimeout(() => {
            setQuery(search.trim());
            setPage(1);
        }, 300);

        return () => clearTimeout(t);
    }, [search]);

    const fetchMedia = useCallback(async () => {
        setLoading(true);
        try {
            const data = await api.get(urls.media ?? '/api/admin/medias', {
                q: query,
                page,
                pageSize: PAGE_SIZE,
            });
            if (data.items.length === 0 && data.total > 0 && page > 1) {
                setPage((p) => p - 1);
                return;
            }
            setItems(data.items);
            setTotal(data.total);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Impossible de charger les médias.') });
        } finally {
            setLoading(false);
        }
    }, [query, page, urls.media]);

    useEffect(() => { fetchMedia(); }, [fetchMedia]);

    const handleFileChange = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploading(true);
        setFeedback(null);

        const formData = new FormData();
        formData.append('file', file);

        try {
            const media = await api.post(urls.mediaUpload ?? '/api/admin/medias', formData);
            if (media.isDuplicate) {
                setFeedback({ type: 'info', message: 'Cette image est déjà dans la bibliothèque.' });
            } else {
                setFeedback({ type: 'success', message: `« ${media.originalName} » ajouté.` });
            }
            fetchMedia();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Upload échoué.') });
        } finally {
            setUploading(false);
            e.target.value = '';
        }
    };

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        setFeedback(null);
        try {
            await api.delete(`/api/admin/medias/${deleteTarget.id}`);
            setFeedback({ type: 'success', message: `« ${deleteTarget.originalName} » supprimé.` });
            setDeleteTarget(null);
            fetchMedia();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
            setDeleteTarget(null);
        } finally {
            setDeleting(false);
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Médiathèque</h2>
                    <p className="text-muted-foreground">
                        {total > 0 ? `${total} image${total > 1 ? 's' : ''}` : 'Bibliothèque d\'images'}
                    </p>
                </div>

                {permissions.canUploadMedia && (
                    <>
                        <Button onClick={() => fileInputRef.current?.click()} disabled={uploading}>
                            <Upload className="size-4" />
                            {uploading ? 'Upload…' : 'Uploader'}
                        </Button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/gif,image/webp"
                            className="hidden"
                            onChange={handleFileChange}
                        />
                    </>
                )}
            </div>

            <div className="relative w-full max-w-sm">
                <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Rechercher par nom…"
                    className="pl-8"
                />
            </div>

            {feedback && (
                <p className={`text-sm ${
                    feedback.type === 'error'   ? 'text-destructive' :
                    feedback.type === 'info'    ? 'text-muted-foreground' :
                    'text-green-600'
                }`}>
                    {feedback.message}
                </p>
            )}

            {loading ? (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    {Array.from({ length: 12 }).map((_, i) => (
                        <div key={i} className="aspect-square rounded-lg bg-muted animate-pulse" />
                    ))}
                </div>
            ) : items.length === 0 ? (
                <div className="text-center py-16 text-muted-foreground">
                    <p className="text-sm">
                        {query ? `Aucune image pour « ${query} ».` : 'Aucune image dans la bibliothèque.'}
                    </p>
                    {!query && permissions.canUploadMedia && (
                        <Button
                            variant="outline"
                            className="mt-4"
                            onClick={() => fileInputRef.current?.click()}
                        >
                            <Upload className="size-4" />
                            Uploader votre première image
                        </Button>
                    )}
                </div>
            ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    {items.map((item) => (
                        <div key={item.id} className="group relative">
                            <div className="aspect-square rounded-lg overflow-hidden border bg-muted">
                                <img
                                    src={item.url}
                                    alt={item.originalName}
                                    className="w-full h-full object-cover"
                                />
                            </div>

                            {permissions.canDeleteMedia && (
                                <button
                                    type="button"
                                    onClick={() => setDeleteTarget(item)}
                                    className="absolute top-1.5 right-1.5 size-7 flex items-center justify-center rounded-md bg-black/60 text-white opacity-0 group-hover:opacity-100 transition-opacity hover:bg-destructive"
                                    title="Supprimer"
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            )}

                            <p className="mt-1.5 text-xs text-muted-foreground truncate" title={item.originalName}>
                                {item.originalName}
                            </p>
                            <p className="text-xs text-muted-foreground/70">
                                {formatSize(item.size)} · {formatDate(item.createdAt)}
                            </p>
                        </div>
                    ))}
                </div>
            )}

            {pageCount > 1 && (
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{total} image{total > 1 ? 's' : ''}</span>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" onClick={() => setPage((p) => p - 1)} disabled={page === 1}>
                            Précédent
                        </Button>
                        <span>{page} / {pageCount}</span>
                        <Button variant="outline" size="sm" onClick={() => setPage((p) => p + 1)} disabled={page === pageCount}>
                            Suivant
                        </Button>
                    </div>
                </div>
            )}

            <Dialog open={deleteTarget !== null} onOpenChange={(o) => !o && setDeleteTarget(null)}>
                {deleteTarget && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Supprimer l'image</DialogTitle>
                            <DialogDescription>
                                <strong>{deleteTarget.originalName}</strong> sera définitivement supprimée.
                                Si elle est utilisée dans des articles, les images ne s'afficheront plus.
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
