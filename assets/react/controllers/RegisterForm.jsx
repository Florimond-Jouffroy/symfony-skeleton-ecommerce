import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api, extractValidationErrors, getErrorMessage } from '../utils/api';
import { resolveUrl } from '../utils/url';

export default function RegisterForm({
    registerUrl    = '/api/auth/inscription',
    resendUrl      = '/api/auth/verification-email/renvoyer',
    loginUrl       = '/connexion',
    afterLoginUrl  = '/',
    checkoutContext = false,
    appName        = '',
}) {
    const [email, setEmail]                     = useState('');
    const [password, setPassword]               = useState('');
    const [passwordConfirm, setPasswordConfirm] = useState('');
    const [errors, setErrors]                   = useState([]);
    const [loading, setLoading]                 = useState(false);
    const [done, setDone]                       = useState(false);
    const [resent, setResent]                   = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors([]);
        setLoading(true);
        try {
            await api.post(resolveUrl(registerUrl), { email, password, passwordConfirm });
            setDone(true);
        } catch (err) {
            const violations = extractValidationErrors(err);
            setErrors(
                violations.length > 0
                    ? violations
                    : [getErrorMessage(err, "Erreur lors de l'inscription.")],
            );
        } finally {
            setLoading(false);
        }
    };

    const handleResend = async () => {
        setResent(false);
        try {
            await api.post(resolveUrl(resendUrl), { email });
            setResent(true);
        } catch {
            setResent(true);
        }
    };

    const decorativePanel = (
        <div style={{ viewTransitionName: 'auth-panel' }} className="hidden lg:flex lg:flex-col lg:items-center lg:justify-center bg-zinc-900 text-zinc-50 p-12">
            <blockquote className="max-w-sm space-y-4 text-center">
                <p className="text-xl font-medium leading-relaxed">
                    "Une interface simple et efficace pour gérer votre activité au quotidien."
                </p>
                {appName && <footer className="text-sm text-zinc-400">{appName}</footer>}
            </blockquote>
        </div>
    );

    if (done) {
        return (
            <div className="lg:grid lg:grid-cols-2 min-h-[calc(100vh-4rem)]">
                <div className="flex items-center justify-center px-6 py-16 lg:px-16 lg:py-24">
                    <div style={{ viewTransitionName: 'auth-form' }} className="w-full max-w-md">
                        <div className="rounded-2xl border bg-card shadow-sm p-10 space-y-7 text-center">
                            <div className="space-y-2">
                                <h1 className="text-2xl font-bold tracking-tight">Vérifiez votre boîte e-mail</h1>
                                <p className="text-sm text-muted-foreground">
                                    Un lien d'activation a été envoyé à{' '}
                                    <strong className="text-foreground">{email}</strong>.
                                    Cliquez dessus pour activer votre compte.
                                </p>
                            </div>

                            {checkoutContext && (
                                <div className="rounded-lg bg-muted/60 px-4 py-3 text-sm text-muted-foreground text-left space-y-1">
                                    <p className="font-medium text-foreground">Prochaine étape</p>
                                    <p>Une fois votre email vérifié, connectez-vous pour finaliser votre commande.</p>
                                </div>
                            )}

                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">Vous n'avez rien reçu ?</p>
                                {resent && (
                                    <p className="text-sm text-green-600">E-mail renvoyé !</p>
                                )}
                                <Button variant="outline" className="w-full" onClick={handleResend}>
                                    Renvoyer l'e-mail
                                </Button>
                            </div>

                            <a
                                href={resolveUrl(loginUrl)}
                                className="block text-sm font-medium text-foreground underline-offset-4 hover:underline"
                            >
                                {checkoutContext ? 'Se connecter et finaliser ma commande →' : 'Retour à la connexion'}
                            </a>
                        </div>
                    </div>
                </div>
                {decorativePanel}
            </div>
        );
    }

    return (
        <div className="lg:grid lg:grid-cols-2 min-h-[calc(100vh-4rem)]">
            {/* ── Colonne gauche : formulaire ── */}
            <div className="flex items-center justify-center px-6 py-16 lg:px-16 lg:py-24">
                <div style={{ viewTransitionName: 'auth-form' }} className="w-full max-w-md">
                    <div className="rounded-2xl border bg-card shadow-sm p-10 space-y-7">
                        <div className="space-y-1.5 text-center">
                            <h1 className="text-2xl font-bold tracking-tight">Inscription</h1>
                            <p className="text-sm text-muted-foreground">
                                {checkoutContext
                                    ? 'Créez un compte pour finaliser votre commande'
                                    : 'Créez votre compte pour accéder à la plateforme'}
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
                                <Label htmlFor="reg-email">Email</Label>
                                <Input
                                    id="reg-email"
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="votre@email.com"
                                    autoComplete="email"
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="reg-password">Mot de passe</Label>
                                <Input
                                    id="reg-password"
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    placeholder="8 caractères minimum"
                                    autoComplete="new-password"
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="reg-password-confirm">Confirmer le mot de passe</Label>
                                <Input
                                    id="reg-password-confirm"
                                    type="password"
                                    value={passwordConfirm}
                                    onChange={(e) => setPasswordConfirm(e.target.value)}
                                    placeholder="••••••••"
                                    autoComplete="new-password"
                                    required
                                />
                            </div>

                            {errors.length > 0 && (
                                <ul className="space-y-1">
                                    {errors.map((msg, i) => (
                                        <li key={i} className="text-sm text-destructive">{msg}</li>
                                    ))}
                                </ul>
                            )}

                            <Button type="submit" className="w-full" disabled={loading}>
                                {loading ? 'Inscription…' : "S'inscrire"}
                            </Button>
                        </form>

                        <p className="text-center text-sm text-muted-foreground">
                            Déjà un compte ?{' '}
                            <a
                                href={resolveUrl(loginUrl)}
                                className="font-medium text-foreground underline-offset-4 hover:underline"
                            >
                                Se connecter
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            {/* ── Colonne droite : panneau décoratif ── */}
            {decorativePanel}
        </div>
    );
}
