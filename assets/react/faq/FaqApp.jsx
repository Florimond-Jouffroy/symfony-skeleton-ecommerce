import React from 'react';
import FaqPage from './pages/FaqPage';

const root = document.getElementById('faq-root');
const urls = JSON.parse(root?.dataset.urls ?? '{}');

export default function FaqApp() {
    return <FaqPage urls={urls} />;
}
