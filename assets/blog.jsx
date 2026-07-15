import './styles/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import BlogApp from './react/blog/BlogApp';

const container = document.getElementById('blog-root');
if (container) {
    createRoot(container).render(<BlogApp />);
}
