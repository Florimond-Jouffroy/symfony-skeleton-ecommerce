import React from 'react';
import { useNavigate } from 'react-router-dom';
import { ShoppingBag, Trash2, ArrowLeft } from 'lucide-react';
import { useCart } from '../context/CartContext';

function formatPrice(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

export default function Panier() {
    const navigate = useNavigate();
    const { cart, loading, updateQty, removeItem, clearCart } = useCart();

    if (loading) return <PageSkeleton />;

    if (cart.items.length === 0) {
        return (
            <div className="mx-auto max-w-2xl px-4 sm:px-6 py-28 text-center">
                <ShoppingBag className="mx-auto mb-4 h-14 w-14 text-muted-foreground/30" />
                <h1 className="text-2xl font-bold tracking-tight">Votre panier est vide</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Ajoutez des produits depuis la boutique pour commencer.
                </p>
                <button
                    onClick={() => navigate('/')}
                    className="mt-6 inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    Découvrir la boutique
                </button>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-7xl px-4 sm:px-6 py-12">
            {/* Header */}
            <div className="mb-8 flex items-center gap-4">
                <button
                    onClick={() => navigate('/')}
                    className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Continuer mes achats
                </button>
            </div>

            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold tracking-tight">Mon panier</h1>
                <button
                    onClick={clearCart}
                    className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-destructive transition-colors"
                >
                    <Trash2 className="h-3.5 w-3.5" />
                    Vider le panier
                </button>
            </div>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3 lg:items-start">
                {/* Items list */}
                <div className="lg:col-span-2">
                    <div className="rounded-xl border border-border bg-card overflow-hidden">
                        <ul className="divide-y divide-border">
                            {cart.items.map((item) => (
                                <CartRow
                                    key={item.key}
                                    item={item}
                                    onUpdateQty={(q) => updateQty(item.key, q)}
                                    onRemove={() => removeItem(item.key)}
                                    onNavigate={() => navigate(`/produit/${item.slug}`)}
                                />
                            ))}
                        </ul>
                    </div>
                </div>

                {/* Summary */}
                <div className="rounded-xl border border-border bg-card p-5 space-y-4 lg:sticky lg:top-24">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Récapitulatif
                    </h2>

                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">
                                Sous-total ({cart.itemCount} article{cart.itemCount !== 1 ? 's' : ''})
                            </dt>
                            <dd className="font-medium">{formatPrice(cart.subtotal)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Livraison</dt>
                            <dd className="text-muted-foreground">Calculée à l'étape suivante</dd>
                        </div>
                        <div className="flex justify-between border-t border-border pt-2 text-base font-semibold">
                            <dt>Total estimé</dt>
                            <dd>{formatPrice(cart.subtotal)}</dd>
                        </div>
                    </dl>

                    <button
                        onClick={() => navigate('/commander')}
                        className="w-full rounded-xl bg-primary py-3.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
                    >
                        Passer commande →
                    </button>
                </div>
            </div>
        </div>
    );
}

function CartRow({ item, onUpdateQty, onRemove, onNavigate }) {
    return (
        <li className="flex gap-4 p-4 sm:p-5">
            {/* Image */}
            <button
                onClick={onNavigate}
                className="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-muted hover:opacity-80 transition-opacity"
            >
                {item.imageUrl ? (
                    <img src={item.imageUrl} alt={item.productName} className="h-full w-full object-cover" />
                ) : (
                    <div className="h-full w-full flex items-center justify-center text-muted-foreground/30">
                        <ShoppingBag className="h-8 w-8" />
                    </div>
                )}
            </button>

            {/* Info */}
            <div className="flex flex-1 flex-col gap-3 min-w-0">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <button
                            onClick={onNavigate}
                            className="text-sm font-medium hover:underline underline-offset-2 text-left"
                        >
                            {item.productName}
                        </button>
                        {item.variantName && (
                            <p className="text-xs text-muted-foreground mt-0.5">{item.variantName}</p>
                        )}
                    </div>
                    <button
                        onClick={onRemove}
                        className="shrink-0 rounded p-1 text-muted-foreground/50 hover:text-destructive transition-colors"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>

                <div className="flex items-center justify-between">
                    {/* Qty stepper */}
                    <div className="flex items-center gap-0 rounded-lg border border-border overflow-hidden">
                        <button
                            onClick={() => onUpdateQty(item.quantity - 1)}
                            className="flex h-8 w-8 items-center justify-center text-muted-foreground hover:bg-accent hover:text-foreground transition-colors text-base"
                        >
                            −
                        </button>
                        <span className="flex h-8 w-10 items-center justify-center border-x border-border text-sm font-medium">
                            {item.quantity}
                        </span>
                        <button
                            onClick={() => onUpdateQty(item.quantity + 1)}
                            className="flex h-8 w-8 items-center justify-center text-muted-foreground hover:bg-accent hover:text-foreground transition-colors text-base"
                        >
                            +
                        </button>
                    </div>

                    <div className="text-right">
                        <p className="text-sm font-semibold">
                            {((item.unitPrice * item.quantity) / 100).toLocaleString('fr-FR', {
                                style: 'currency',
                                currency: 'EUR',
                            })}
                        </p>
                        {item.quantity > 1 && (
                            <p className="text-xs text-muted-foreground">
                                {(item.unitPrice / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })} / unité
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </li>
    );
}

function PageSkeleton() {
    return (
        <div className="mx-auto max-w-7xl px-4 sm:px-6 py-12 animate-pulse">
            <div className="mb-8 h-5 w-40 rounded bg-muted" />
            <div className="mb-6 h-7 w-40 rounded bg-muted" />
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-3">
                    {[1, 2].map((i) => <div key={i} className="h-28 rounded-xl bg-muted" />)}
                </div>
                <div className="h-64 rounded-xl bg-muted" />
            </div>
        </div>
    );
}
