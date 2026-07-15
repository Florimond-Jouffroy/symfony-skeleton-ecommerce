import './styles/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import AdminApp from './react/admin/AdminApp';

const container = document.getElementById('admin-root');
if (container) {
    createRoot(container).render(<AdminApp />);
}
