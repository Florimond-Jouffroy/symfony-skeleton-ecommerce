import React from 'react';
import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import AccountLayout from './layouts/AccountLayout';
import Dashboard from './pages/Dashboard';
import Commandes from './pages/Commandes';
import CommandeDetail from './pages/CommandeDetail';
import Profil from './pages/Profil';
import Support from './pages/Support';
import SupportDetail from './pages/SupportDetail';
import Avis from './pages/Avis';

const root      = document.getElementById('account-root');
const urls      = JSON.parse(root?.dataset.urls      ?? '{}');
const userEmail = root?.dataset.userEmail ?? '';

function SupportDetailWrapper() {
    const { id } = useParams();
    return <SupportDetail urls={urls} ticketId={id} />;
}

export default function AccountApp() {
    return (
        <BrowserRouter basename="/mon-compte">
            <Routes>
                <Route element={<AccountLayout userEmail={userEmail} logoutUrl={urls.logout} />}>
                    <Route index element={<Navigate to="/tableau-de-bord" replace />} />
                    <Route path="tableau-de-bord" element={<Dashboard urls={urls} userEmail={userEmail} />} />
                    <Route path="commandes"        element={<Commandes urls={urls} />} />
                    <Route path="commandes/:number" element={<CommandeDetail urls={urls} />} />
                    <Route path="profil"           element={<Profil urls={urls} />} />
                    <Route path="support"          element={<Support urls={urls} />} />
                    <Route path="support/:id"      element={<SupportDetailWrapper />} />
                    <Route path="avis"             element={<Avis urls={urls} />} />
                </Route>
            </Routes>
        </BrowserRouter>
    );
}
