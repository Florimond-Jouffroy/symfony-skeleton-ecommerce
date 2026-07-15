import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../../utils/api';
import BlockRenderer from '../../admin/components/BlockRenderer';

export default function Article({ urls }) {
    const { slug } = useParams();
    const navigate = useNavigate();

    const [article, setArticle]   = useState(null);
    const [loading, setLoading]   = useState(true);
    const [notFound, setNotFound] = useState(false);

    useEffect(() => {
        setLoading(true);
        setNotFound(false);
        api.get(`${urls.articles}/${slug}`)
            .then((data) => setArticle(data))
            .catch((err) => {
                if (err instanceof ApiError && err.status === 404) setNotFound(true);
            })
            .finally(() => setLoading(false));
    }, [slug]);

    if (loading) return <PageSkeleton />;

    if (notFound || !article) {
        return (
            <div className="mx-auto max-w-3xl px-4 sm:px-6 py-28 text-center">
                <p className="text-lg font-medium text-muted-foreground">Article introuvable.</p>
                <button
                    onClick={() => navigate('/')}
                    className="mt-4 text-sm font-medium text-foreground underline underline-offset-4 hover:no-underline"
                >
                    Retour au blog
                </button>
            </div>
        );
    }

    const publishedDate = article.publishedAt
        ? new Date(article.publishedAt).toLocaleDateString('fr-FR', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null;

    return (
        <article className="mx-auto max-w-3xl px-4 sm:px-6 py-12">
            {/* Back */}
            <button
                onClick={() => navigate('/')}
                className="mb-8 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
            >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Retour au blog
            </button>

            {/* Categories */}
            {article.categories?.length > 0 && (
                <div className="mb-4 flex flex-wrap gap-2">
                    {article.categories.map((cat) => (
                        <span
                            key={cat.id}
                            className="rounded-full bg-primary/10 px-3 py-0.5 text-xs font-medium text-primary"
                        >
                            {cat.name}
                        </span>
                    ))}
                </div>
            )}

            {/* Title */}
            <h1 className="text-3xl font-bold tracking-tight sm:text-4xl leading-tight mb-4">
                {article.title}
            </h1>

            {/* Meta: author + date */}
            <div className="flex items-center gap-3 mb-8 pb-8 border-b border-border">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold text-foreground">
                    {(article.authorEmail?.[0] ?? '?').toUpperCase()}
                </span>
                <div>
                    <p className="text-sm font-medium text-foreground">{article.authorEmail}</p>
                    {publishedDate && (
                        <p className="text-xs text-muted-foreground">{publishedDate}</p>
                    )}
                </div>
            </div>

            {/* Cover image */}
            {article.coverImage && (
                <figure className="mb-10 -mx-4 sm:-mx-6">
                    <img
                        src={article.coverImage}
                        alt={article.title}
                        className="w-full max-h-96 object-cover sm:rounded-xl"
                    />
                </figure>
            )}

            {/* Excerpt */}
            {article.excerpt && (
                <p className="mb-8 text-lg text-muted-foreground leading-relaxed border-l-4 border-primary pl-4 italic">
                    {article.excerpt}
                </p>
            )}

            {/* Content */}
            <div className="prose-article">
                <BlockRenderer content={article.content} />
            </div>
        </article>
    );
}

function PageSkeleton() {
    return (
        <div className="mx-auto max-w-3xl animate-pulse px-4 sm:px-6 py-12">
            <div className="mb-8 h-5 w-28 rounded bg-muted" />
            <div className="mb-4 h-4 w-24 rounded-full bg-muted" />
            <div className="mb-3 h-10 w-3/4 rounded bg-muted" />
            <div className="mb-8 h-10 w-1/2 rounded bg-muted" />
            <div className="mb-10 flex items-center gap-3">
                <div className="h-9 w-9 rounded-full bg-muted" />
                <div className="space-y-1.5">
                    <div className="h-4 w-32 rounded bg-muted" />
                    <div className="h-3 w-20 rounded bg-muted" />
                </div>
            </div>
            <div className="mb-10 aspect-video rounded-xl bg-muted" />
            <div className="space-y-3">
                {Array.from({ length: 5 }).map((_, i) => (
                    <div key={i} className={`h-4 rounded bg-muted ${i % 3 === 0 ? 'w-full' : i % 3 === 1 ? 'w-5/6' : 'w-4/5'}`} />
                ))}
            </div>
        </div>
    );
}
