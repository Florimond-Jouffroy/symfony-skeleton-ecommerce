import React, { useEffect, useState } from 'react';
import { Star } from 'lucide-react';
import { api, ApiError } from '../../utils/api';

function StarButton({ value, selected, onHover, onClick }) {
    return (
        <button
            type="button"
            onMouseEnter={() => onHover(value)}
            onMouseLeave={() => onHover(0)}
            onClick={() => onClick(value)}
            className="transition-transform hover:scale-110"
        >
            <Star className={`size-7 ${value <= selected ? 'fill-yellow-400 text-yellow-400' : 'fill-muted text-muted-foreground/30'}`} />
        </button>
    );
}

function StarDisplay({ rating, size = 'sm' }) {
    const cls = size === 'lg' ? 'size-5' : 'size-4';
    return (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4, 5].map(i => (
                <Star key={i} className={`${cls} ${i <= rating ? 'fill-yellow-400 text-yellow-400' : 'fill-muted text-muted-foreground/20'}`} />
            ))}
        </div>
    );
}

function ReviewForm({ productId, reviewSubmitUrl, onSubmitted }) {
    const [rating, setRating]   = useState(0);
    const [hover, setHover]     = useState(0);
    const [comment, setComment] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError]     = useState(null);

    const displayRating = hover || rating;

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (rating === 0) { setError('Veuillez sélectionner une note.'); return; }
        setError(null);
        setSubmitting(true);
        try {
            await api.post(reviewSubmitUrl, { productId, rating, comment: comment.trim() });
            onSubmitted();
        } catch (err) {
            if (err instanceof ApiError) {
                setError(err.message ?? 'Erreur lors de l\'envoi.');
            } else {
                setError('Erreur lors de l\'envoi.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="rounded-xl border border-border bg-card p-5 space-y-4">
            <h3 className="text-sm font-semibold">Laisser un avis</h3>

            {error && (
                <p className="text-sm text-destructive">{error}</p>
            )}

            <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Votre note</p>
                <div className="flex gap-1">
                    {[1, 2, 3, 4, 5].map(v => (
                        <StarButton
                            key={v}
                            value={v}
                            selected={displayRating}
                            onHover={setHover}
                            onClick={setRating}
                        />
                    ))}
                </div>
            </div>

            <div className="space-y-1">
                <label className="text-xs text-muted-foreground">Commentaire <span className="font-normal">(facultatif)</span></label>
                <textarea
                    value={comment}
                    onChange={e => setComment(e.target.value)}
                    rows={3}
                    maxLength={1000}
                    className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary resize-none"
                    placeholder="Partagez votre expérience…"
                />
            </div>

            <button
                type="submit"
                disabled={submitting || rating === 0}
                className="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
            >
                {submitting ? 'Envoi en cours…' : 'Envoyer mon avis'}
            </button>
        </form>
    );
}

export default function ProductReviews({ product, urls, isConnected }) {
    const [data, setData]       = useState(null);
    const [loading, setLoading] = useState(true);
    const [submitted, setSubmitted] = useState(false);

    const fetchReviews = () => {
        api.get(`${urls.products}/${product.slug}/avis`)
            .then(d => setData(d))
            .catch(() => {})
            .finally(() => setLoading(false));
    };

    useEffect(() => { fetchReviews(); }, [product.slug]);

    const handleSubmitted = () => {
        setSubmitted(true);
        fetchReviews();
    };

    if (loading) {
        return (
            <div className="border-t border-border pt-8 space-y-4 animate-pulse">
                <div className="h-5 w-32 rounded bg-muted" />
                <div className="h-20 rounded-xl bg-muted" />
            </div>
        );
    }

    const avgRating = data?.avgRating ?? null;
    const count     = data?.count ?? 0;
    const reviews   = data?.reviews ?? [];

    return (
        <div className="border-t border-border pt-10 space-y-8">
            {/* Header */}
            <div className="flex items-center gap-4">
                <h2 className="text-xl font-bold">Avis clients</h2>
                {count > 0 && (
                    <div className="flex items-center gap-2">
                        <StarDisplay rating={Math.round(avgRating)} size="lg" />
                        <span className="text-sm font-semibold">{avgRating}/5</span>
                        <span className="text-sm text-muted-foreground">({count} avis)</span>
                    </div>
                )}
            </div>

            {/* Reviews list */}
            {count === 0 ? (
                <p className="text-sm text-muted-foreground">Aucun avis pour l'instant. Soyez le premier !</p>
            ) : (
                <div className="space-y-5">
                    {reviews.map(review => (
                        <div key={review.id} className="border-b border-border pb-5 last:border-0 last:pb-0">
                            <div className="flex items-center gap-2 mb-1">
                                <StarDisplay rating={review.rating} />
                                <span className="text-xs text-muted-foreground">{review.authorName.split('@')[0]}</span>
                                <span className="text-xs text-muted-foreground">·</span>
                                <span className="text-xs text-muted-foreground">
                                    {new Date(review.createdAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
                                </span>
                            </div>
                            {review.comment && (
                                <p className="text-sm leading-relaxed text-foreground/80">{review.comment}</p>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* Form */}
            {submitted ? (
                <div className="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-700">
                    Merci pour votre avis ! Il sera visible après modération.
                </div>
            ) : isConnected ? (
                <ReviewForm
                    productId={product.id}
                    reviewSubmitUrl={urls.reviewSubmit}
                    onSubmitted={handleSubmitted}
                />
            ) : (
                <p className="text-sm text-muted-foreground">
                    <a href="/connexion" className="font-medium text-foreground underline underline-offset-4 hover:no-underline">
                        Connectez-vous
                    </a>{' '}
                    pour laisser un avis (réservé aux acheteurs).
                </p>
            )}
        </div>
    );
}
