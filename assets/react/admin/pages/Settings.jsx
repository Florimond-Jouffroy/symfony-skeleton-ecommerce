import React, { useEffect, useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { api, getErrorMessage } from '../../utils/api';

const TAX_RATE_OPTIONS = [
    { value: 20,  label: '20% — Taux normal (vêtements, électronique, etc.)' },
    { value: 10,  label: '10% — Taux intermédiaire (restauration, certains services)' },
    { value: 5.5, label: '5,5% — Taux réduit (alimentation, livres, abonnements)' },
    { value: 2.1, label: '2,1% — Taux super-réduit (médicaments, presse)' },
    { value: 0,   label: '0% — Exonéré (exportations, DOM-TOM)' },
];

const TABS = [
    { id: 'facturation', label: 'Facturation' },
    { id: 'paiements',   label: 'Paiements' },
];

export default function Settings({ urls = {}, permissions = {} }) {
    const [settings, setSettings] = useState(null);
    const [loading, setLoading]   = useState(true);
    const [saving, setSaving]     = useState(false);
    const [feedback, setFeedback] = useState(null);
    const [activeTab, setActiveTab] = useState('facturation');

    useEffect(() => {
        api.get(urls.settings ?? '/api/admin/parametres')
            .then(setSettings)
            .catch(() => setFeedback({ type: 'error', message: 'Impossible de charger les paramètres.' }))
            .finally(() => setLoading(false));
    }, []);

    const updateSetting = async (patch) => {
        setSaving(true);
        setFeedback(null);
        try {
            const data = await api.patch(urls.settings ?? '/api/admin/parametres', patch);
            setSettings(data);
            setFeedback({ type: 'success', message: 'Paramètre enregistré.' });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="space-y-4 animate-pulse">
                <div className="h-8 w-64 rounded bg-muted" />
                <div className="h-40 rounded bg-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Paramètres</h2>
                <p className="text-sm text-muted-foreground mt-1">Configuration générale de la boutique</p>
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            {/* ── Toggles rapides ── */}
            <div className="flex flex-wrap gap-3">
                <Toggle
                    label="Mode maintenance"
                    description="Les visiteurs voient une page de maintenance. Les admins accèdent normalement."
                    checked={!!settings?.maintenanceMode}
                    danger
                    disabled={saving || permissions.canEditSettings === false}
                    onChange={() => updateSetting({ maintenanceMode: !settings?.maintenanceMode })}
                />
                <Toggle
                    label="Boutique active"
                    description={<>Les pages <code>/boutique/*</code> sont accessibles au public.</>}
                    checked={!!settings?.shopEnabled}
                    disabled={saving || permissions.canEditSettings === false}
                    onChange={() => updateSetting({ shopEnabled: !settings?.shopEnabled })}
                />
            </div>

            {/* ── Barre d'onglets ── */}
            <div className="border-b">
                <nav className="flex gap-1" aria-label="Sections des paramètres">
                    {TABS.map(tab => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setActiveTab(tab.id)}
                            className={`px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px ${
                                activeTab === tab.id
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground/40'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>


            {/* ── Onglet Facturation ── */}
            {activeTab === 'facturation' && (
                <div className="rounded-lg border max-w-2xl">
                    <div className="px-5 py-4 border-b bg-muted/40">
                        <h3 className="font-semibold text-sm">Facturation</h3>
                        <p className="text-xs text-muted-foreground mt-0.5">Génération des factures et TVA par défaut</p>
                    </div>
                    <div className="p-5 space-y-6">

                        {/* Déclencheur */}
                        <div className="space-y-3">
                            <div>
                                <p className="text-sm font-medium">Déclencheur de la facture</p>
                                <p className="text-xs text-muted-foreground mt-0.5">Quand la facture est générée automatiquement</p>
                            </div>
                            <div className="space-y-2">
                                {[
                                    {
                                        value: 'on_order',
                                        label: 'À la création de la commande',
                                        description: 'Facture générée dès la validation du panier (statut En attente). Idéal pour les paiements immédiats.',
                                    },
                                    {
                                        value: 'on_confirm',
                                        label: "Lors de la confirmation par l'admin",
                                        description: "Facture générée quand l'admin passe la commande en Confirmée. Recommandé pour les validations manuelles.",
                                    },
                                ].map(({ value, label, description }) => {
                                    const active = settings?.invoiceTrigger === value;
                                    return (
                                        <button
                                            key={value}
                                            type="button"
                                            disabled={saving || permissions.canEditSettings === false}
                                            onClick={() => !active && permissions.canEditSettings !== false && updateSetting({ invoiceTrigger: value })}
                                            className={`w-full text-left rounded-lg border-2 p-4 transition-colors ${
                                                active ? 'border-primary bg-primary/5' : 'border-border hover:border-muted-foreground/40'
                                            } ${saving ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}
                                        >
                                            <div className="flex items-start gap-3">
                                                <div className={`mt-0.5 size-4 rounded-full border-2 flex items-center justify-center shrink-0 ${
                                                    active ? 'border-primary' : 'border-muted-foreground/40'
                                                }`}>
                                                    {active && <div className="size-2 rounded-full bg-primary" />}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium">{label}</p>
                                                    <p className="text-xs text-muted-foreground mt-0.5">{description}</p>
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="border-t" />

                        {/* Taux de TVA par défaut */}
                        <div className="space-y-3">
                            <div>
                                <p className="text-sm font-medium">Taux de TVA par défaut</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Appliqué aux produits dont la catégorie n'a pas de taux spécifique, et aux frais de livraison.
                                </p>
                            </div>
                            <div className="space-y-2">
                                {TAX_RATE_OPTIONS.map(({ value, label }) => {
                                    const active = settings?.defaultTaxRate === value;
                                    return (
                                        <button
                                            key={value}
                                            type="button"
                                            disabled={saving || permissions.canEditSettings === false}
                                            onClick={() => !active && permissions.canEditSettings !== false && updateSetting({ defaultTaxRate: value })}
                                            className={`w-full text-left rounded-lg border-2 px-4 py-3 transition-colors ${
                                                active ? 'border-primary bg-primary/5' : 'border-border hover:border-muted-foreground/40'
                                            } ${saving ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className={`size-4 rounded-full border-2 flex items-center justify-center shrink-0 ${
                                                    active ? 'border-primary' : 'border-muted-foreground/40'
                                                }`}>
                                                    {active && <div className="size-2 rounded-full bg-primary" />}
                                                </div>
                                                <span className="text-sm">{label}</span>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="rounded-md bg-muted/60 px-4 py-3 text-xs text-muted-foreground">
                            <strong>Taux par catégorie</strong> — Pour appliquer un taux différent sur certains produits,
                            configurez-le directement sur la catégorie dans{' '}
                            <strong>Boutique → Catégories</strong>. Il prend le dessus sur le taux par défaut.
                            <br /><br />
                            <strong>Informations entreprise</strong> — Nom, adresse, SIRET visibles sur les factures PDF.
                            À modifier dans <code>config/services.yaml</code> sous la clé <code>app.company</code>.
                        </div>
                    </div>
                </div>
            )}

            {/* ── Onglet Paiements ── */}
            {activeTab === 'paiements' && (
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <StripeSection settings={settings} saving={saving} permissions={permissions} updateSetting={updateSetting} />
                    <PayPalSection settings={settings} saving={saving} permissions={permissions} updateSetting={updateSetting} />
                    <MollieSection settings={settings} saving={saving} permissions={permissions} updateSetting={updateSetting} />
                </div>
            )}

        </div>
    );
}

function Toggle({ label, description, checked, onChange, disabled, danger = false }) {
    return (
        <div className={`flex items-center justify-between gap-6 rounded-lg border px-4 py-3 min-w-64 ${checked && danger ? 'border-destructive/40 bg-destructive/5' : 'bg-card'}`}>
            <div>
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-muted-foreground mt-0.5">{description}</p>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                disabled={disabled}
                onClick={onChange}
                className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                    checked ? (danger ? 'bg-destructive' : 'bg-primary') : 'bg-muted'
                } ${disabled ? 'opacity-60 cursor-not-allowed' : ''}`}
            >
                <span className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transform transition-transform ${checked ? 'translate-x-5' : 'translate-x-0'}`} />
            </button>
        </div>
    );
}

function StripeSection({ settings, saving, permissions, updateSetting }) {
    const [showSk, setShowSk]       = useState(false);
    const [showWh, setShowWh]       = useState(false);
    const [skInput, setSkInput]     = useState('');
    const [whInput, setWhInput]     = useState('');
    const [pkInput, setPkInput]     = useState(settings?.stripePublicKey ?? '');
    const [savingKeys, setSavingKeys] = useState(false);

    const canEdit = permissions.canEditSettings !== false;

    const saveKeys = async () => {
        setSavingKeys(true);
        try {
            await updateSetting({
                stripePublicKey:    pkInput.trim() || undefined,
                stripeSecretKey:    skInput.trim() || undefined,
                stripeWebhookSecret: whInput.trim() || undefined,
            });
            setSkInput('');
            setWhInput('');
        } finally {
            setSavingKeys(false);
        }
    };

    return (
        <div className="rounded-lg border">
            <div className="px-5 py-4 border-b bg-muted/40">
                <h3 className="font-semibold text-sm">Paiement — Stripe</h3>
                <p className="text-xs text-muted-foreground mt-0.5">
                    Intégration Stripe pour les paiements en ligne. Les clés sont stockées en base de données.
                </p>
            </div>
            <div className="p-5 space-y-6">

                {/* Toggle */}
                <div className="flex items-center justify-between gap-6">
                    <div>
                        <p className="text-sm font-medium">Activer Stripe</p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Quand activé, le checkout affiche le formulaire de paiement Stripe.
                            Désactivé, les commandes passent directement en attente (paiement manuel).
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={settings?.stripeEnabled}
                        disabled={saving || !canEdit}
                        onClick={() => updateSetting({ stripeEnabled: !settings?.stripeEnabled })}
                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                            settings?.stripeEnabled ? 'bg-primary' : 'bg-muted'
                        } ${saving ? 'opacity-60 cursor-not-allowed' : ''}`}
                    >
                        <span className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transform transition-transform ${
                            settings?.stripeEnabled ? 'translate-x-5' : 'translate-x-0'
                        }`} />
                    </button>
                </div>

                <div className="border-t" />

                {/* Keys */}
                <div className="space-y-4">
                    <p className="text-sm font-medium">Clés API Stripe</p>

                    {/* Public key */}
                    <div className="space-y-1.5">
                        <label className="text-xs font-medium text-muted-foreground">Clé publique (pk_…)</label>
                        <input
                            type="text"
                            value={pkInput}
                            onChange={e => setPkInput(e.target.value)}
                            disabled={!canEdit}
                            placeholder="pk_live_… ou pk_test_…"
                            className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                        />
                    </div>

                    {/* Secret key */}
                    <div className="space-y-1.5">
                        <label className="text-xs font-medium text-muted-foreground">
                            Clé secrète (sk_…) {settings?.stripeSecretKeySet && <span className="text-green-600 ml-1">✓ définie</span>}
                        </label>
                        <div className="relative">
                            <input
                                type={showSk ? 'text' : 'password'}
                                value={skInput}
                                onChange={e => setSkInput(e.target.value)}
                                disabled={!canEdit}
                                placeholder={settings?.stripeSecretKeySet ? '••••••••• (laisser vide pour conserver)' : 'sk_live_… ou sk_test_…'}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 pr-10 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                            />
                            <button type="button" onClick={() => setShowSk(v => !v)} className="absolute right-3 top-2.5 text-muted-foreground hover:text-foreground">
                                {showSk ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                    </div>

                    {/* Webhook secret */}
                    <div className="space-y-1.5">
                        <label className="text-xs font-medium text-muted-foreground">
                            Webhook secret (whsec_…) {settings?.stripeWebhookSecretSet && <span className="text-green-600 ml-1">✓ défini</span>}
                        </label>
                        <div className="relative">
                            <input
                                type={showWh ? 'text' : 'password'}
                                value={whInput}
                                onChange={e => setWhInput(e.target.value)}
                                disabled={!canEdit}
                                placeholder={settings?.stripeWebhookSecretSet ? '••••••••• (laisser vide pour conserver)' : 'whsec_…'}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 pr-10 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                            />
                            <button type="button" onClick={() => setShowWh(v => !v)} className="absolute right-3 top-2.5 text-muted-foreground hover:text-foreground">
                                {showWh ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={saveKeys}
                        disabled={savingKeys || !canEdit || (!pkInput.trim() && !skInput.trim() && !whInput.trim())}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {savingKeys ? 'Enregistrement…' : 'Enregistrer les clés'}
                    </button>
                </div>

                <div className="rounded-md bg-muted/60 px-4 py-3 text-xs text-muted-foreground space-y-1">
                    <p><strong>URL du webhook à configurer dans Stripe :</strong></p>
                    <code className="block mt-1">{window.location.origin}/api/webhook/stripe</code>
                    <p className="mt-2">Événements à écouter : <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code></p>
                </div>
            </div>
        </div>
    );
}

function PayPalSection({ settings, saving, permissions, updateSetting }) {
    const [showSecret, setShowSecret]   = useState(false);
    const [secretInput, setSecretInput] = useState('');
    const [clientIdInput, setClientIdInput] = useState(settings?.paypalClientId ?? '');
    const [webhookIdInput, setWebhookIdInput] = useState('');
    const [savingKeys, setSavingKeys]   = useState(false);

    const canEdit = permissions.canEditSettings !== false;

    const saveKeys = async () => {
        setSavingKeys(true);
        try {
            await updateSetting({
                paypalClientId:     clientIdInput.trim() || undefined,
                paypalClientSecret: secretInput.trim() || undefined,
                paypalWebhookId:    webhookIdInput.trim() || undefined,
            });
            setSecretInput('');
            setWebhookIdInput('');
        } finally {
            setSavingKeys(false);
        }
    };

    return (
        <div className="rounded-lg border">
            <div className="px-5 py-4 border-b bg-muted/40">
                <h3 className="font-semibold text-sm">Paiement — PayPal</h3>
                <p className="text-xs text-muted-foreground mt-0.5">
                    Intégration PayPal via l'API Orders v2. Les clés sont stockées en base de données.
                </p>
            </div>
            <div className="p-5 space-y-6">

                {/* Toggle activé */}
                <div className="flex items-center justify-between gap-6">
                    <div>
                        <p className="text-sm font-medium">Activer PayPal</p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Quand activé, le checkout affiche les boutons PayPal.
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={settings?.paypalEnabled}
                        disabled={saving || !canEdit}
                        onClick={() => updateSetting({ paypalEnabled: !settings?.paypalEnabled })}
                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                            settings?.paypalEnabled ? 'bg-primary' : 'bg-muted'
                        } ${saving ? 'opacity-60 cursor-not-allowed' : ''}`}
                    >
                        <span className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transform transition-transform ${
                            settings?.paypalEnabled ? 'translate-x-5' : 'translate-x-0'
                        }`} />
                    </button>
                </div>

                {/* Toggle sandbox */}
                <div className="flex items-center justify-between gap-6">
                    <div>
                        <p className="text-sm font-medium">Mode sandbox</p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Utilise l'environnement de test PayPal (sandbox.paypal.com).
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={settings?.paypalSandbox}
                        disabled={saving || !canEdit}
                        onClick={() => updateSetting({ paypalSandbox: !settings?.paypalSandbox })}
                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                            settings?.paypalSandbox ? 'bg-primary' : 'bg-muted'
                        } ${saving ? 'opacity-60 cursor-not-allowed' : ''}`}
                    >
                        <span className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transform transition-transform ${
                            settings?.paypalSandbox ? 'translate-x-5' : 'translate-x-0'
                        }`} />
                    </button>
                </div>

                <div className="border-t" />

                {/* Keys */}
                <div className="space-y-4">
                    <p className="text-sm font-medium">Clés API PayPal</p>

                    <div className="space-y-1.5">
                        <label className="text-xs font-medium text-muted-foreground">Client ID (public)</label>
                        <input
                            type="text"
                            value={clientIdInput}
                            onChange={e => setClientIdInput(e.target.value)}
                            disabled={!canEdit}
                            placeholder="AaBb…"
                            className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <label className="text-xs font-medium text-muted-foreground">
                            Client Secret {settings?.paypalClientSecretSet && <span className="text-green-600 ml-1">✓ défini</span>}
                        </label>
                        <div className="relative">
                            <input
                                type={showSecret ? 'text' : 'password'}
                                value={secretInput}
                                onChange={e => setSecretInput(e.target.value)}
                                disabled={!canEdit}
                                placeholder={settings?.paypalClientSecretSet ? '••••••••• (laisser vide pour conserver)' : 'EaBb…'}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 pr-10 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                            />
                            <button type="button" onClick={() => setShowSecret(v => !v)} className="absolute right-3 top-2.5 text-muted-foreground hover:text-foreground">
                                {showSecret ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <label className="text-xs font-medium text-muted-foreground">
                            Webhook ID {settings?.paypalWebhookIdSet && <span className="text-green-600 ml-1">✓ défini</span>}
                        </label>
                        <input
                            type="text"
                            value={webhookIdInput}
                            onChange={e => setWebhookIdInput(e.target.value)}
                            disabled={!canEdit}
                            placeholder={settings?.paypalWebhookIdSet ? '••••••••• (laisser vide pour conserver)' : 'ID du webhook PayPal'}
                            className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                        />
                    </div>

                    <button
                        type="button"
                        onClick={saveKeys}
                        disabled={savingKeys || !canEdit || (!clientIdInput.trim() && !secretInput.trim() && !webhookIdInput.trim())}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {savingKeys ? 'Enregistrement…' : 'Enregistrer les clés'}
                    </button>
                </div>

                <div className="rounded-md bg-muted/60 px-4 py-3 text-xs text-muted-foreground space-y-1">
                    <p><strong>URL du webhook à configurer dans PayPal :</strong></p>
                    <code className="block mt-1">{window.location.origin}/api/webhook/paypal</code>
                    <p className="mt-2">Événements à écouter : <code>PAYMENT.CAPTURE.COMPLETED</code>, <code>PAYMENT.CAPTURE.DENIED</code></p>
                </div>
            </div>
        </div>
    );
}

function MollieSection({ settings, saving, permissions, updateSetting }) {
    const [showKey, setShowKey]     = useState(false);
    const [keyInput, setKeyInput]   = useState('');
    const [savingKey, setSavingKey] = useState(false);

    const canEdit = permissions.canEditSettings !== false;

    const saveKey = async () => {
        setSavingKey(true);
        try {
            await updateSetting({ mollieApiKey: keyInput.trim() || undefined });
            setKeyInput('');
        } finally {
            setSavingKey(false);
        }
    };

    return (
        <div className="rounded-lg border">
            <div className="px-5 py-4 border-b bg-muted/40">
                <h3 className="font-semibold text-sm">Paiement — Mollie</h3>
                <p className="text-xs text-muted-foreground mt-0.5">
                    Intégration Mollie (iDEAL, Bancontact, CB…). Paiement par redirection vers la page hébergée Mollie.
                </p>
            </div>
            <div className="p-5 space-y-6">

                {/* Toggle */}
                <div className="flex items-center justify-between gap-6">
                    <div>
                        <p className="text-sm font-medium">Activer Mollie</p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Quand activé, le checkout redirige vers la page de paiement Mollie.
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={settings?.mollieEnabled}
                        disabled={saving || !canEdit}
                        onClick={() => updateSetting({ mollieEnabled: !settings?.mollieEnabled })}
                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                            settings?.mollieEnabled ? 'bg-primary' : 'bg-muted'
                        } ${saving ? 'opacity-60 cursor-not-allowed' : ''}`}
                    >
                        <span className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transform transition-transform ${
                            settings?.mollieEnabled ? 'translate-x-5' : 'translate-x-0'
                        }`} />
                    </button>
                </div>

                <div className="border-t" />

                {/* API Key */}
                <div className="space-y-4">
                    <p className="text-sm font-medium">Clé API Mollie</p>
                    <div className="space-y-1.5">
                        <label className="text-xs font-medium text-muted-foreground">
                            Clé API (test_… ou live_…)
                            {settings?.mollieApiKeySet && (
                                <span className="text-green-600 ml-1">
                                    ✓ définie
                                    {settings.mollieApiKeyPrefix && (
                                        <span className="text-muted-foreground ml-1">
                                            ({settings.mollieApiKeyPrefix}…)
                                        </span>
                                    )}
                                </span>
                            )}
                        </label>
                        <div className="relative">
                            <input
                                type={showKey ? 'text' : 'password'}
                                value={keyInput}
                                onChange={e => setKeyInput(e.target.value)}
                                disabled={!canEdit}
                                placeholder={settings?.mollieApiKeySet ? '••••••••• (laisser vide pour conserver)' : 'test_… ou live_…'}
                                className="w-full rounded-lg border border-input bg-background px-3 py-2 pr-10 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                            />
                            <button type="button" onClick={() => setShowKey(v => !v)} className="absolute right-3 top-2.5 text-muted-foreground hover:text-foreground">
                                {showKey ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Trouvez vos clés dans le tableau de bord Mollie → Développeurs → Clés API.
                            Utilisez <code>test_</code> en développement, <code>live_</code> en production.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={saveKey}
                        disabled={savingKey || !canEdit || !keyInput.trim()}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {savingKey ? 'Enregistrement…' : 'Enregistrer la clé'}
                    </button>
                </div>

                <div className="rounded-md bg-muted/60 px-4 py-3 text-xs text-muted-foreground space-y-1">
                    <p><strong>URL du webhook à configurer dans Mollie :</strong></p>
                    <code className="block mt-1">{window.location.origin}/api/webhook/mollie</code>
                    <p className="mt-2">Mollie envoie automatiquement le statut de paiement à cette URL après chaque transaction.</p>
                </div>
            </div>
        </div>
    );
}
