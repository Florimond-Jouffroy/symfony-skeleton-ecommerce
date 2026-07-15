import React, { useEffect, useState } from 'react';
import { Star, Trash2 } from 'lucide-react';
import { api } from '../../utils/api';

function StarDisplay({ rating }) {
    return (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4, 5].map(i => (
                <Star
                    key={i}
                    className={`size-3.5 ${i <= rating ? 'fill-yellow-400 text-yellow-400' : 'fill-muted text-muted'}`}
                />
            ))}
        </div>
    );
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

export default function Avis({ urls }) {
    const [reviews, setReviews] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get(urls.reviews)
            .then(data => setReviews(data ?? []))
            .finally(() => setLoading(false));
    }, []);

    const handleDelete = async (review) => {
        if (!confirm('Supprimer cet avis ?')) return;
        try {
            await api.delete(`${urls.reviews}/${review.id}`);
            setReviews(rs => rs.filter(r => r.id !== review.id));
        } catch (err) {
            alert(err.message ?? 'Erreur lors de la suppression.');
        }
    };

    if (loading) {
        return (
            <div className="space-y-4 animate-pulse">
                {[1, 2, 3].map(i => <div key={i} className="h-24 rounded-xl bg-muted" />)}
            </div>
        );
    }

    if (reviews.length === 0) {
        return (
            <div className="rounded-xl border border-dashed p-12 text-center">
                <Star className="mx-auto mb-3 size-8 text-muted-foreground/40" />
                <p className="text-sm text-muted-foreground">Vous n'avez pas encore laissé d'avis.</p>
                <p className="mt-1 text-xs text-muted-foreground">Après réception d'une commande, vous pourrez noter vos achats depuis la fiche produit.</p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {reviews.map(review => (
                <div key={review.id} className="rounded-xl border border-border bg-card px-5 py-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="space-y-1.5 min-w-0">
                            <div className="flex items-center gap-2">
                                <StarDisplay rating={review.rating} />
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                    review.isApproved
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-yellow-100 text-yellow-700'
                                }`}>
                                    {review.isApproved ? 'Publié' : 'En attente de modération'}
                                </span>
                            </div>
                            <p className="text-sm font-medium">{review.productName}</p>
                            {review.comment && (
                                <p className="text-sm text-muted-foreground leading-relaxed">{review.comment}</p>
                            )}
                            <p className="text-xs text-muted-foreground">{formatDate(review.createdAt)}</p>
                        </div>
                        {!review.isApproved && (
                            <button
                                type="button"
                                onClick={() => handleDelete(review)}
                                className="shrink-0 rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors"
                                title="Supprimer"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}
