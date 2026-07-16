import React, { useEffect, useRef, useState } from 'react';
import QRCode from 'qrcode';
import { api, getErrorMessage } from '../../utils/api';

export default function TwoFactorSecurity({ urls = {} }) {
    const [enabled, setEnabled]       = useState(null);
    const [loading, setLoading]       = useState(true);
    const [step, setStep]             = useState('idle'); // 'idle' | 'setup' | 'disable'
    const [setupData, setSetupData]   = useState(null); // { secret, uri }
    const [qrDataUrl, setQrDataUrl]   = useState('');
    const [code, setCode]             = useState('');
    const [error, setError]           = useState('');
    const [saving, setSaving]         = useState(false);
    const [feedback, setFeedback]     = useState('');
    const [trustedDays, setTrustedDays]       = useState(30);
    const [savingDays, setSavingDays]         = useState(false);
    const [daysInput, setDaysInput]           = useState(30);
    const [maxAttempts, setMaxAttempts]       = useState(5);
    const [windowMinutes, setWindowMinutes]   = useState(15);
    const [attemptsInput, setAttemptsInput]   = useState(5);
    const [windowInput, setWindowInput]       = useState(15);
    const [savingRate, setSavingRate]         = useState(false);
    const codeRef = useRef(null);

    useEffect(() => {
        Promise.all([
            api.get(urls.twoFactor ?? '/api/admin/securite/2fa'),
            api.get(urls.settings  ?? '/api/admin/parametres'),
        ])
            .then(([statusData, settingsData]) => {
                setEnabled(statusData.enabled);
                setTrustedDays(settingsData.twoFaRememberDays ?? 30);
                setDaysInput(settingsData.twoFaRememberDays ?? 30);
                setMaxAttempts(settingsData.rateLimitMaxAttempts ?? 5);
                setWindowMinutes(settingsData.rateLimitWindowMinutes ?? 15);
                setAttemptsInput(settingsData.rateLimitMaxAttempts ?? 5);
                setWindowInput(settingsData.rateLimitWindowMinutes ?? 15);
            })
            .catch(() => setError('Impossible de charger le statut 2FA.'))
            .finally(() => setLoading(false));
    }, []);

    const saveRateLimit = async () => {
        setSavingRate(true);
        try {
            const data = await api.patch(urls.settings ?? '/api/admin/parametres', {
                rateLimitMaxAttempts:   attemptsInput,
                rateLimitWindowMinutes: windowInput,
            });
            setMaxAttempts(data.rateLimitMaxAttempts);
            setWindowMinutes(data.rateLimitWindowMinutes);
            setAttemptsInput(data.rateLimitMaxAttempts);
            setWindowInput(data.rateLimitWindowMinutes);
            setFeedback('Paramètres enregistrés.');
        } catch {
            setError('Impossible de sauvegarder les paramètres.');
        } finally {
            setSavingRate(false);
        }
    };

    const saveTrustedDays = async () => {
        setSavingDays(true);
        try {
            const data = await api.patch(urls.settings ?? '/api/admin/parametres', { twoFaRememberDays: daysInput });
            setTrustedDays(data.twoFaRememberDays);
            setDaysInput(data.twoFaRememberDays);
            setFeedback('Durée enregistrée.');
        } catch {
            setError('Impossible de sauvegarder la durée.');
        } finally {
            setSavingDays(false);
        }
    };

    useEffect(() => {
        if (step === 'idle') { setCode(''); setError(''); setSetupData(null); setQrDataUrl(''); }
        if (step !== 'idle' && codeRef.current) setTimeout(() => codeRef.current?.focus(), 100);
    }, [step]);

    useEffect(() => {
        if (setupData?.uri) {
            QRCode.toDataURL(setupData.uri, { width: 200, margin: 2 })
                .then(url => setQrDataUrl(url));
        }
    }, [setupData]);

    const startSetup = async () => {
        setSaving(true);
        setError('');
        try {
            const data = await api.post(urls.twoFactorSetup ?? '/api/admin/securite/2fa/setup');
            setSetupData(data);
            setStep('setup');
        } catch (err) {
            setError(getErrorMessage(err));
        } finally {
            setSaving(false);
        }
    };

    const confirmEnable = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        try {
            await api.post(urls.twoFactorEnable ?? '/api/admin/securite/2fa/activer', { code });
            setEnabled(true);
            setStep('idle');
            setFeedback('La double authentification est maintenant activée.');
        } catch (err) {
            setError(getErrorMessage(err));
        } finally {
            setSaving(false);
        }
    };

    const confirmDisable = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        try {
            await api.post(urls.twoFactorDisable ?? '/api/admin/securite/2fa/desactiver', { code });
            setEnabled(false);
            setStep('idle');
            setFeedback('La double authentification a été désactivée.');
        } catch (err) {
            setError(getErrorMessage(err));
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="space-y-4 animate-pulse max-w-lg">
                <div className="h-8 w-48 rounded bg-muted" />
                <div className="h-40 rounded bg-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-8 max-w-lg">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Sécurité du compte</h2>
                <p className="text-sm text-muted-foreground mt-1">Double authentification (2FA) par application TOTP</p>
            </div>

            {feedback && (
                <p className="text-sm text-green-600">{feedback}</p>
            )}

            {/* ── Statut ── */}
            <div className="rounded-lg border">
                <div className="px-5 py-4 border-b bg-muted/40 flex items-center justify-between">
                    <div>
                        <h3 className="font-semibold text-sm">Double authentification</h3>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Protège votre compte même si votre mot de passe est compromis.
                        </p>
                    </div>
                    <span className={`text-xs font-medium px-2 py-1 rounded-full ${
                        enabled
                            ? 'bg-green-100 text-green-700'
                            : 'bg-muted text-muted-foreground'
                    }`}>
                        {enabled ? 'Activée' : 'Désactivée'}
                    </span>
                </div>

                <div className="p-5 space-y-4">
                    {step === 'idle' && (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {enabled
                                    ? 'La 2FA est active sur ce compte. À chaque connexion, un code à 6 chiffres vous sera demandé.'
                                    : 'Activez la 2FA pour sécuriser votre accès admin. Vous aurez besoin d\'une application comme Google Authenticator, Authy ou Bitwarden.'
                                }
                            </p>
                            {enabled ? (
                                <button
                                    type="button"
                                    onClick={() => setStep('disable')}
                                    className="rounded-lg border border-destructive px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/5 transition-colors"
                                >
                                    Désactiver la 2FA
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={startSetup}
                                    disabled={saving}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                                >
                                    {saving ? 'Chargement…' : 'Activer la 2FA'}
                                </button>
                            )}
                        </>
                    )}

                    {/* ── Setup : QR code + vérification ── */}
                    {step === 'setup' && setupData && (
                        <form onSubmit={confirmEnable} className="space-y-5">
                            <ol className="text-sm text-muted-foreground space-y-1 list-decimal list-inside">
                                <li>Ouvrez votre application d'authentification (Google Authenticator, Authy…)</li>
                                <li>Scannez le QR code ci-dessous ou saisissez la clé manuellement</li>
                                <li>Entrez le code à 6 chiffres affiché pour confirmer</li>
                            </ol>

                            {qrDataUrl && (
                                <div className="flex justify-center">
                                    <img src={qrDataUrl} alt="QR code 2FA" className="rounded-lg border p-2 bg-white" width={200} height={200} />
                                </div>
                            )}

                            <div className="rounded-md bg-muted/60 px-4 py-3 space-y-1">
                                <p className="text-xs font-medium text-muted-foreground">Clé manuelle :</p>
                                <code className="text-sm font-mono break-all">{setupData.secret}</code>
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Code de vérification</label>
                                <input
                                    ref={codeRef}
                                    type="text"
                                    inputMode="numeric"
                                    pattern="\d{6}"
                                    maxLength={6}
                                    value={code}
                                    onChange={e => setCode(e.target.value.replace(/\D/g, ''))}
                                    placeholder="123456"
                                    className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-ring"
                                    required
                                />
                            </div>

                            {error && <p className="text-sm text-destructive">{error}</p>}

                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setStep('idle')}
                                    className="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted transition-colors"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving || code.length !== 6}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                                >
                                    {saving ? 'Vérification…' : 'Confirmer et activer'}
                                </button>
                            </div>
                        </form>
                    )}

                    {/* ── Désactivation ── */}
                    {step === 'disable' && (
                        <form onSubmit={confirmDisable} className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Entrez un code valide de votre application pour confirmer la désactivation.
                            </p>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Code de confirmation</label>
                                <input
                                    ref={codeRef}
                                    type="text"
                                    inputMode="numeric"
                                    pattern="\d{6}"
                                    maxLength={6}
                                    value={code}
                                    onChange={e => setCode(e.target.value.replace(/\D/g, ''))}
                                    placeholder="123456"
                                    className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-ring"
                                    required
                                />
                            </div>

                            {error && <p className="text-sm text-destructive">{error}</p>}

                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setStep('idle')}
                                    className="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted transition-colors"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving || code.length !== 6}
                                    className="rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground hover:bg-destructive/90 disabled:opacity-50 transition-colors"
                                >
                                    {saving ? 'Désactivation…' : 'Désactiver la 2FA'}
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>

            {/* ── Durée de confiance ── */}
            <div className="rounded-lg border">
                <div className="px-5 py-4 border-b bg-muted/40">
                    <h3 className="font-semibold text-sm">Mémorisation des appareils</h3>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Durée pendant laquelle un appareil de confiance peut se connecter sans redemander le code
                    </p>
                </div>
                <div className="p-5 space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Après une 2FA réussie, l'utilisateur peut cocher "Se souvenir de cet appareil".
                        Le code ne sera alors plus demandé pendant la durée ci-dessous.
                        Mettre <strong>0</strong> pour désactiver cette option.
                    </p>
                    <div className="flex items-center gap-3">
                        <input
                            type="number"
                            min={0}
                            max={365}
                            value={daysInput}
                            onChange={e => setDaysInput(Math.max(0, Math.min(365, parseInt(e.target.value, 10) || 0)))}
                            className="w-24 rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                        <span className="text-sm text-muted-foreground">jours (0 = désactivé)</span>
                        <button
                            type="button"
                            onClick={saveTrustedDays}
                            disabled={savingDays || daysInput === trustedDays}
                            className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                        >
                            {savingDays ? 'Enregistrement…' : 'Enregistrer'}
                        </button>
                    </div>
                    {daysInput === 0 && (
                        <p className="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-md px-3 py-2">
                            La mémorisation est désactivée — le code 2FA sera demandé à chaque connexion.
                        </p>
                    )}
                </div>
            </div>

            {/* ── Rate limiting ── */}
            <div className="rounded-lg border">
                <div className="px-5 py-4 border-b bg-muted/40">
                    <h3 className="font-semibold text-sm">Protection brute-force</h3>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Limite le nombre d'échecs sur les endpoints de connexion, inscription et réinitialisation
                    </p>
                </div>
                <div className="p-5 space-y-5">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <label className="text-sm font-medium">Tentatives max</label>
                            <p className="text-xs text-muted-foreground">0 = désactivé</p>
                            <input
                                type="number"
                                min={0}
                                max={100}
                                value={attemptsInput}
                                onChange={e => setAttemptsInput(Math.max(0, Math.min(100, parseInt(e.target.value, 10) || 0)))}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-sm font-medium">Fenêtre (minutes)</label>
                            <p className="text-xs text-muted-foreground">Durée de blocage après dépassement</p>
                            <input
                                type="number"
                                min={1}
                                max={1440}
                                value={windowInput}
                                onChange={e => setWindowInput(Math.max(1, Math.min(1440, parseInt(e.target.value, 10) || 1)))}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    {attemptsInput === 0 && (
                        <p className="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-md px-3 py-2">
                            La protection brute-force est <strong>désactivée</strong>. Les endpoints d'authentification sont sans limite.
                        </p>
                    )}

                    {attemptsInput > 0 && (
                        <p className="text-xs text-muted-foreground">
                            Après <strong>{attemptsInput} échec{attemptsInput > 1 ? 's' : ''}</strong>, l'IP est bloquée pendant <strong>{windowInput} minute{windowInput > 1 ? 's' : ''}</strong>.
                            Le compteur se réinitialise après une connexion réussie.
                        </p>
                    )}

                    <button
                        type="button"
                        onClick={saveRateLimit}
                        disabled={savingRate || (attemptsInput === maxAttempts && windowInput === windowMinutes)}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {savingRate ? 'Enregistrement…' : 'Enregistrer'}
                    </button>
                </div>
            </div>

            {/* ── Info applications compatibles ── */}
            <div className="rounded-md bg-muted/60 px-4 py-3 text-xs text-muted-foreground space-y-1">
                <p className="font-medium">Applications compatibles :</p>
                <p>Google Authenticator · Authy · Microsoft Authenticator · Bitwarden · 1Password</p>
            </div>
        </div>
    );
}
