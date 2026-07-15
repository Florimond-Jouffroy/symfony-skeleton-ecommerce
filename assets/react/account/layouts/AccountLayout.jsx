import React from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import { LayoutDashboard, MessageCircle, Package, Star, User, LogOut } from 'lucide-react';

const navItems = [
    { to: '/tableau-de-bord', icon: LayoutDashboard, label: 'Tableau de bord' },
    { to: '/commandes',       icon: Package,         label: 'Mes commandes' },
    { to: '/avis',            icon: Star,            label: 'Mes avis' },
    { to: '/support',         icon: MessageCircle,   label: 'Mes demandes' },
    { to: '/profil',          icon: User,            label: 'Mon profil' },
];

export default function AccountLayout({ userEmail = '', logoutUrl = '/deconnexion' }) {
    return (
        <div className="mx-auto max-w-6xl px-4 sm:px-6 py-10">
            <div className="flex flex-col gap-8 md:flex-row md:gap-10">

                {/* ── Sidebar ── */}
                <aside className="shrink-0 md:w-56">
                    {/* User info */}
                    <div className="mb-6 flex items-center gap-3 rounded-xl border border-border bg-card p-4">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground text-sm font-semibold">
                            {(userEmail?.[0] ?? '?').toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-foreground truncate">Mon compte</p>
                            <p className="text-xs text-muted-foreground truncate">{userEmail}</p>
                        </div>
                    </div>

                    {/* Nav */}
                    <nav className="space-y-0.5">
                        {navItems.map(({ to, icon: Icon, label }) => (
                            <NavLink
                                key={to}
                                to={to}
                                className={({ isActive }) =>
                                    `flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm transition-colors ${
                                        isActive
                                            ? 'bg-primary text-primary-foreground font-medium'
                                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                                    }`
                                }
                            >
                                <Icon className="h-4 w-4 shrink-0" />
                                {label}
                            </NavLink>
                        ))}

                        <div className="pt-2 mt-2 border-t border-border">
                            <a
                                href={logoutUrl}
                                className="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                            >
                                <LogOut className="h-4 w-4 shrink-0" />
                                Se déconnecter
                            </a>
                        </div>
                    </nav>
                </aside>

                {/* ── Contenu ── */}
                <main className="flex-1 min-w-0">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
