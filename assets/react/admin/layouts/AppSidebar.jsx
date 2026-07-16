import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { BookOpen, ChevronRight, ChevronsUpDown, ClipboardList, FileText, HelpCircle, Image, LayoutDashboard, LogOut, MessageCircle, Package, Receipt, Settings, ShieldCheck, ShoppingCart, Star, Tag, Ticket, Truck, Users } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarRail,
} from '@/components/ui/sidebar';

const NAV_ITEMS = [
    { to: '/dashboard',    icon: LayoutDashboard, label: 'Dashboard',           permission: null },
    { to: '/utilisateurs', icon: Users,           label: 'Utilisateurs',        permission: 'canViewUsers' },
    { to: '/medias',       icon: Image,           label: 'Médiathèque',         permission: 'canViewMedia' },
    { to: '/faq',          icon: HelpCircle,      label: 'FAQ',                 permission: 'canViewFaq' },
    { to: '/support',      icon: MessageCircle,   label: 'Support',             permission: 'canViewSupport' },
    { to: '/pages',        icon: BookOpen,        label: 'Pages statiques',     permission: 'canViewPages' },
    { to: '/avis',         icon: Star,            label: 'Avis clients',        permission: 'canViewReviews' },
    { to: '/journal',      icon: ClipboardList,   label: "Journal d'activité",  permission: 'canViewActivityLog' },
];

const BLOG_ITEMS = [
    { to: '/articles',   icon: FileText, label: 'Articles',    permission: 'canViewArticles' },
    { to: '/categories', icon: Tag,      label: 'Catégories',  permission: 'canViewCategories' },
];

const SHOP_ITEMS = [
    { to: '/commandes',           icon: ShoppingCart, label: 'Commandes',   permission: 'canViewOrders' },
    { to: '/factures',            icon: Receipt,      label: 'Factures',    permission: 'canViewInvoices' },
    { to: '/codes-promo',         icon: Ticket,       label: 'Codes promo', permission: 'canViewPromoCodes' },
    { to: '/produits',            icon: Package,      label: 'Produits',    permission: 'canViewProducts' },
    { to: '/categories-produits', icon: Tag,          label: 'Catégories',  permission: 'canViewProductCategories' },
    { to: '/livraison',           icon: Truck,        label: 'Livraison',   permission: 'canViewShipping' },
    { to: '/parametres',          icon: Settings,     label: 'Paramètres',  permission: 'canViewSettings' },
    { to: '/securite',            icon: ShieldCheck,  label: 'Sécurité',    permission: null },
];

function getInitials(email) {
    if (!email) return '?';
    const local = email.split('@')[0];
    const parts = local.split(/[._-]/);
    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : local.slice(0, 2).toUpperCase();
}

function CollapsibleNavGroup({ label, icon: Icon, groupKey, isActive, items, pathname }) {
    return (
        <SidebarMenuItem>
            <Collapsible defaultOpen={isActive} className={`group/${groupKey} w-full`}>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton tooltip={label} isActive={isActive}>
                        <Icon />
                        <span>{label}</span>
                        <ChevronRight className={`ml-auto size-4 transition-transform duration-200 group-data-[state=open]/${groupKey}:rotate-90`} />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {items.map(({ to, label: itemLabel }) => {
                            const active = pathname === to || pathname.startsWith(to + '/');
                            return (
                                <SidebarMenuSubItem key={to}>
                                    <SidebarMenuSubButton asChild isActive={active}>
                                        <NavLink to={to}>{itemLabel}</NavLink>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            );
                        })}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </Collapsible>
        </SidebarMenuItem>
    );
}

function NavItem({ to, icon: Icon, label }) {
    const { pathname } = useLocation();
    const isActive = pathname === to || pathname.startsWith(to + '/');

    return (
        <SidebarMenuItem>
            <SidebarMenuButton asChild isActive={isActive} tooltip={label}>
                <NavLink to={to}>
                    <Icon />
                    <span>{label}</span>
                </NavLink>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

export default function AppSidebar({ userEmail = '', logoutUrl = '/deconnexion', permissions = {}, appName = 'Admin' }) {
    const { pathname } = useLocation();

    const allowed = (permission) => permission === null || permissions[permission] !== false;

    const navItems  = NAV_ITEMS.filter(i => allowed(i.permission));
    const blogItems = BLOG_ITEMS.filter(i => allowed(i.permission));
    const shopItems = SHOP_ITEMS.filter(i => allowed(i.permission));

    const isBlogActive = pathname.startsWith('/articles') || pathname.startsWith('/categories');
    const isShopActive = pathname.startsWith('/commandes') || pathname.startsWith('/factures') || pathname.startsWith('/produits') || pathname.startsWith('/categories-produits') || pathname.startsWith('/livraison') || pathname.startsWith('/parametres');

    return (
        <Sidebar collapsible="icon">
            {/* ── Header : logo ── */}
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild tooltip={appName}>
                            <a href="/admin">
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground font-bold text-sm">
                                    {appName.charAt(0).toUpperCase()}
                                </div>
                                <div className="flex flex-col gap-0.5 leading-none">
                                    <span className="font-semibold">{appName}</span>
                                    <span className="text-xs text-sidebar-foreground/70">Administration</span>
                                </div>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            {/* ── Content : navigation ── */}
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel>Navigation</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {navItems.map((item) => (
                                <NavItem key={item.to} {...item} />
                            ))}

                            {blogItems.length > 0 && (
                                <CollapsibleNavGroup
                                    label="Blog"
                                    icon={FileText}
                                    groupKey="blog"
                                    isActive={isBlogActive}
                                    items={blogItems}
                                    pathname={pathname}
                                />
                            )}

                            {shopItems.length > 0 && (
                                <CollapsibleNavGroup
                                    label="Boutique"
                                    icon={ShoppingCart}
                                    groupKey="shop"
                                    isActive={isShopActive}
                                    items={shopItems}
                                    pathname={pathname}
                                />
                            )}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            {/* ── Footer : utilisateur ── */}
            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton
                                    size="lg"
                                    tooltip={userEmail}
                                    className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                >
                                    <Avatar className="size-8 rounded-lg shrink-0">
                                        <AvatarFallback className="rounded-lg text-xs">
                                            {getInitials(userEmail)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex flex-col gap-0.5 leading-none text-left">
                                        <span className="truncate text-sm font-medium">
                                            {userEmail?.split('@')[0]}
                                        </span>
                                        <span className="truncate text-xs text-sidebar-foreground/70">
                                            {userEmail}
                                        </span>
                                    </div>
                                    <ChevronsUpDown className="ml-auto size-4 shrink-0" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                side="top"
                                align="end"
                                sideOffset={4}
                                className="w-56"
                            >
                                <DropdownMenuLabel className="font-normal">
                                    <p className="text-xs text-muted-foreground truncate">{userEmail}</p>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <a href={logoutUrl} className="flex items-center gap-2 text-destructive focus:text-destructive">
                                        <LogOut className="size-4" />
                                        Se déconnecter
                                    </a>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>

            <SidebarRail />
        </Sidebar>
    );
}
