import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    BadgeCheck,
    KeyRound,
    MailPlus,
    MoreHorizontal,
    Search,
    Shield,
    ShieldOff,
    Trash2,
    UserCheck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { api, getErrorMessage } from '../../utils/api';

const PAGE_SIZE = 20;

const currentUserEmail = document.getElementById('admin-root')?.dataset.userEmail ?? '';


/**
 * Configuration des actions nécessitant une confirmation.
 * Chaque entrée décrit le dialog et l'appel API associé.
 */
const CONFIRM_ACTIONS = {
    resetPassword: {
        title: 'Réinitialiser le mot de passe',
        description: (user) => (
            <>Un code de réinitialisation sera envoyé à <strong>{user.email}</strong>. L'utilisateur pourra définir un nouveau mot de passe via la page « Mot de passe oublié ».</>
        ),
        confirmLabel: 'Envoyer le code',
        variant: 'default',
        run: (user) => api.post(`/api/admin/utilisateurs/${user.id}/reinitialiser-mot-de-passe`),
        successMessage: (user) => `Code de réinitialisation envoyé à ${user.email}.`,
    },
    promote: {
        title: 'Promouvoir administrateur',
        description: (user) => (
            <><strong>{user.email}</strong> aura accès à l'administration et à toutes les actions sur les utilisateurs.</>
        ),
        confirmLabel: 'Promouvoir',
        variant: 'default',
        run: (user) => api.put(`/api/admin/utilisateurs/${user.id}/roles`, { roles: ['ROLE_ADMIN'] }),
        successMessage: (user) => `${user.email} est maintenant administrateur.`,
    },
    demote: {
        title: 'Retirer les droits administrateur',
        description: (user) => (
            <><strong>{user.email}</strong> n'aura plus accès à l'administration.</>
        ),
        confirmLabel: 'Retirer les droits',
        variant: 'default',
        run: (user) => api.put(`/api/admin/utilisateurs/${user.id}/roles`, { roles: [] }),
        successMessage: (user) => `${user.email} n'est plus administrateur.`,
    },
    delete: {
        title: 'Supprimer le compte',
        description: (user) => (
            <>Le compte <strong>{user.email}</strong> sera définitivement supprimé. Cette action est irréversible.</>
        ),
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        run: (user) => api.delete(`/api/admin/utilisateurs/${user.id}`),
        successMessage: (user) => `Le compte ${user.email} a été supprimé.`,
    },
};

