import React, { useEffect, useState } from 'react';
import { Check, ExternalLink, Star, Trash2, X } from 'lucide-react';
import { api } from '../../utils/api';

function StarDisplay({ rating, size = 'sm' }) {
    const cls = size === 'sm' ? 'size-3.5' : 'size-4';
    return (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4, 5].map(i => (
                <Star
                    key={i}
                    className={`${cls} ${i <= rating ? 'fill-yellow-400 text-yellow-400' : 'fill-muted text-muted'}`}
                />
            ))}
        </div>
    );
}

const TABS = [
    { key: 'all',     label: 'Tous' },
    { key: 'pending', label: 'En attente' },
    { key: 'approved', label: 'Approuvés' },
];

export default function ReviewManager({ urls, permissions = {} }) {
    const reviewsUrl = urls.reviews;
    const [reviews, setReviews] = useState([]);
    const [loading, setLoading] = useState(true);
    const [tab, setTab]         = useState('pending');
    const [error, setError]     = useState(null);

    const load = (filter) => {
        setLoading(true);
        const params = filter === 'pending' ? '?approved=false' : filter === 'approved' ? '?approved=true' : '';
        api.get(`${reviewsUrl}${params}`)
            .then(data => setReviews(data ?? []))
            .catch(() => setError('Impossible de charger les avis.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { load(tab); }, [tab]);

    const handleApprove = async (review) => {
        try {
            const updated = await api.patch(`${reviewsUrl}/${review.id}`, { isApproved: !review.isApproved });
            setReviews(rs => {
                if (tab === 'pending' && updated.isApproved)   return rs.filter(r => r.id !== updated.id);
                if (tab === 'approved' && !updated.isApproved) return rs.filter(r => r.id !== updated.id);
                return rs.map(r => r.id === updated.id ? updated : r);
            });
        } catch {
            setError('Erreur lors de la mise à jour.');
        }
    };

    const handleDelete = async (review) => {
        if (!confirm(`Supprimer l'avis de "${review.authorName}" ?`)) return;
        try {
            await api.delete(`${reviewsUrl}/${review.id}`);
            setReviews(rs => rs.filter(r => r.id !== review.id));
        } catch {
            setError('Erreur lors de la suppression.');
        }
    };

    const displayed = tab === 'all' ? reviews : reviews;

    return (
        <div className="space-y-5">
            {error && (
                <div className="flex items-center justify-between rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                    <button type="button" onClick={() => setError(null)}><X className="size-4" /></button>
                </div>
            )}

            {/* Tabs */}
            <div className="flex gap-1 rounded-lg border bg-muted/30 p-1 w-fit">
                {TABS.map(t => (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => setTab(t.key)}
                        className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                            tab === t.key
                                ? 'bg-background shadow-sm text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            ) : reviews.length === 0 ? (
                <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                    Aucun avis dans cette catégorie.
                </div>
            ) : (
                <div className="space-y-3">
                    {reviews.map(review => (
                        <div key={review.id} className="rounded-lg border bg-card px-5 py-4 space-y-3">
                            <div className="flex items-start gap-3">
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StarDisplay rating={review.rating} />
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                            review.isApproved
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-yellow-100 text-yellow-700'
                                        }`}>
                                            {review.isApproved ? 'Approuvé' : 'En attente'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm">
                                        <span className="font-medium">{review.authorName}</span>
                                        <span className="text-muted-foreground">·</span>
                                        <a
                                            href={`/boutique/produit/${review.productSlug}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors"
                                        >
                                            {review.productName}
                                            <ExternalLink className="size-3" />
                                        </a>
                                        <span className="text-muted-foreground">·</span>
                                        <span className="text-xs text-muted-foreground">{review.createdAt}</span>
                                    </div>
                                    {review.comment && (
                                        <p className="text-sm text-foreground/80 leading-relaxed pt-1">{review.comment}</p>
                                    )}
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    {permissions.canEditReview !== false && (
                                        <button
                                            type="button"
                                            onClick={() => handleApprove(review)}
                                            title={review.isApproved ? 'Dépublier' : 'Approuver'}
                                            className={`rounded p-1.5 transition-colors ${
                                                review.isApproved
                                                    ? 'text-green-600 hover:bg-green-50'
                                                    : 'text-muted-foreground hover:bg-green-50 hover:text-green-600'
                                            }`}
                                        >
                                            <Check className="size-4" />
                                        </button>
                                    )}
                                    {permissions.canDeleteReview !== false && (
                                        <button
                                            type="button"
                                            onClick={() => handleDelete(review)}
                                            className="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
