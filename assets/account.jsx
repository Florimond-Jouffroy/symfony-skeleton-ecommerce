import './styles/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import AccountApp from './react/account/AccountApp';

const container = document.getElementById('account-root');
if (container) {
    createRoot(container).render(<AccountApp />);
}
