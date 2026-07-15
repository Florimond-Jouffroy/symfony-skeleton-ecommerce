import React from 'react';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import Liste from './pages/Liste';
import Article from './pages/Article';

const root = document.getElementById('blog-root');
const urls = JSON.parse(root?.dataset.urls ?? '{}');

export default function BlogApp() {
    return (
        <BrowserRouter basename="/blog">
            <Routes>
                <Route index element={<Liste urls={urls} />} />
                <Route path="article/:slug" element={<Article urls={urls} />} />
            </Routes>
        </BrowserRouter>
    );
}
