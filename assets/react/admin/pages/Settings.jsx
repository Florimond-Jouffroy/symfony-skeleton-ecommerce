import React, { useEffect, useState } from 'react';
import { api, getErrorMessage } from '../../utils/api';

const TAX_RATE_OPTIONS = [
    { value: 20,  label: '20% — Taux normal (vêtements, électronique, etc.)' },
    { value: 10,  label: '10% — Taux intermédiaire (restauration, certains services)' },
    { value: 5.5, label: '5,5% — Taux réduit (alimentation, livres, abonnements)' },
    { value: 2.1, label: '2,1% — Taux super-réduit (médicaments, presse)' },
    { value: 0,   label: '0% — Exonéré (exportations, DOM-TOM)' },
];

export default function Settings({ urls = {}, permissions = {} }) {
    const [settings, setSettings] = useState(null);
    const [loading, setLoading]   = useState(true);
    const [saving, setSaving]     = useState(false);
    const [feedback, setFeedback] = useState(null);

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
        <div className="space-y-8 max-w-2xl">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Paramètres</h2>
                <p className="text-sm text-muted-foreground mt-1">Configuration générale de la boutique</p>
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            {/* ── Site ── */}
            <div className="rounded-lg border">
                <div className="px-5 py-4 border-b bg-muted/40">
                    <h3 className="font-semibold text-sm">Site</h3>
                    <p className="text-xs text-muted-foreground mt-0.5">Disponibilité du site pour les visiteurs</p>
                </div>
                <div className="p-5">
                    <div className="flex items-center justify-between gap-6">
                        <div>
                            <p className="text-sm font-medium">Mode maintenance</p>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Quand activé, tous les visiteurs voient une page de maintenance.
                                Les administrateurs continuent d'accéder au site normalement.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={settings?.maintenanceMode}
                            disabled={saving || permissions.canEditSettings === false}
                            onClick={() => updateSetting({ maintenanceMode: !settings?.maintenanceMode })}
                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                                settings?.maintenanceMode ? 'bg-destructive' : 'bg-muted'
                            } ${saving ? 'opacity-60 cursor-not-allowed' : ''}`}
                        >
                            <span
                                className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transform transition-transform ${
                                    settings?.maintenanceMode ? 'translate-x-5' : 'translate-x-0'
                                }`}
                            />
                        </button>
                    </div>
                    {settings?.maintenanceMode && (
                        <div className="mt-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                            Le site est actuellement en <strong>maintenance</strong>. Les visiteurs ne peuvent pas accéder au site.
                            Une bannière d'avertissement est affichée pour les administrateurs connectés.
                        </div>
                    )}
                </div>
            </div>

            {/* ── Boutique ── */}
            <div className="rounded-lg border">
                <div className="px-5 py-4 border-b bg-muted/40">
                    <h3 className="font-semibold text-sm">Boutique en ligne</h3>
                    <p className="text-xs text-muted-foreground mt-0.5">Activer ou désactiver l'accès public à la boutique</p>
                </div>
                <div className="p-5">
                    <div className="flex items-center justify-between gap-6">
                        <div>
                            <p className="text-sm font-medium">Boutique active</p>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Quand désactivée, les pages <code>/boutique/*</code> affichent un message de maintenance
                                et le lien dans la navigation est masqué. L'administration reste entièrement accessible.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={settings?.shopEnabled}
                            disabled={saving || permissions.canEditSettings === false}
                            onClick={() => updateSetting({ shopEnabled: !settings?.shopEnabled })}
                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                                settings?.shopEnabled ? 'bg-primary' : 'bg-muted'
                            } ${saving ? 'opacity-60 cursor-not-allowed' : ''}`}
                        >
                            <span
                                className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transform transition-transform ${
                                    settings?.shopEnabled ? 'translate-x-5' : 'translate-x-0'
                                }`}
                            />
                        </button>
                    </div>
                    {settings?.shopEnabled === false && (
                        <div className="mt-4 rounded-md bg-yellow-50 border border-yellow-200 px-4 py-3 text-sm text-yellow-800">
                            La boutique est actuellement <strong>désactivée</strong>. Les visiteurs voient une page de maintenance.
                        </div>
                    )}
                </div>
            </div>

            {/* ── Facturation ── */}
            <div className="rounded-lg border">
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
        </div>
    );
}
