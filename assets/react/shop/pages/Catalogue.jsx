import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../../utils/api';
import ProductCard from '../components/ProductCard';

const PAGE_SIZE = 12;

export default function Catalogue({ urls }) {
    const [searchParams, setSearchParams] = useSearchParams();
    const navigate = useNavigate();

    const [products, setProducts]     = useState([]);
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
        api.get(urls.products, {
            q:         searchParams.get('q') || undefined,
            categorie: currentCategory || undefined,
            page:      currentPage,
            pageSize:  PAGE_SIZE,
        })
            .then((data) => {
                setProducts(data?.items ?? []);
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
        <div className="mx-auto max-w-7xl px-4 sm:px-6 py-12">
            {/* Header */}
            <div className="mb-10">
                <h1 className="text-3xl font-bold tracking-tight">Boutique</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {loading ? ' ' : `${total} produit${total !== 1 ? 's' : ''}`}
                </p>
            </div>

            <div className="flex gap-10">
                {/* Sidebar */}
                <aside className="hidden lg:block w-48 shrink-0">
                    <p className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Catégories
                    </p>
                    <nav className="space-y-0.5">
                        <CategoryButton
                            active={!currentCategory}
                            onClick={() => selectCategory('')}
                        >
                            Tous les produits
                        </CategoryButton>
                        {categories.map((cat) => (
                            <CategoryButton
                                key={cat.id}
                                active={currentCategory === cat.slug}
                                onClick={() => selectCategory(cat.slug)}
                            >
                                {cat.name}
                            </CategoryButton>
                        ))}
                    </nav>
                </aside>

                {/* Main */}
                <div className="flex-1 min-w-0">
                    {/* Search bar */}
                    <form onSubmit={handleSearch} className="mb-8 flex gap-2">
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher un produit…"
                            className="flex-1 max-w-sm rounded-lg border border-input bg-background px-4 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                        <button
                            type="submit"
                            className="rounded-lg bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground hover:bg-secondary/70 transition-colors"
                        >
                            Rechercher
                        </button>
                    </form>

                    {/* Mobile category pills */}
                    {categories.length > 0 && (
                        <div className="flex gap-2 flex-wrap mb-6 lg:hidden">
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

                    {/* Grid */}
                    {loading ? (
                        <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                            {Array.from({ length: 8 }).map((_, i) => (
                                <div key={i} className="aspect-[3/4] rounded-2xl bg-muted animate-pulse" />
                            ))}
                        </div>
                    ) : products.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-28 text-center">
                            <svg className="mb-4 h-12 w-12 text-muted-foreground/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z" />
                            </svg>
                            <p className="text-base font-medium text-muted-foreground">Aucun produit trouvé</p>
                            <p className="mt-1 text-sm text-muted-foreground">Essayez d'autres critères de recherche.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                            {products.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    onClick={() => navigate(`/produit/${product.slug}`)}
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
            </div>
        </div>
    );
}

function CategoryButton({ active, onClick, children }) {
    return (
        <button
            onClick={onClick}
            className={`w-full rounded-lg px-3 py-2 text-left text-sm transition-colors ${
                active
                    ? 'bg-primary text-primary-foreground font-medium'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

function CategoryPill({ active, onClick, children }) {
    return (
        <button
            onClick={onClick}
            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                active
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}
