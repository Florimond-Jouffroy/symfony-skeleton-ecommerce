import './styles/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ContactApp from './react/contact/ContactApp';

const container = document.getElementById('contact-root');
if (container) {
    createRoot(container).render(<ContactApp />);
}
