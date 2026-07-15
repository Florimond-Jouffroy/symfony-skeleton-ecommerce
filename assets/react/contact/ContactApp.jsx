import React from 'react';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import ContactPage from './pages/ContactPage';
import SuiviPage from './pages/SuiviPage';

const root        = document.getElementById('contact-root');
const urls        = JSON.parse(root?.dataset.urls ?? '{}');
const isConnected = root?.dataset.isConnected === 'true';
const prefillEmail = root?.dataset.prefillEmail ?? '';

export default function ContactApp() {
    return (
        <BrowserRouter basename="/contact">
            <Routes>
                <Route index element={<ContactPage urls={urls} isConnected={isConnected} prefillEmail={prefillEmail} />} />
                <Route path="suivi" element={<SuiviPage urls={urls} />} />
            </Routes>
        </BrowserRouter>
    );
}
