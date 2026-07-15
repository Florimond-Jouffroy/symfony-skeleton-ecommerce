import React from 'react';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { CartProvider } from './context/CartContext';
import CartDrawer from './components/CartDrawer';
import Catalogue from './pages/Catalogue';
import Produit from './pages/Produit';
import Panier from './pages/Panier';
import Checkout from './pages/Checkout';

const root        = document.getElementById('shop-root');
const urls        = JSON.parse(root?.dataset.urls ?? '{}');
const isConnected = root?.dataset.isConnected === 'true';

export default function ShopApp() {
    return (
        <BrowserRouter basename="/boutique">
            <CartProvider urls={urls}>
                <CartDrawer />
                <Routes>
                    <Route index element={<Catalogue urls={urls} />} />
                    <Route path="produit/:slug" element={<Produit urls={urls} isConnected={isConnected} />} />
                    <Route path="panier" element={<Panier />} />
                    <Route path="commander" element={<Checkout urls={urls} />} />
                </Routes>
            </CartProvider>
        </BrowserRouter>
    );
}
