import React, { useEffect, useRef, useState } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Bell, Globe, ShoppingCart, MessageCircle, RotateCcw, Star } from 'lucide-react';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { api } from '../../utils/api';
import AppSidebar from './AppSidebar';

const pageTitles = {
    '/dashboard':          'Dashboard',
    '/utilisateurs':       'Utilisateurs',
    '/articles':           'Articles',
    '/medias':             'Médiathèque',
    '/categories':         'Catégories',
    '/commandes':          'Commandes',
    '/factures':           'Factures',
    '/produits':           'Produits',
    '/categories-produits': 'Catégories produits',
    '/livraison':          'Livraison',
    '/codes-promo':        'Codes promo',
    '/faq':                'FAQ',
    '/support':            'Support',
    '/pages':              'Pages statiques',
    '/avis':               'Avis clients',
    '/parametres':         'Paramètres',
};

function getTitle(pathname) {
    if (pageTitles[pathname]) return pageTitles[pathname];
    if (/^\/articles\/\d+\/modifier$/.test(pathname)) return "Modifier l'article";
    if (/^\/produits\/\d+\/modifier$/.test(pathname)) return 'Modifier le produit';
    if (/^\/commandes\/\d+$/.test(pathname)) return 'Détail commande';
    return 'Administration';
}

function NotificationBell({ notificationsUrl, permissions = {} }) {
    const navigate = useNavigate();
    const [counts, setCounts]       = useState({ pendingOrders: 0, pendingReturns: 0, openTickets: 0, pendingReviews: 0 });
    const [open, setOpen]           = useState(false);
    const ref                       = useRef(null);
    const total = counts.pendingOrders + counts.pendingReturns + counts.openTickets + counts.pendingReviews;

    const fetchCounts = () => {
        if (!notificationsUrl) return;
        api.get(notificationsUrl).then(data => setCounts(data)).catch(() => {});
    };

    useEffect(() => {
        fetchCounts();
        const id = setInterval(fetchCounts, 30_000);
        return () => clearInterval(id);
    }, [notificationsUrl]);

    useEffect(() => {
        if (!open) return;
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    const go = (path) => { setOpen(false); navigate(path); };

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen(v => !v)}
                className="relative flex items-center rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                aria-label="Notifications"
            >
                <Bell className="size-4" />
                {total > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-white leading-none">
                        {total > 99 ? '99+' : total}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-full z-50 mt-2 w-64 rounded-lg border bg-popover shadow-lg">
                    <p className="border-b px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                        Notifications
                    </p>
                    {permissions.canViewOrders !== false && (
                        <button
                            type="button"
                            onClick={() => go('/commandes?status=pending')}
                            className="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-accent transition-colors"
                        >
                            <ShoppingCart className="size-4 shrink-0 text-muted-foreground" />
                            <span className="flex-1 text-left">Commandes en attente</span>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${counts.pendingOrders > 0 ? 'bg-orange-100 text-orange-700' : 'bg-muted text-muted-foreground'}`}>
                                {counts.pendingOrders}
                            </span>
                        </button>
                    )}
                    {permissions.canViewReturns !== false && (
                        <button
                            type="button"
                            onClick={() => go('/retours')}
                            className="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-accent transition-colors"
                        >
                            <RotateCcw className="size-4 shrink-0 text-muted-foreground" />
                            <span className="flex-1 text-left">Retours à traiter</span>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${counts.pendingReturns > 0 ? 'bg-orange-100 text-orange-700' : 'bg-muted text-muted-foreground'}`}>
                                {counts.pendingReturns}
                            </span>
                        </button>
                    )}
                    {permissions.canViewSupport !== false && (
                        <button
                            type="button"
                            onClick={() => go('/support?status=open')}
                            className="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-accent transition-colors"
                        >
                            <MessageCircle className="size-4 shrink-0 text-muted-foreground" />
                            <span className="flex-1 text-left">Tickets ouverts</span>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${counts.openTickets > 0 ? 'bg-blue-100 text-blue-700' : 'bg-muted text-muted-foreground'}`}>
                                {counts.openTickets}
                            </span>
                        </button>
                    )}
                    {permissions.canViewReviews !== false && (
                        <button
                            type="button"
                            onClick={() => go('/avis?approved=false')}
                            className="flex w-full items-center gap-3 px-4 py-3 text-sm hover:bg-accent transition-colors rounded-b-lg"
                        >
                            <Star className="size-4 shrink-0 text-muted-foreground" />
                            <span className="flex-1 text-left">Avis en attente</span>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${counts.pendingReviews > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-muted text-muted-foreground'}`}>
                                {counts.pendingReviews}
                            </span>
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

export default function AdminLayout({ userEmail = '', logoutUrl = '/deconnexion', notificationsUrl = null, permissions = {}, appName = 'Admin' }) {
    const { pathname } = useLocation();
    const title = getTitle(pathname);

    return (
        <TooltipProvider>
            <SidebarProvider>
                <AppSidebar userEmail={userEmail} logoutUrl={logoutUrl} permissions={permissions} appName={appName} />

                <SidebarInset>
                    <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b bg-background px-4">
                        <SidebarTrigger className="-ml-1" />
                        <span className="ml-2 text-sm font-medium text-foreground">{title}</span>

                        <div className="ml-auto flex items-center gap-2">
                            <NotificationBell notificationsUrl={notificationsUrl} permissions={permissions} />
                            <a
                                href="/"
                                className="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                            >
                                <Globe className="size-4" />
                                <span>Voir le site</span>
                            </a>
                        </div>
                    </header>

                    {/* key sur le pathname : relance l'animation d'entrée à chaque changement de page */}
                    <div
                        key={pathname}
                        className="flex flex-1 flex-col gap-4 p-6 animate-in fade-in slide-in-from-bottom-2 duration-300"
                    >
                        <Outlet />
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}
