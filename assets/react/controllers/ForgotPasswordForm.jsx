import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api, extractValidationErrors, getErrorMessage } from '../utils/api';
import { resolveUrl } from '../utils/url';

export default function ForgotPasswordForm({
    requestUrl = '/api/auth/reinitialisation-mot-de-passe/demande',
    confirmUrl = '/api/auth/reinitialisation-mot-de-passe/confirmation',
    loginUrl   = '/connexion',
    appName    = '',
}) {
    const [step, setStep]                             = useState('request');
    const [email, setEmail]                           = useState('');
    const [code, setCode]                             = useState('');
    const [newPassword, setNewPassword]               = useState('');
    const [newPasswordConfirm, setNewPasswordConfirm] = useState('');
    const [errors, setErrors]                         = useState([]);
    const [loading, setLoading]                       = useState(false);

    const handleRequest = async (e) => {
        e.preventDefault();
        setErrors([]);
        setLoading(true);
        try {
            await api.post(resolveUrl(requestUrl), { email });
            setStep('confirm');
        } catch (err) {
            const violations = extractValidationErrors(err);
            setErrors(violations.length > 0 ? violations : [getErrorMessage(err, 'Une erreur est survenue.')]);
        } finally {
            setLoading(false);
        }
    };

    const handleConfirm = async (e) => {
        e.preventDefault();
        setErrors([]);
        setLoading(true);
        try {
            await api.post(resolveUrl(confirmUrl), { email, code, newPassword, newPasswordConfirm });
            setStep('done');
        } catch (err) {
            const violations = extractValidationErrors(err);
            setErrors(violations.length > 0 ? violations : [getErrorMessage(err, 'Une erreur est survenue.')]);
        } finally {
            setLoading(false);
        }
    };

    const stepContent = () => {
        if (step === 'done') {
            return (
                <>
                    <div className="space-y-1.5 text-center">
                        <h1 className="text-2xl font-bold tracking-tight">Mot de passe réinitialisé</h1>
                        <p className="text-sm text-muted-foreground">
                            Votre mot de passe a été mis à jour avec succès.
                        </p>
                    </div>
                    <Button asChild className="w-full">
                        <a href={resolveUrl(loginUrl)}>Se connecter</a>
                    </Button>
                </>
            );
        }

        if (step === 'confirm') {
            return (
                <>
                    <div className="space-y-1.5 text-center">
                        <h1 className="text-2xl font-bold tracking-tight">Entrez votre code</h1>
                        <p className="text-sm text-muted-foreground">
                            Un code à 6 chiffres a été envoyé à{' '}
                            <strong className="text-foreground">{email}</strong>.
                            Il est valable 15 minutes.
                        </p>
                    </div>

                    <form onSubmit={handleConfirm} className="space-y-5">
                        <div className="space-y-2">
                            <Label htmlFor="fp-code">Code de vérification</Label>
                            <Input
                                id="fp-code"
                                type="text"
                                inputMode="numeric"
                                pattern="\d{6}"
                                maxLength={6}
                                value={code}
                                onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                                placeholder="000000"
                                autoComplete="one-time-code"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="fp-password">Nouveau mot de passe</Label>
                            <Input
                                id="fp-password"
                                type="password"
                                value={newPassword}
                                onChange={(e) => setNewPassword(e.target.value)}
                                placeholder="8 caractères minimum"
                                autoComplete="new-password"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="fp-password-confirm">Confirmer le mot de passe</Label>
                            <Input
                                id="fp-password-confirm"
                                type="password"
                                value={newPasswordConfirm}
                                onChange={(e) => setNewPasswordConfirm(e.target.value)}
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
                            {loading ? 'Validation…' : 'Réinitialiser le mot de passe'}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="w-full"
                            onClick={() => { setStep('request'); setErrors([]); }}
                        >
                            Changer d'adresse e-mail
                        </Button>
                    </form>
                </>
            );
        }

        return (
            <>
                <div className="space-y-1.5 text-center">
                    <h1 className="text-2xl font-bold tracking-tight">Mot de passe oublié</h1>
                    <p className="text-sm text-muted-foreground">
                        Entrez votre adresse e-mail pour recevoir un code de réinitialisation.
                    </p>
                </div>

                <form onSubmit={handleRequest} className="space-y-5">
                    <div className="space-y-2">
                        <Label htmlFor="fp-email">Email</Label>
                        <Input
                            id="fp-email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="votre@email.com"
                            autoComplete="email"
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
                        {loading ? 'Envoi…' : 'Envoyer le code'}
                    </Button>
                </form>

                <p className="text-center text-sm text-muted-foreground">
                    <a
                        href={resolveUrl(loginUrl)}
                        className="font-medium text-foreground underline-offset-4 hover:underline"
                    >
                        Retour à la connexion
                    </a>
                </p>
            </>
        );
    };

    return (
        <div className="lg:grid lg:grid-cols-2 min-h-[calc(100vh-4rem)]">
            {/* ── Colonne gauche : panneau décoratif ── */}
            <div style={{ viewTransitionName: 'auth-panel' }} className="hidden lg:flex lg:flex-col lg:items-center lg:justify-center bg-zinc-900 text-zinc-50 p-12">
                <blockquote className="max-w-sm space-y-4 text-center">
                    <p className="text-xl font-medium leading-relaxed">
                        "Une interface simple et efficace pour gérer votre activité au quotidien."
                    </p>
                    {appName && <footer className="text-sm text-zinc-400">{appName}</footer>}
                </blockquote>
            </div>

            {/* ── Colonne droite : formulaire ── */}
            <div className="flex items-center justify-center px-6 py-16 lg:px-16 lg:py-24">
                <div style={{ viewTransitionName: 'auth-form' }} className="w-full max-w-md">
                    <div className="rounded-2xl border bg-card shadow-sm p-10 space-y-7">
                        {stepContent()}
                    </div>
                </div>
            </div>
        </div>
    );
}
