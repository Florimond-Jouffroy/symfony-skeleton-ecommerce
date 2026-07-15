import React, { useEffect, useState } from 'react';
import { ChevronUp, ChevronDown, Pencil, Trash2, Plus, Check, X } from 'lucide-react';
import { api } from '../../utils/api';

const empty = { question: '', answer: '', isActive: true };

function FaqForm({ initial = empty, onSave, onCancel, saving }) {
    const [form, setForm] = useState(initial);
    const [error, setError] = useState(null);

    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const handleSave = async () => {
        if (!form.question.trim() || !form.answer.trim()) {
            setError('La question et la réponse sont requises.');
            return;
        }
        setError(null);
        await onSave(form);
    };

    return (
        <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
            {error && <p className="text-xs text-destructive">{error}</p>}
            <div>
                <label className="mb-1 block text-xs font-medium text-muted-foreground">Question</label>
                <input
                    className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    value={form.question}
                    onChange={e => set('question', e.target.value)}
                    placeholder="Ex. Quels sont vos délais de livraison ?"
                />
            </div>
            <div>
                <label className="mb-1 block text-xs font-medium text-muted-foreground">Réponse</label>
                <textarea
                    rows={4}
                    className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary resize-y"
                    value={form.answer}
                    onChange={e => set('answer', e.target.value)}
                    placeholder="Répondez à la question ici…"
                />
            </div>
            <div className="flex items-center gap-2">
                <input
                    id="faq-active"
                    type="checkbox"
                    checked={form.isActive}
                    onChange={e => set('isActive', e.target.checked)}
                    className="size-4 rounded border-input accent-primary"
                />
                <label htmlFor="faq-active" className="text-sm cursor-pointer">Visible sur le site</label>
            </div>
            <div className="flex justify-end gap-2">
                <button
                    type="button"
                    onClick={onCancel}
                    className="rounded-md border px-3 py-1.5 text-sm hover:bg-accent transition-colors"
                >
                    Annuler
                </button>
                <button
                    type="button"
                    onClick={handleSave}
                    disabled={saving}
                    className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
                >
                    {saving ? 'Enregistrement…' : 'Enregistrer'}
                </button>
            </div>
        </div>
    );
}

export default function FaqManager({ urls, permissions = {} }) {
    const [items, setItems]       = useState([]);
    const [loading, setLoading]   = useState(true);
    const [creating, setCreating] = useState(false);
    const [editId, setEditId]     = useState(null);
    const [saving, setSaving]     = useState(false);
    const [error, setError]       = useState(null);

    const faqUrl = urls.faq;

    const load = () => {
        setLoading(true);
        api.get(faqUrl)
            .then(data => setItems(data ?? []))
            .catch(() => setError('Impossible de charger la FAQ.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { load(); }, []);

    const handleCreate = async (form) => {
        setSaving(true);
        try {
            await api.post(faqUrl, form);
            setCreating(false);
            load();
        } catch (e) {
            setError(e.message ?? 'Erreur lors de la création.');
        } finally {
            setSaving(false);
        }
    };

    const handleUpdate = async (id, form) => {
        setSaving(true);
        try {
            await api.patch(`${faqUrl}/${id}`, form);
            setEditId(null);
            load();
        } catch (e) {
            setError(e.message ?? 'Erreur lors de la mise à jour.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Supprimer cette entrée FAQ ?')) return;
        try {
            await api.delete(`${faqUrl}/${id}`);
            setItems(items => items.filter(i => i.id !== id));
        } catch {
            setError('Erreur lors de la suppression.');
        }
    };

    const handleToggleActive = async (item) => {
        try {
            const updated = await api.patch(`${faqUrl}/${item.id}`, { isActive: !item.isActive });
            setItems(items => items.map(i => i.id === item.id ? updated : i));
        } catch {
            setError('Erreur lors de la mise à jour.');
        }
    };

    const handleMove = async (id, direction) => {
        try {
            const updated = await api.post(`${faqUrl}/${id}/move-${direction}`);
            setItems(updated);
        } catch {
            setError('Erreur lors du déplacement.');
        }
    };

    if (loading) {
        return (
            <div className="flex justify-center py-12">
                <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {error && (
                <div className="flex items-center justify-between rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                    <button type="button" onClick={() => setError(null)}><X className="size-4" /></button>
                </div>
            )}

            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">
                    {items.length} entrée{items.length !== 1 ? 's' : ''}
                </p>
                {!creating && permissions.canCreateFaq !== false && (
                    <button
                        type="button"
                        onClick={() => setCreating(true)}
                        className="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                    >
                        <Plus className="size-4" />
                        Ajouter une question
                    </button>
                )}
            </div>

            {creating && permissions.canCreateFaq !== false && (
                <FaqForm
                    onSave={handleCreate}
                    onCancel={() => setCreating(false)}
                    saving={saving}
                />
            )}

            {items.length === 0 && !creating && (
                <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                    Aucune entrée FAQ. Cliquez sur « Ajouter une question » pour commencer.
                </div>
            )}

            <div className="space-y-3">
                {items.map((item, idx) => (
                    <div key={item.id} className="rounded-lg border bg-card">
                        {editId === item.id && permissions.canEditFaq !== false ? (
                            <div className="p-4">
                                <FaqForm
                                    initial={{ question: item.question, answer: item.answer, isActive: item.isActive }}
                                    onSave={form => handleUpdate(item.id, form)}
                                    onCancel={() => setEditId(null)}
                                    saving={saving}
                                />
                            </div>
                        ) : (
                            <div className="flex items-start gap-3 p-4">
                                {/* Boutons de déplacement */}
                                {permissions.canEditFaq !== false && (
                                    <div className="flex flex-col gap-0.5 pt-0.5">
                                        <button
                                            type="button"
                                            disabled={idx === 0}
                                            onClick={() => handleMove(item.id, 'up')}
                                            className="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-30 transition-colors"
                                        >
                                            <ChevronUp className="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            disabled={idx === items.length - 1}
                                            onClick={() => handleMove(item.id, 'down')}
                                            className="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-30 transition-colors"
                                        >
                                            <ChevronDown className="size-4" />
                                        </button>
                                    </div>
                                )}

                                {/* Contenu */}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="font-medium text-sm leading-snug">{item.question}</p>
                                        <div className="flex shrink-0 items-center gap-1">
                                            {permissions.canEditFaq !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleToggleActive(item)}
                                                    title={item.isActive ? 'Masquer' : 'Afficher'}
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium transition-colors ${
                                                        item.isActive
                                                            ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                            : 'bg-muted text-muted-foreground hover:bg-accent'
                                                    }`}
                                                >
                                                    {item.isActive ? 'Visible' : 'Masqué'}
                                                </button>
                                            )}
                                            {permissions.canEditFaq !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => setEditId(item.id)}
                                                    className="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                                                >
                                                    <Pencil className="size-3.5" />
                                                </button>
                                            )}
                                            {permissions.canDeleteFaq !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(item.id)}
                                                    className="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    <p className="mt-1.5 text-xs text-muted-foreground line-clamp-2 whitespace-pre-line">
                                        {item.answer}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
