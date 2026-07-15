import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api, getErrorMessage } from '../utils/api';
import { resolveUrl } from '../utils/url';

export default function LoginForm({
    loginUrl          = '/api/auth/connexion',
    redirectUrl       = '/',
    forgotPasswordUrl = '/mot-de-passe-oublie',
    registerUrl       = '/inscription',
    checkoutContext   = false,
    appName           = '',
}) {
    const [email, setEmail]       = useState('');
    const [password, setPassword] = useState('');
    const [error, setError]       = useState('');
    const [loading, setLoading]   = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await api.post(resolveUrl(loginUrl), { email, password });
            window.location.href = redirectUrl;
        } catch (err) {
            setError(getErrorMessage(err, 'Identifiants incorrects.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="lg:grid lg:grid-cols-2 min-h-[calc(100vh-4rem)]">
            {/* ── Colonne gauche : formulaire ── */}
            <div className="flex items-center justify-center px-6 py-16 lg:px-16 lg:py-24">
                <div style={{ viewTransitionName: 'auth-form' }} className="w-full max-w-md">
                    <div className="rounded-2xl border bg-card shadow-sm p-10 space-y-7">
                        <div className="space-y-1.5 text-center">
                            <h1 className="text-2xl font-bold tracking-tight">Connexion</h1>
                            <p className="text-sm text-muted-foreground">
                                {checkoutContext
                                    ? 'Connectez-vous pour finaliser votre commande'
                                    : 'Entrez vos identifiants pour accéder à votre compte'}
                            </p>
                        </div>

                        {checkoutContext && (
                            <div className="flex items-center gap-2 rounded-lg bg-primary/8 border border-primary/20 px-4 py-3 text-sm text-primary">
                                <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.847-7.148a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                </svg>
                                Votre panier est sauvegardé et vous attend.
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="votre@email.com"
                                    autoComplete="email"
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="password">Mot de passe</Label>
                                    <a
                                        href={resolveUrl(forgotPasswordUrl)}
                                        className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                    >
                                        Mot de passe oublié ?
                                    </a>
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    placeholder="••••••••"
                                    autoComplete="current-password"
                                    required
                                />
                            </div>

                            {error && (
                                <p className="text-sm text-destructive">{error}</p>
                            )}

                            <Button type="submit" className="w-full" disabled={loading}>
                                {loading ? 'Connexion…' : 'Se connecter'}
                            </Button>
                        </form>

                        <p className="text-center text-sm text-muted-foreground">
                            Pas encore de compte ?{' '}
                            <a
                                href={resolveUrl(registerUrl)}
                                className="font-medium text-foreground underline-offset-4 hover:underline"
                            >
                                S'inscrire
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            {/* ── Colonne droite : panneau décoratif ── */}
            <div style={{ viewTransitionName: 'auth-panel' }} className="hidden lg:flex lg:flex-col lg:items-center lg:justify-center bg-zinc-900 text-zinc-50 p-12">
                <blockquote className="max-w-sm space-y-4 text-center">
                    <p className="text-xl font-medium leading-relaxed">
                        "Une interface simple et efficace pour gérer votre activité au quotidien."
                    </p>
                    {appName && <footer className="text-sm text-zinc-400">{appName}</footer>}
                </blockquote>
            </div>
        </div>
    );
}
