import './styles/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ShopApp from './react/shop/ShopApp';

const container = document.getElementById('shop-root');
if (container) {
    createRoot(container).render(<ShopApp />);
}