export default function UsersList({ permissions = {}, urls = {} }) {
    const [users, setUsers]           = useState([]);
    const [total, setTotal]           = useState(0);
    const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: PAGE_SIZE });
    const [search, setSearch]         = useState('');
    const [query, setQuery]           = useState('');
    const [loading, setLoading]       = useState(true);  // premier chargement → squelettes
    const [refreshing, setRefreshing] = useState(false); // rechargements → lignes en fondu
    const isFirstLoad                 = useRef(true);
    const [feedback, setFeedback]     = useState(null); // { type: 'success' | 'error', message }
    const [confirm, setConfirm]       = useState(null); // { action, user }
    const [acting, setActing]         = useState(false);

    const pageCount = Math.max(1, Math.ceil(total / pagination.pageSize));

    // Recherche live : déclenche la requête 350 ms après la dernière frappe
    useEffect(() => {
        const timer = setTimeout(() => {
            setQuery(search.trim());
            setPagination((p) => (p.pageIndex === 0 ? p : { ...p, pageIndex: 0 }));
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    const fetchUsers = useCallback(async () => {
        if (isFirstLoad.current) {
            setLoading(true);
        } else {
            setRefreshing(true);
        }
        try {
            const data = await api.get(urls.users ?? '/api/admin/utilisateurs', {
                q: query,
                page: pagination.pageIndex + 1,
                pageSize: pagination.pageSize,
            });
            // Page au-delà de la dernière (ex : changement de taille de page) : on recale
            if (data.items.length === 0 && data.total > 0 && pagination.pageIndex > 0) {
                setPagination((p) => ({ ...p, pageIndex: 0 }));
                return;
            }
            setUsers(data.items);
            setTotal(data.total);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Impossible de charger les utilisateurs.') });
        } finally {
            isFirstLoad.current = false;
            setLoading(false);
            setRefreshing(false);
        }
    }, [query, pagination]);

    useEffect(() => { fetchUsers(); }, [fetchUsers]);

    /** Actions immédiates, sans dialog de confirmation. */
    const runDirect = useCallback(async (promise, successMessage) => {
        setFeedback(null);
        try {
            await promise;
            setFeedback({ type: 'success', message: successMessage });
            fetchUsers();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        }
    }, [fetchUsers]);

    const handleConfirm = async () => {
        const { action, user } = confirm;
        const config = CONFIRM_ACTIONS[action];

        setActing(true);
        setFeedback(null);
        try {
            await config.run(user);
            setFeedback({ type: 'success', message: config.successMessage(user) });
            setConfirm(null);
            fetchUsers();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
            setConfirm(null);
        } finally {
            setActing(false);
        }
    };

    const columns = useMemo(() => [
        {
            accessorKey: 'id',
            header: 'ID',
            meta: { headerClassName: 'w-16', cellClassName: 'text-muted-foreground' },
        },
        {
            accessorKey: 'email',
            header: 'Email',
            cell: ({ row }) => (
                <span className="font-medium">
                    {row.original.email}
                    {row.original.email === currentUserEmail && (
                        <span className="ml-2 text-xs font-normal text-muted-foreground">(vous)</span>
                    )}
                </span>
            ),
        },
        {
            id: 'role',
            header: 'Rôle',
            cell: ({ row }) => {
                const isAdmin = row.original.roles.includes('ROLE_ADMIN');
                return (
                    <Badge variant={isAdmin ? 'default' : 'secondary'}>
                        {isAdmin ? 'Admin' : 'Utilisateur'}
                    </Badge>
                );
            },
        },
        {
            id: 'status',
            header: 'Statut',
            cell: ({ row }) => (
                <Badge
                    variant="outline"
                    className={row.original.isVerified
                        ? 'text-green-600 border-green-600/40'
                        : 'text-amber-600 border-amber-600/40'}
                >
                    {row.original.isVerified ? 'Vérifié' : 'En attente'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { headerClassName: 'w-16', cellClassName: 'text-right' },
            cell: ({ row }) => {
                const user    = row.original;
                const isAdmin = user.roles.includes('ROLE_ADMIN');
                const isSelf  = user.email === currentUserEmail;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="size-8">
                                <MoreHorizontal className="size-4" />
                                <span className="sr-only">Actions pour {user.email}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-64">
                            {permissions.canResetUserPassword && (
                                <DropdownMenuItem onClick={() => setConfirm({ action: 'resetPassword', user })}>
                                    <KeyRound className="size-4" />
                                    Réinitialiser le mot de passe
                                </DropdownMenuItem>
                            )}

                            {!user.isVerified && permissions.canVerifyUser && (
                                <DropdownMenuItem
                                    onClick={() => runDirect(
                                        api.post(`/api/admin/utilisateurs/${user.id}/verifier`),
                                        `Le compte ${user.email} est maintenant vérifié.`,
                                    )}
                                >
                                    <BadgeCheck className="size-4" />
                                    Marquer comme vérifié
                                </DropdownMenuItem>
                            )}

                            {!user.isVerified && permissions.canResendVerification && (
                                <DropdownMenuItem
                                    onClick={() => runDirect(
                                        api.post(`/api/admin/utilisateurs/${user.id}/renvoyer-verification`),
                                        `E-mail de vérification renvoyé à ${user.email}.`,
                                    )}
                                >
                                    <MailPlus className="size-4" />
                                    Renvoyer l'e-mail de vérification
                                </DropdownMenuItem>
                            )}

                            {!isSelf && permissions.canEditUserRoles && (
                                <DropdownMenuItem onClick={() => setConfirm({ action: isAdmin ? 'demote' : 'promote', user })}>
                                    {isAdmin ? <ShieldOff className="size-4" /> : <Shield className="size-4" />}
                                    {isAdmin ? 'Retirer les droits admin' : 'Promouvoir administrateur'}
                                </DropdownMenuItem>
                            )}

                            {!isSelf && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        window.location.href = `/?_switch_user=${encodeURIComponent(user.email)}`;
                                    }}
                                >
                                    <UserCheck className="size-4" />
                                    Se connecter en tant que
                                </DropdownMenuItem>
                            )}

                            {!isSelf && permissions.canDeleteUser && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={() => setConfirm({ action: 'delete', user })}
                                    >
                                        <Trash2 className="size-4" />
                                        Supprimer le compte
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ], [runDirect]);

    const confirmConfig = confirm ? CONFIRM_ACTIONS[confirm.action] : null;

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Utilisateurs</h2>
                <p className="text-muted-foreground">Gestion des comptes utilisateurs.</p>
            </div>

            <div className="relative w-full max-w-sm">
                <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Rechercher par email…"
                    className="pl-8"
                />
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            <DataTable
                columns={columns}
                data={users}
                pageCount={pageCount}
                totalItems={total}
                pagination={pagination}
                onPaginationChange={setPagination}
                loading={loading}
                refreshing={refreshing}
                emptyMessage={query ? `Aucun utilisateur pour « ${query} ».` : 'Aucun utilisateur à afficher.'}
            />

            <Dialog open={confirm !== null} onOpenChange={(open) => !open && setConfirm(null)}>
                {confirmConfig && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{confirmConfig.title}</DialogTitle>
                            <DialogDescription>{confirmConfig.description(confirm.user)}</DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setConfirm(null)} disabled={acting}>
                                Annuler
                            </Button>
                            <Button variant={confirmConfig.variant} onClick={handleConfirm} disabled={acting}>
                                {acting ? 'En cours…' : confirmConfig.confirmLabel}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>
        </div>
    );
}
