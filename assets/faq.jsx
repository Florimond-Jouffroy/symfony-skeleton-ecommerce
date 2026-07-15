import './styles/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import FaqApp from './react/faq/FaqApp';

const container = document.getElementById('faq-root');
if (container) {
    createRoot(container).render(<FaqApp />);
}
