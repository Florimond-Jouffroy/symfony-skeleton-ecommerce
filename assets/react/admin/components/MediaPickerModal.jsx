import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Search, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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

export default function MediaPickerModal({ open, onSelect, onCancel, urls = {} }) {
    const [items, setItems]         = useState([]);
    const [total, setTotal]         = useState(0);
    const [page, setPage]           = useState(1);
    const [search, setSearch]       = useState('');
    const [query, setQuery]         = useState('');
    const [loading, setLoading]     = useState(false);
    const [uploading, setUploading] = useState(false);
    const [feedback, setFeedback]   = useState(null);
    const fileInputRef              = useRef(null);

    const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE));

    const fetchMedia = useCallback(async () => {
        setLoading(true);
        try {
            const data = await api.get(urls.media ?? '/api/admin/medias', {
                q: query,
                page,
                pageSize: PAGE_SIZE,
            });
            setItems(data.items);
            setTotal(data.total);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setLoading(false);
        }
    }, [query, page, urls.media]);

    useEffect(() => {
        if (!open) return;
        fetchMedia();
    }, [open, fetchMedia]);

    // Debounce search
    useEffect(() => {
        const t = setTimeout(() => {
            setQuery(search.trim());
            setPage(1);
        }, 300);

        return () => clearTimeout(t);
    }, [search]);

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
                setFeedback({ type: 'info', message: 'Image déjà dans la bibliothèque.' });
            }
            await fetchMedia();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Upload échoué.') });
        } finally {
            setUploading(false);
            e.target.value = '';
        }
    };

    const handleSelect = (item) => {
        onSelect(item.url);
        setSearch('');
        setQuery('');
        setPage(1);
        setFeedback(null);
    };

    const handleClose = () => {
        setSearch('');
        setQuery('');
        setPage(1);
        setFeedback(null);
        onCancel();
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="max-w-3xl max-h-[80vh] flex flex-col gap-0 p-0">
                <DialogHeader className="px-6 pt-6 pb-4 border-b shrink-0">
                    <DialogTitle>Bibliothèque de médias</DialogTitle>

                    <div className="flex items-center gap-3 mt-3">
                        <div className="relative flex-1">
                            <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Rechercher une image…"
                                className="pl-8"
                            />
                        </div>

                        <Button
                            variant="outline"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={uploading}
                        >
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
                    </div>

                    {feedback && (
                        <p className={`text-xs mt-2 ${
                            feedback.type === 'error' ? 'text-destructive' :
                            feedback.type === 'info'  ? 'text-muted-foreground' :
                            'text-green-600'
                        }`}>
                            {feedback.message}
                        </p>
                    )}
                </DialogHeader>

                {/* Grille */}
                <div className="overflow-y-auto flex-1 p-6">
                    {loading ? (
                        <div className="grid grid-cols-4 gap-3">
                            {Array.from({ length: 8 }).map((_, i) => (
                                <div key={i} className="aspect-square rounded-md bg-muted animate-pulse" />
                            ))}
                        </div>
                    ) : items.length === 0 ? (
                        <p className="text-center text-sm text-muted-foreground py-12">
                            {query ? `Aucun résultat pour « ${query} ».` : 'Aucune image dans la bibliothèque.'}
                        </p>
                    ) : (
                        <div className="grid grid-cols-4 gap-3">
                            {items.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => handleSelect(item)}
                                    className="group relative aspect-square rounded-md overflow-hidden border bg-muted hover:border-primary focus:outline-none focus:ring-2 focus:ring-primary transition-colors"
                                    title={item.originalName}
                                >
                                    <img
                                        src={item.url}
                                        alt={item.originalName}
                                        className="w-full h-full object-cover"
                                    />
                                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors" />
                                    <div className="absolute bottom-0 left-0 right-0 bg-black/60 px-1.5 py-1 translate-y-full group-hover:translate-y-0 transition-transform">
                                        <p className="text-white text-xs truncate">{item.originalName}</p>
                                        <p className="text-white/70 text-xs">{formatSize(item.size)}</p>
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {pageCount > 1 && (
                    <div className="flex items-center justify-between px-6 py-3 border-t shrink-0 text-sm text-muted-foreground">
                        <span>{total} image{total > 1 ? 's' : ''}</span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPage((p) => p - 1)}
                                disabled={page === 1}
                            >
                                Précédent
                            </Button>
                            <span>{page} / {pageCount}</span>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPage((p) => p + 1)}
                                disabled={page === pageCount}
                            >
                                Suivant
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
