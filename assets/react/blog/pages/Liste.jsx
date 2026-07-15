import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../../utils/api';

const PAGE_SIZE = 9;

function formatDate(isoString) {
    if (!isoString) return '';
    return new Date(isoString).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function authorInitial(email) {
    return (email?.[0] ?? '?').toUpperCase();
}

export default function Liste({ urls }) {
    const [searchParams, setSearchParams] = useSearchParams();
    const navigate = useNavigate();

    const [articles, setArticles]     = useState([]);
    const [categories, setCategories] = useState([]);
    const [total, setTotal]           = useState(0);
    const [loading, setLoading]       = useState(true);
    const [search, setSearch]         = useState(searchParams.get('q') ?? '');

    const currentCategory = searchParams.get('categorie') ?? '';
    const currentPage     = Math.max(1, parseInt(searchParams.get('page') ?? '1', 10));
    const totalPages      = Math.ceil(total / PAGE_SIZE);

    useEffect(() => {
        api.get(urls.categories).then((data) => setCategories(data ?? []));
    }, []);

    useEffect(() => {
        setLoading(true);
        api.get(urls.articles, {
            q:         searchParams.get('q') || undefined,
            categorie: currentCategory || undefined,
            page:      currentPage,
            pageSize:  PAGE_SIZE,
        })
            .then((data) => {
                setArticles(data?.items ?? []);
                setTotal(data?.total ?? 0);
            })
            .finally(() => setLoading(false));
    }, [searchParams.toString()]);

    const selectCategory = (slug) => {
        setSearch('');
        setSearchParams(slug ? { categorie: slug } : {});
    };

    const handleSearch = (e) => {
        e.preventDefault();
        const params = {};
        if (search.trim()) params.q = search.trim();
        if (currentCategory) params.categorie = currentCategory;
        setSearchParams(params);
    };

    const goToPage = (page) => {
        const params = Object.fromEntries(searchParams);
        setSearchParams({ ...params, page: String(page) });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <div className="mx-auto max-w-6xl px-4 sm:px-6 py-12">
            {/* Header */}
            <div className="mb-10 text-center max-w-xl mx-auto">
                <h1 className="text-4xl font-bold tracking-tight">Blog</h1>
                <p className="mt-3 text-muted-foreground">
                    Actualités, conseils et nouveautés.
                </p>
            </div>

            {/* Search */}
            <form onSubmit={handleSearch} className="mb-6 flex gap-2 justify-center">
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Rechercher un article…"
                    className="w-full max-w-sm rounded-lg border border-input bg-background px-4 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                />
                <button
                    type="submit"
                    className="rounded-lg bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground hover:bg-secondary/70 transition-colors"
                >
                    Rechercher
                </button>
            </form>

            {/* Category pills */}
            {categories.length > 0 && (
                <div className="mb-10 flex flex-wrap justify-center gap-2">
                    <CategoryPill active={!currentCategory} onClick={() => selectCategory('')}>
                        Tous
                    </CategoryPill>
                    {categories.map((cat) => (
                        <CategoryPill
                            key={cat.id}
                            active={currentCategory === cat.slug}
                            onClick={() => selectCategory(cat.slug)}
                        >
                            {cat.name}
                        </CategoryPill>
                    ))}
                </div>
            )}

            {/* Counter */}
            {!loading && (
                <p className="mb-6 text-sm text-muted-foreground text-center">
                    {total} article{total !== 1 ? 's' : ''}
                </p>
            )}

            {/* Grid */}
            {loading ? (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <div key={i} className="rounded-2xl bg-muted animate-pulse overflow-hidden">
                            <div className="aspect-video" />
                            <div className="p-5 space-y-3">
                                <div className="h-4 bg-muted-foreground/20 rounded w-1/3" />
                                <div className="h-5 bg-muted-foreground/20 rounded w-3/4" />
                                <div className="h-4 bg-muted-foreground/20 rounded w-full" />
                                <div className="h-4 bg-muted-foreground/20 rounded w-5/6" />
                            </div>
                        </div>
                    ))}
                </div>
            ) : articles.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-24 text-center">
                    <svg className="mb-4 h-12 w-12 text-muted-foreground/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
                    </svg>
                    <p className="text-base font-medium text-muted-foreground">Aucun article trouvé</p>
                    <p className="mt-1 text-sm text-muted-foreground">Essayez d'autres critères de recherche.</p>
                </div>
            ) : (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {articles.map((article) => (
                        <ArticleCard
                            key={article.id}
                            article={article}
                            onClick={() => navigate(`/article/${article.slug}`)}
                        />
                    ))}
                </div>
            )}

            {/* Pagination */}
            {totalPages > 1 && (
                <div className="mt-12 flex items-center justify-center gap-2">
                    <button
                        disabled={currentPage <= 1}
                        onClick={() => goToPage(currentPage - 1)}
                        className="rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40 transition-colors"
                    >
                        ← Précédent
                    </button>
                    <span className="px-3 text-sm text-muted-foreground">
                        {currentPage} / {totalPages}
                    </span>
                    <button
                        disabled={currentPage >= totalPages}
                        onClick={() => goToPage(currentPage + 1)}
                        className="rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40 transition-colors"
                    >
                        Suivant →
                    </button>
                </div>
            )}
        </div>
    );
}

function ArticleCard({ article, onClick }) {
    return (
        <button
            onClick={onClick}
            className="group text-left rounded-2xl border border-border bg-card hover:shadow-lg transition-all duration-200 overflow-hidden w-full flex flex-col"
        >
            {/* Cover */}
            <div className="aspect-video bg-muted overflow-hidden shrink-0">
                {article.coverImage ? (
                    <img
                        src={article.coverImage}
                        alt={article.title}
                        className="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                ) : (
                    <div className="h-full w-full flex items-center justify-center text-muted-foreground/20">
                        <svg className="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
                        </svg>
                    </div>
                )}
            </div>

            {/* Content */}
            <div className="flex flex-col flex-1 p-5 space-y-3">
                {/* Categories */}
                {article.categories?.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {article.categories.map((cat) => (
                            <span
                                key={cat.id}
                                className="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                            >
                                {cat.name}
                            </span>
                        ))}
                    </div>
                )}

                {/* Title */}
                <h2 className="text-base font-semibold text-foreground line-clamp-2 leading-snug group-hover:text-primary transition-colors">
                    {article.title}
                </h2>

                {/* Excerpt */}
                {article.excerpt && (
                    <p className="text-sm text-muted-foreground line-clamp-3 leading-relaxed flex-1">
                        {article.excerpt}
                    </p>
                )}

                {/* Footer: author + date */}
                <div className="flex items-center gap-2 pt-1">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-foreground">
                        {(article.authorEmail?.[0] ?? '?').toUpperCase()}
                    </span>
                    <span className="text-xs text-muted-foreground truncate">
                        {article.publishedAt
                            ? new Date(article.publishedAt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
                            : ''}
                    </span>
                </div>
            </div>
        </button>
    );
}

function CategoryPill({ active, onClick, children }) {
    return (
        <button
            onClick={onClick}
            className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
                active
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}
