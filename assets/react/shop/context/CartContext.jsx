import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { api } from '../../utils/api';

const CartContext = createContext(null);

export function CartProvider({ urls, children }) {
    const [cart, setCart]       = useState({ items: [], itemCount: 0, subtotal: 0 });
    const [isOpen, setIsOpen]   = useState(false);
    const [loading, setLoading] = useState(true);

    const applyCart = useCallback((data) => {
        setCart(data);
        window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { count: data.itemCount } }));
    }, []);

    useEffect(() => {
        api.get(urls.cart)
            .then(applyCart)
            .finally(() => setLoading(false));
    }, []);

    const addItem = useCallback(async (productId, variantId, quantity = 1) => {
        const data = await api.post(urls.cart, { productId, variantId: variantId ?? null, quantity });
        applyCart(data);
        setIsOpen(true);
        return data;
    }, [urls.cart, applyCart]);

    const updateQty = useCallback(async (key, quantity) => {
        const data = await api.put(`${urls.cart}/${key}`, { quantity });
        applyCart(data);
        return data;
    }, [urls.cart, applyCart]);

    const removeItem = useCallback(async (key) => {
        const data = await api.delete(`${urls.cart}/${key}`);
        applyCart(data);
        return data;
    }, [urls.cart, applyCart]);

    const clearCart = useCallback(async () => {
        const data = await api.delete(urls.cart);
        applyCart(data);
        return data;
    }, [urls.cart, applyCart]);

    return (
        <CartContext.Provider value={{ cart, loading, isOpen, setIsOpen, addItem, updateQty, removeItem, clearCart }}>
            {children}
        </CartContext.Provider>
    );
}

export function useCart() {
    return useContext(CartContext);
}
