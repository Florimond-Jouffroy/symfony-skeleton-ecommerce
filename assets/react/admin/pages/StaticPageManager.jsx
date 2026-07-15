import React, { useEffect, useRef, useState } from 'react';
import { ArrowLeft, ExternalLink, Pencil, Plus, Trash2, X } from 'lucide-react';
import BlockEditor from '../components/BlockEditor';
import { api } from '../../utils/api';

function SlugInput({ value, onChange, locked }) {
    return (
        <div>
            <label className="mb-1 block text-xs font-medium text-muted-foreground">
                Slug (URL) <span className="font-normal">— /pages/<strong>{value || '…'}</strong></span>
            </label>
            <input
                value={value}
                onChange={e => onChange(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-'))}
                disabled={locked}
                className="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                placeholder="mon-slug"
            />
        </div>
    );
}

function PageEditor({ page, pagesUrl, onSaved, onCancel }) {
    const [title, setTitle]       = useState(page?.title ?? '');
    const [slug, setSlug]         = useState(page?.slug ?? '');
    const [content, setContent]   = useState(page?.content ?? { blocks: [] });
    const [isActive, setIsActive] = useState(page?.isActive ?? true);
    const [saving, setSaving]     = useState(false);
    const [error, setError]       = useState(null);
    const [slugLocked, setSlugLocked] = useState(!!page);
    const isEdit = !!page;

    const handleTitleChange = (val) => {
        setTitle(val);
        if (!isEdit && !slugLocked) {
            const auto = val.toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
            setSlug(auto);
        }
    };

    const handleSave = async () => {
        if (!title.trim()) { setError('Le titre est requis.'); return; }
        if (!slug.trim())  { setError('Le slug est requis.'); return; }
        setError(null);
        setSaving(true);
        try {
            const payload = { title: title.trim(), slug: slug.trim(), content, isActive };
            const saved = isEdit
                ? await api.patch(`${pagesUrl}/${page.id}`, payload)
                : await api.post(pagesUrl, payload);
            onSaved(saved);
        } catch (err) {
            setError(err.message ?? 'Erreur lors de la sauvegarde.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-5">
            <div className="flex items-center gap-3">
                <button type="button" onClick={onCancel} className="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
                    <ArrowLeft className="size-4" />
                </button>
                <h2 className="font-semibold">{isEdit ? `Modifier — ${page.title}` : 'Nouvelle page'}</h2>
                {isEdit && (
                    <a
                        href={`/pages/${page.slug}`}
                        target="_blank"
                        rel="noreferrer"
                        className="ml-auto flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ExternalLink className="size-3.5" />
                        Voir la page
                    </a>
                )}
            </div>

            {error && (
                <div className="flex items-center justify-between rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                    <button type="button" onClick={() => setError(null)}><X className="size-4" /></button>
                </div>
            )}

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="mb-1 block text-xs font-medium text-muted-foreground">Titre</label>
                    <input
                        value={title}
                        onChange={e => handleTitleChange(e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Ex. Mentions légales"
                    />
                </div>
                <div>
                    <SlugInput
                        value={slug}
                        onChange={setSlug}
                        locked={isEdit && slugLocked}
                    />
                    {isEdit && (
                        <button
                            type="button"
                            onClick={() => setSlugLocked(v => !v)}
                            className="mt-1 text-[10px] text-muted-foreground underline underline-offset-2 hover:text-foreground transition-colors"
                        >
                            {slugLocked ? 'Modifier le slug' : 'Verrouiller le slug'}
                        </button>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-2">
                <input
                    id="page-active"
                    type="checkbox"
                    checked={isActive}
                    onChange={e => setIsActive(e.target.checked)}
                    className="size-4 rounded border-input accent-primary"
                />
                <label htmlFor="page-active" className="text-sm cursor-pointer">Page visible sur le site</label>
            </div>

            <div>
                <label className="mb-2 block text-xs font-medium text-muted-foreground">Contenu</label>
                <BlockEditor value={content} onChange={setContent} />
            </div>

            <div className="flex justify-end gap-2 pb-4">
                <button type="button" onClick={onCancel} className="rounded-md border px-4 py-2 text-sm hover:bg-accent transition-colors">
                    Annuler
                </button>
                <button
                    type="button"
                    onClick={handleSave}
                    disabled={saving}
                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
                >
                    {saving ? 'Sauvegarde…' : 'Sauvegarder'}
                </button>
            </div>
        </div>
    );
}

export default function StaticPageManager({ urls, permissions = {} }) {
    const pagesUrl = urls.pages;
    const [pages, setPages]     = useState([]);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState(null); // null = list, false = new, page obj = edit
    const [error, setError]     = useState(null);

    const load = () => {
        setLoading(true);
        api.get(pagesUrl)
            .then(data => setPages(data ?? []))
            .catch(() => setError('Impossible de charger les pages.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { load(); }, []);

    const openEdit = async (page) => {
        try {
            const full = await api.get(`${pagesUrl}/${page.id}`);
            setEditing(full);
        } catch {
            setError('Impossible de charger cette page.');
        }
    };

    const handleSaved = (saved) => {
        setPages(ps => {
            const exists = ps.find(p => p.id === saved.id);
            return exists ? ps.map(p => p.id === saved.id ? saved : p) : [saved, ...ps];
        });
        setEditing(null);
    };

    const handleDelete = async (page) => {
        if (!confirm(`Supprimer la page "${page.title}" ?`)) return;
        try {
            await api.delete(`${pagesUrl}/${page.id}`);
            setPages(ps => ps.filter(p => p.id !== page.id));
        } catch {
            setError('Erreur lors de la suppression.');
        }
    };

    const handleToggleActive = async (page) => {
        try {
            const updated = await api.patch(`${pagesUrl}/${page.id}`, { isActive: !page.isActive });
            setPages(ps => ps.map(p => p.id === updated.id ? { ...p, ...updated } : p));
        } catch {
            setError('Erreur lors de la mise à jour.');
        }
    };

    if (editing !== null) {
        return (
            <PageEditor
                page={editing === false ? null : editing}
                pagesUrl={pagesUrl}
                onSaved={handleSaved}
                onCancel={() => setEditing(null)}
            />
        );
    }

    if (loading) {
        return (
            <div className="flex justify-center py-12">
                <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
        );
    }

    return (
        <div className="space-y-5">
            {error && (
                <div className="flex items-center justify-between rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                    <button type="button" onClick={() => setError(null)}><X className="size-4" /></button>
                </div>
            )}

            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{pages.length} page{pages.length !== 1 ? 's' : ''}</p>
                {permissions.canCreatePage !== false && (
                    <button
                        type="button"
                        onClick={() => setEditing(false)}
                        className="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                    >
                        <Plus className="size-4" />
                        Nouvelle page
                    </button>
                )}
            </div>

            {pages.length === 0 && (
                <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                    Aucune page. Créez "Mentions légales", "CGV" ou "Politique de confidentialité" pour commencer.
                </div>
            )}

            <div className="space-y-2">
                {pages.map(page => (
                    <div key={page.id} className="flex items-center gap-3 rounded-lg border bg-card px-4 py-3">
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-sm">{page.title}</p>
                            <p className="text-xs text-muted-foreground font-mono mt-0.5">/pages/{page.slug}</p>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {permissions.canEditPage !== false && (
                                <button
                                    type="button"
                                    onClick={() => handleToggleActive(page)}
                                    className={`rounded-full px-2 py-0.5 text-xs font-medium transition-colors ${
                                        page.isActive
                                            ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                            : 'bg-muted text-muted-foreground hover:bg-accent'
                                    }`}
                                >
                                    {page.isActive ? 'Visible' : 'Masquée'}
                                </button>
                            )}
                            <a
                                href={`/pages/${page.slug}`}
                                target="_blank"
                                rel="noreferrer"
                                className="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                            >
                                <ExternalLink className="size-3.5" />
                            </a>
                            {permissions.canEditPage !== false && (
                                <button
                                    type="button"
                                    onClick={() => openEdit(page)}
                                    className="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                                >
                                    <Pencil className="size-3.5" />
                                </button>
                            )}
                            {permissions.canDeletePage !== false && (
                                <button
                                    type="button"
                                    onClick={() => handleDelete(page)}
                                    className="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors"
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
