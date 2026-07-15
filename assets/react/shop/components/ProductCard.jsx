import React from 'react';

function formatPrice(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

export default function ProductCard({ product, onClick }) {
    const discount = product.compareAtPrice
        ? Math.round((1 - product.price / product.compareAtPrice) * 100)
        : 0;

    return (
        <button
            onClick={onClick}
            className="group text-left rounded-2xl border border-border bg-card hover:shadow-lg transition-all duration-200 overflow-hidden w-full"
        >
            {/* Image */}
            <div className="aspect-square bg-muted overflow-hidden relative">
                {product.coverImage ? (
                    <img
                        src={product.coverImage}
                        alt={product.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center text-muted-foreground/30">
                        <svg className="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </div>
                )}
                {discount > 0 && (
                    <span className="absolute top-2 left-2 text-xs font-semibold text-white bg-destructive rounded-md px-2 py-0.5">
                        -{discount}%
                    </span>
                )}
                {product.stock === 0 && (
                    <div className="absolute inset-0 bg-background/60 flex items-center justify-center">
                        <span className="text-xs font-medium text-muted-foreground bg-background/90 rounded-full px-3 py-1 border border-border">
                            Rupture de stock
                        </span>
                    </div>
                )}
            </div>

            {/* Info */}
            <div className="p-4 space-y-1">
                <h3 className="text-sm font-medium text-foreground line-clamp-2 leading-snug">
                    {product.name}
                </h3>
                <div className="flex items-baseline gap-2">
                    <span className="text-base font-semibold text-foreground">
                        {formatPrice(product.price)}
                    </span>
                    {product.compareAtPrice && (
                        <span className="text-sm text-muted-foreground line-through">
                            {formatPrice(product.compareAtPrice)}
                        </span>
                    )}
                </div>
            </div>
        </button>
    );
}
