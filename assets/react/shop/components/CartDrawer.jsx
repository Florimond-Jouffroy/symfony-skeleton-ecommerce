import React from 'react';
import { useNavigate } from 'react-router-dom';
import { X, Trash2, ShoppingBag } from 'lucide-react';
import { useCart } from '../context/CartContext';

function formatPrice(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

export default function CartDrawer() {
    const { cart, isOpen, setIsOpen, updateQty, removeItem } = useCart();
    const navigate = useNavigate();

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex justify-end">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/40 backdrop-blur-sm"
                onClick={() => setIsOpen(false)}
            />

            {/* Panel */}
            <div className="relative flex h-full w-full max-w-md flex-col bg-background shadow-2xl">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-border px-5 py-4">
                    <div className="flex items-center gap-2">
                        <h2 className="text-base font-semibold">Mon panier</h2>
                        {cart.itemCount > 0 && (
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground">
                                {cart.itemCount}
                            </span>
                        )}
                    </div>
                    <button
                        onClick={() => setIsOpen(false)}
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>

                {/* Items */}
                <div className="flex-1 overflow-y-auto">
                    {cart.items.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-full gap-3 py-20 text-center px-6">
                            <ShoppingBag className="h-12 w-12 text-muted-foreground/30" />
                            <p className="text-sm font-medium text-muted-foreground">Votre panier est vide</p>
                            <button
                                onClick={() => { setIsOpen(false); navigate('/'); }}
                                className="mt-1 text-sm font-medium text-primary hover:underline"
                            >
                                Continuer mes achats
                            </button>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border px-5 py-2">
                            {cart.items.map((item) => (
                                <CartItem
                                    key={item.key}
                                    item={item}
                                    onUpdateQty={(q) => updateQty(item.key, q)}
                                    onRemove={() => removeItem(item.key)}
                                />
                            ))}
                        </ul>
                    )}
                </div>

                {/* Footer */}
                {cart.items.length > 0 && (
                    <div className="border-t border-border bg-background px-5 py-5 space-y-4">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Sous-total</span>
                            <span className="font-semibold text-base">{formatPrice(cart.subtotal)}</span>
                        </div>
                        <p className="text-xs text-muted-foreground">Frais de livraison calculés à la commande.</p>
                        <div className="flex flex-col gap-2">
                            <button
                                onClick={() => { setIsOpen(false); navigate('/panier'); }}
                                className="w-full rounded-xl bg-primary py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                            >
                                Voir mon panier
                            </button>
                            <button
                                onClick={() => { setIsOpen(false); navigate('/'); }}
                                className="w-full rounded-xl border border-border py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                            >
                                Continuer mes achats
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function CartItem({ item, onUpdateQty, onRemove }) {
    return (
        <li className="flex gap-3 py-4">
            {/* Image */}
            <div className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-muted">
                {item.imageUrl ? (
                    <img src={item.imageUrl} alt={item.productName} className="h-full w-full object-cover" />
                ) : (
                    <div className="h-full w-full flex items-center justify-center text-muted-foreground/30">
                        <ShoppingBag className="h-6 w-6" />
                    </div>
                )}
            </div>

            {/* Info */}
            <div className="flex flex-1 flex-col gap-2 min-w-0">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-sm font-medium leading-tight truncate">{item.productName}</p>
                        {item.variantName && (
                            <p className="text-xs text-muted-foreground">{item.variantName}</p>
                        )}
                    </div>
                    <button
                        onClick={onRemove}
                        className="shrink-0 rounded p-0.5 text-muted-foreground/50 hover:text-destructive transition-colors"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </button>
                </div>

                <div className="flex items-center justify-between">
                    {/* Qty stepper */}
                    <div className="flex items-center gap-1 rounded-lg border border-border">
                        <button
                            onClick={() => onUpdateQty(item.quantity - 1)}
                            className="flex h-7 w-7 items-center justify-center text-muted-foreground hover:bg-accent hover:text-foreground rounded-l-lg transition-colors text-lg leading-none"
                        >
                            −
                        </button>
                        <span className="w-7 text-center text-sm font-medium">{item.quantity}</span>
                        <button
                            onClick={() => onUpdateQty(item.quantity + 1)}
                            className="flex h-7 w-7 items-center justify-center text-muted-foreground hover:bg-accent hover:text-foreground rounded-r-lg transition-colors text-lg leading-none"
                        >
                            +
                        </button>
                    </div>

                    <span className="text-sm font-semibold">
                        {((item.unitPrice * item.quantity) / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })}
                    </span>
                </div>
            </div>
        </li>
    );
}
