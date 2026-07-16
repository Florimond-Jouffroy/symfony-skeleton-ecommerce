import React from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import AdminLayout from './layouts/AdminLayout';
import ActivityLog from './pages/ActivityLog';
import ArticleEditor from './pages/ArticleEditor';
import ArticlesList from './pages/ArticlesList';
import CategoryManager from './pages/CategoryManager';
import InvoicesList from './pages/InvoicesList';
import FaqManager from './pages/FaqManager';
import StaticPageManager from './pages/StaticPageManager';
import ReviewManager from './pages/ReviewManager';
import SupportManager from './pages/SupportManager';
import PromoCodeManager from './pages/PromoCodeManager';
import OrderDetail from './pages/OrderDetail';
import OrdersList from './pages/OrdersList';
import Dashboard from './pages/Dashboard';
import ProductCategoryManager from './pages/ProductCategoryManager';
import ProductEditor from './pages/ProductEditor';
import ProductsList from './pages/ProductsList';
import MediaLibrary from './pages/MediaLibrary';
import Settings from './pages/Settings';
import TwoFactorSecurity from './pages/TwoFactorSecurity';
import UsersList from './pages/UsersList';
import ShippingMethods from './pages/ShippingMethods';

const root        = document.getElementById('admin-root');
const userEmail   = root?.dataset.userEmail ?? '';
const logoutUrl   = root?.dataset.logoutUrl ?? '/deconnexion';
const permissions = JSON.parse(root?.dataset.permissions ?? '{}');
const urls        = JSON.parse(root?.dataset.urls        ?? '{}');
const appName     = root?.dataset.appName ?? 'Admin';

export default function AdminApp() {
    return (
        <BrowserRouter basename="/admin">
            <Routes>
                <Route element={<AdminLayout userEmail={userEmail} logoutUrl={logoutUrl} notificationsUrl={urls.notifications} permissions={permissions} appName={appName} />}>
                    <Route index element={<Navigate to="/dashboard" replace />} />
                    <Route path="dashboard" element={<Dashboard permissions={permissions} urls={urls} />} />
                    <Route path="utilisateurs" element={<UsersList permissions={permissions} urls={urls} />} />
                    <Route path="articles" element={<ArticlesList permissions={permissions} urls={urls} />} />
                    <Route path="articles/nouveau" element={<ArticleEditor permissions={permissions} urls={urls} />} />
                    <Route path="articles/:id/modifier" element={<ArticleEditor permissions={permissions} urls={urls} />} />
                    <Route path="categories" element={<CategoryManager permissions={permissions} urls={urls} />} />
                    <Route path="commandes" element={<OrdersList permissions={permissions} urls={urls} />} />
                    <Route path="commandes/:id" element={<OrderDetail permissions={permissions} />} />
                    <Route path="factures" element={<InvoicesList permissions={permissions} urls={urls} />} />
                    <Route path="codes-promo" element={<PromoCodeManager permissions={permissions} urls={urls} />} />
                    <Route path="produits" element={<ProductsList permissions={permissions} urls={urls} />} />
                    <Route path="produits/nouveau" element={<ProductEditor permissions={permissions} urls={urls} />} />
                    <Route path="produits/:id/modifier" element={<ProductEditor permissions={permissions} urls={urls} />} />
                    <Route path="categories-produits" element={<ProductCategoryManager permissions={permissions} urls={urls} />} />
                    <Route path="medias" element={<MediaLibrary permissions={permissions} urls={urls} />} />
                    <Route path="faq" element={<FaqManager permissions={permissions} urls={urls} />} />
                    <Route path="support" element={<SupportManager permissions={permissions} urls={urls} />} />
                    <Route path="pages" element={<StaticPageManager permissions={permissions} urls={urls} />} />
                    <Route path="avis" element={<ReviewManager permissions={permissions} urls={urls} />} />
                    <Route path="livraison" element={<ShippingMethods permissions={permissions} urls={urls} />} />
                    <Route path="parametres" element={<Settings permissions={permissions} urls={urls} />} />
                    <Route path="securite" element={<TwoFactorSecurity urls={urls} />} />
                    <Route path="journal" element={<ActivityLog urls={urls} />} />
                </Route>
            </Routes>
        </BrowserRouter>
    );
}
