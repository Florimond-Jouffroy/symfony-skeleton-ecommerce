import React, { useEffect, useState } from 'react';
import { Plus, Trash2, PencilLine } from 'lucide-react';
import { api, getErrorMessage } from '../../utils/api';

const TYPE_LABELS = { percent: 'Pourcentage', fixed: 'Montant fixe' };

function euros(cents) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function formatValue(code) {
    return code.type === 'percent' ? `${code.value} %` : euros(code.value);
}

const EMPTY_FORM = { code: '', type: 'percent', value: '', expiresAt: '', maxUses: '', isActive: true };

function PromoForm({ initial = EMPTY_FORM, onSave, onCancel, saving, error }) {
    const [form, setForm] = useState(initial);
    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const handleSubmit = (e) => {
        e.preventDefault();
        onSave({
            code:      form.code,
            type:      form.type,
            value:     form.type === 'percent' ? parseInt(form.value, 10) : Math.round(parseFloat(form.value) * 100),
            expiresAt: form.expiresAt || null,
            maxUses:   form.maxUses !== '' ? parseInt(form.maxUses, 10) : null,
            isActive:  form.isActive,
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                    <label className="text-sm font-medium">Code *</label>
                    <input
                        required
                        value={form.code}
                        onChange={e => set('code', e.target.value.toUpperCase())}
                        placeholder="EX: BIENVENUE20"
                        className="w-full rounded-md border px-3 py-2 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <div className="space-y-1">
                    <label className="text-sm font-medium">Type *</label>
                    <select
                        value={form.type}
                        onChange={e => set('type', e.target.value)}
                        className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    >
                        <option value="percent">Pourcentage (%)</option>
                        <option value="fixed">Montant fixe (€)</option>
                    </select>
                </div>

                <div className="space-y-1">
                    <label className="text-sm font-medium">
                        {form.type === 'percent' ? 'Valeur (%)' : 'Valeur (€)'} *
                    </label>
                    <input
                        required
                        type="number"
                        min="1"
                        max={form.type === 'percent' ? 100 : undefined}
                        step={form.type === 'percent' ? '1' : '0.01'}
                        value={form.value}
                        onChange={e => set('value', e.target.value)}
                        placeholder={form.type === 'percent' ? 'ex : 15' : 'ex : 10.00'}
                        className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <div className="space-y-1">
                    <label className="text-sm font-medium">Expiration</label>
                    <input
                        type="date"
                        value={form.expiresAt}
                        onChange={e => set('expiresAt', e.target.value)}
                        className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <div className="space-y-1">
                    <label className="text-sm font-medium">Utilisations max</label>
                    <input
                        type="number"
                        min="1"
                        value={form.maxUses}
                        onChange={e => set('maxUses', e.target.value)}
                        placeholder="Illimité"
                        className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <div className="flex items-center gap-3 pt-6">
                    <button
                        type="button"
                        role="switch"
                        aria-checked={form.isActive}
                        onClick={() => set('isActive', !form.isActive)}
                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors ${
                            form.isActive ? 'bg-primary' : 'bg-muted'
                        }`}
                    >
                        <span className={`pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transform transition-transform ${
                            form.isActive ? 'translate-x-5' : 'translate-x-0'
                        }`} />
                    </button>
                    <span className="text-sm">{form.isActive ? 'Actif' : 'Inactif'}</span>
                </div>
            </div>

            {error && <p className="text-sm text-destructive">{error}</p>}

            <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={onCancel} className="rounded-md border px-4 py-2 text-sm hover:bg-accent transition-colors">
                    Annuler
                </button>
                <button type="submit" disabled={saving} className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60">
                    {saving ? 'Enregistrement…' : 'Enregistrer'}
                </button>
            </div>
        </form>
    );
}

export default function PromoCodeManager({ urls = {}, permissions = {} }) {
    const [codes, setCodes]       = useState([]);
    const [loading, setLoading]   = useState(true);
    const [panel, setPanel]       = useState(null); // null | 'create' | {code}
    const [saving, setSaving]     = useState(false);
    const [formError, setFormError] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const baseUrl = urls.promoCodes ?? '/api/admin/codes-promo';

    useEffect(() => {
        api.get(baseUrl).then(setCodes).finally(() => setLoading(false));
    }, []);

    const openCreate = () => { setFormError(null); setPanel('create'); };
    const openEdit   = (c)  => { setFormError(null); setPanel(c); };
    const closePanel = ()   => setPanel(null);

    const handleSave = async (payload) => {
        setSaving(true);
        setFormError(null);
        try {
            if (panel === 'create') {
                const created = await api.post(baseUrl, payload);
                setCodes(prev => [created, ...prev]);
            } else {
                const updated = await api.patch(`${baseUrl}/${panel.id}`, payload);
                setCodes(prev => prev.map(c => c.id === updated.id ? updated : c));
            }
            closePanel();
        } catch (err) {
            setFormError(getErrorMessage(err));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (code) => {
        if (!window.confirm(`Supprimer le code « ${code.code} » ?`)) return;
        setDeleting(code.id);
        try {
            await api.delete(`${baseUrl}/${code.id}`);
            setCodes(prev => prev.filter(c => c.id !== code.id));
        } finally {
            setDeleting(null);
        }
    };

    const handleToggle = async (code) => {
        try {
            const updated = await api.patch(`${baseUrl}/${code.id}`, { isActive: !code.isActive });
            setCodes(prev => prev.map(c => c.id === updated.id ? updated : c));
        } catch {
            // silently ignore
        }
    };

    return (
        <div className="space-y-6 max-w-4xl">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Codes promo</h2>
                    <p className="text-sm text-muted-foreground mt-1">Gérez les codes de réduction applicables à la boutique</p>
                </div>
                {permissions.canCreatePromoCode !== false && (
                    <button
                        onClick={openCreate}
                        className="flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                    >
                        <Plus className="size-4" />
                        Nouveau code
                    </button>
                )}
            </div>

            {/* Formulaire création / édition */}
            {panel !== null && (
                <div className="rounded-lg border p-5">
                    <h3 className="font-semibold text-sm mb-4">{panel === 'create' ? 'Nouveau code promo' : `Modifier « ${panel.code} »`}</h3>
                    <PromoForm
                        initial={panel === 'create' ? EMPTY_FORM : {
                            code:      panel.code,
                            type:      panel.type,
                            value:     panel.type === 'percent' ? String(panel.value) : String(panel.value / 100),
                            expiresAt: panel.expiresAt ?? '',
                            maxUses:   panel.maxUses != null ? String(panel.maxUses) : '',
                            isActive:  panel.isActive,
                        }}
                        onSave={handleSave}
                        onCancel={closePanel}
                        saving={saving}
                        error={formError}
                    />
                </div>
            )}

            {/* Liste */}
            {loading ? (
                <div className="space-y-2 animate-pulse">
                    {[...Array(3)].map((_, i) => <div key={i} className="h-14 rounded-lg bg-muted" />)}
                </div>
            ) : codes.length === 0 ? (
                <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                    Aucun code promo créé.
                </div>
            ) : (
                <div className="rounded-lg border overflow-hidden">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40 text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Code</th>
                                <th className="px-4 py-3 text-left font-medium">Réduction</th>
                                <th className="px-4 py-3 text-left font-medium">Expiration</th>
                                <th className="px-4 py-3 text-left font-medium">Utilisations</th>
                                <th className="px-4 py-3 text-left font-medium">Statut</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {codes.map(c => (
                                <tr key={c.id} className="border-b last:border-0 hover:bg-muted/30 transition-colors">
                                    <td className="px-4 py-3 font-mono font-semibold tracking-wide">{c.code}</td>
                                    <td className="px-4 py-3">{formatValue(c)}</td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {c.expiresAt
                                            ? new Date(c.expiresAt).toLocaleDateString('fr-FR')
                                            : <span className="text-xs">Aucune</span>
                                        }
                                    </td>
                                    <td className="px-4 py-3 tabular-nums text-muted-foreground">
                                        {c.usedCount}{c.maxUses != null ? ` / ${c.maxUses}` : ''}
                                    </td>
                                    <td className="px-4 py-3">
                                        {permissions.canEditPromoCode !== false ? (
                                            <button
                                                type="button"
                                                onClick={() => handleToggle(c)}
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors ${
                                                    c.isUsable
                                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                                }`}
                                            >
                                                {c.isUsable ? 'Actif' : (c.isActive ? 'Expiré / épuisé' : 'Désactivé')}
                                            </button>
                                        ) : (
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                c.isUsable ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground'
                                            }`}>
                                                {c.isUsable ? 'Actif' : (c.isActive ? 'Expiré / épuisé' : 'Désactivé')}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            {permissions.canEditPromoCode !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(c)}
                                                    className="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                                                >
                                                    <PencilLine className="size-4" />
                                                </button>
                                            )}
                                            {permissions.canDeletePromoCode !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(c)}
                                                    disabled={deleting === c.id}
                                                    className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors disabled:opacity-40"
                                                >
                                                    <Trash2 className="size-4" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
