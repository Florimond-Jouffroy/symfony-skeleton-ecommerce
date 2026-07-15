import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../../utils/api';
import BlockRenderer from '../../admin/components/BlockRenderer';
import { useCart } from '../context/CartContext';
import ProductReviews from '../components/ProductReviews';

function formatPrice(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

export default function Produit({ urls, isConnected = false }) {
    const { slug }    = useParams();
    const navigate    = useNavigate();
    const { addItem } = useCart();

    const [product, setProduct]             = useState(null);
    const [loading, setLoading]             = useState(true);
    const [notFound, setNotFound]           = useState(false);
    const [selectedImage, setSelectedImage] = useState(0);
    const [selectedVariantId, setSelectedVariantId] = useState(null);
    const [addLoading, setAddLoading]       = useState(false);
    const [addSuccess, setAddSuccess]       = useState(false);

    useEffect(() => {
        setLoading(true);
        setNotFound(false);
        setSelectedImage(0);
        api.get(`${urls.products}/${slug}`)
            .then((data) => {
                setProduct(data);
                const firstVariant = data.variants?.[0];
                setSelectedVariantId(firstVariant?.id ?? null);
            })
            .catch((err) => {
                if (err instanceof ApiError && err.status === 404) setNotFound(true);
            })
            .finally(() => setLoading(false));
    }, [slug]);

    if (loading) return <PageSkeleton />;

    if (notFound || !product) {
        return (
            <div className="mx-auto max-w-7xl px-4 sm:px-6 py-28 text-center">
                <p className="text-lg font-medium text-muted-foreground">Produit introuvable.</p>
                <button
                    onClick={() => navigate('/')}
                    className="mt-4 text-sm font-medium text-foreground underline underline-offset-4 hover:no-underline"
                >
                    Retour à la boutique
                </button>
            </div>
        );
    }

    const images        = product.images ?? [];
    const currentVariant = product.hasVariants
        ? (product.variants?.find((v) => v.id === selectedVariantId) ?? null)
        : null;
    const displayPrice  = currentVariant ? currentVariant.price : product.price;
    const variantStock  = currentVariant ? currentVariant.stock : null;
    const inStock       = (variantStock ?? product.stock) > 0;

    const discount = product.compareAtPrice && !currentVariant
        ? Math.round((1 - product.price / product.compareAtPrice) * 100)
        : 0;

    return (
        <div className="mx-auto max-w-7xl px-4 sm:px-6 py-12">
            {/* Back */}
            <button
                onClick={() => navigate('/')}
                className="mb-8 flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
            >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Retour à la boutique
            </button>

            <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
                {/* Gallery */}
                <div className="space-y-3">
                    <div className="aspect-square overflow-hidden rounded-2xl bg-muted">
                        {images[selectedImage] ? (
                            <img
                                src={images[selectedImage].url}
                                alt={images[selectedImage].alt ?? product.name}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <NoImage />
                        )}
                    </div>
                    {images.length > 1 && (
                        <div className="flex gap-2 overflow-x-auto pb-1">
                            {images.map((img, i) => (
                                <button
                                    key={img.id}
                                    onClick={() => setSelectedImage(i)}
                                    className={`h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition-colors ${
                                        i === selectedImage
                                            ? 'border-primary'
                                            : 'border-border hover:border-foreground/40'
                                    }`}
                                >
                                    <img src={img.url} alt={img.alt ?? ''} className="h-full w-full object-cover" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Product info */}
                <div className="space-y-6">
                    {/* Categories */}
                    {product.categories?.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {product.categories.map((cat) => (
                                <span
                                    key={cat.id}
                                    className="rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground"
                                >
                                    {cat.name}
                                </span>
                            ))}
                        </div>
                    )}

                    <h1 className="text-3xl font-bold tracking-tight">{product.name}</h1>

                    {/* Price */}
                    <div className="flex items-baseline gap-3">
                        <span className="text-2xl font-bold">{formatPrice(displayPrice)}</span>
                        {product.compareAtPrice && !currentVariant && (
                            <>
                                <span className="text-lg text-muted-foreground line-through">
                                    {formatPrice(product.compareAtPrice)}
                                </span>
                                {discount > 0 && (
                                    <span className="rounded-md bg-destructive px-2 py-0.5 text-xs font-semibold text-white">
                                        -{discount}%
                                    </span>
                                )}
                            </>
                        )}
                    </div>

                    {/* Variants */}
                    {product.hasVariants && product.variants?.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-sm font-medium">Variante</p>
                            <div className="flex flex-wrap gap-2">
                                {product.variants.map((variant) => (
                                    <button
                                        key={variant.id}
                                        disabled={variant.stock === 0}
                                        onClick={() => setSelectedVariantId(variant.id)}
                                        className={`rounded-lg border px-4 py-2 text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${
                                            selectedVariantId === variant.id
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border hover:border-foreground/60'
                                        }`}
                                    >
                                        {variant.name}
                                        {variant.stock === 0 && ' (épuisé)'}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Stock indicator */}
                    <div className="flex items-center gap-2">
                        <span
                            className={`inline-block h-2 w-2 rounded-full ${
                                inStock ? 'bg-green-500' : 'bg-muted-foreground'
                            }`}
                        />
                        <span className={`text-sm ${inStock ? 'text-green-600' : 'text-muted-foreground'}`}>
                            {inStock ? 'En stock' : 'Rupture de stock'}
                        </span>
                    </div>

                    {/* CTA */}
                    <button
                        disabled={!inStock || addLoading}
                        onClick={async () => {
                            if (!inStock || addLoading) return;
                            setAddLoading(true);
                            setAddSuccess(false);
                            try {
                                await addItem(product.id, selectedVariantId);
                                setAddSuccess(true);
                                setTimeout(() => setAddSuccess(false), 2500);
                            } finally {
                                setAddLoading(false);
                            }
                        }}
                        className="w-full rounded-xl bg-primary px-6 py-3.5 text-base font-medium text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
                    >
                        {!inStock
                            ? 'Rupture de stock'
                            : addLoading
                                ? 'Ajout en cours…'
                                : addSuccess
                                    ? '✓ Ajouté au panier'
                                    : 'Ajouter au panier'}
                    </button>

                    {/* Description */}
                    {product.description && (
                        <div className="border-t border-border pt-8">
                            <h2 className="mb-4 text-base font-semibold">Description</h2>
                            <BlockRenderer content={product.description} />
                        </div>
                    )}
                </div>
            </div>

            <ProductReviews product={product} urls={urls} isConnected={isConnected} />
        </div>
    );
}

function NoImage() {
    return (
        <div className="flex h-full w-full items-center justify-center text-muted-foreground/30">
            <svg className="h-20 w-20" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
                <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
        </div>
    );
}

function PageSkeleton() {
    return (
        <div className="mx-auto max-w-7xl animate-pulse px-4 sm:px-6 py-12">
            <div className="mb-8 h-5 w-36 rounded bg-muted" />
            <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
                <div className="aspect-square rounded-2xl bg-muted" />
                <div className="space-y-4">
                    <div className="h-8 w-3/4 rounded bg-muted" />
                    <div className="h-7 w-1/3 rounded bg-muted" />
                    <div className="h-4 w-full rounded bg-muted" />
                    <div className="h-4 w-5/6 rounded bg-muted" />
                    <div className="h-12 w-full rounded-xl bg-muted" />
                </div>
            </div>
        </div>
    );
}
