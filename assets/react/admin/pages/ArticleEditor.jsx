import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Globe, GlobeLock, ImageIcon, Save, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import BlockEditor from '../components/BlockEditor';
import BlockRenderer from '../components/BlockRenderer';
import MediaPickerModal from '../components/MediaPickerModal';
import { api, getErrorMessage } from '../../utils/api';

function CategoryPicker({ urls, selectedIds, onChange }) {
    const [categories, setCategories] = useState([]);

    useEffect(() => {
        api.get(urls.categories ?? '/api/admin/categories')
            .then(setCategories)
            .catch(() => {});
    }, [urls.categories]);

    const toggle = (id) => {
        onChange(
            selectedIds.includes(id)
                ? selectedIds.filter((x) => x !== id)
                : [...selectedIds, id],
        );
    };

    if (categories.length === 0) return null;

    return (
        <div className="flex flex-wrap gap-2">
            {categories.map((cat) => {
                const active = selectedIds.includes(cat.id);
                return (
                    <button
                        key={cat.id}
                        type="button"
                        onClick={() => toggle(cat.id)}
                        className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                            active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background text-muted-foreground hover:text-foreground hover:border-foreground/40'
                        }`}
                    >
                        {cat.name}
                    </button>
                );
            })}
        </div>
    );
}

export default function ArticleEditor({ permissions = {}, urls = {} }) {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEdit = id !== undefined;

    const [title, setTitle]                 = useState('');
    const [excerpt, setExcerpt]             = useState('');
    const [coverImage, setCoverImage]       = useState(null);
    const [categoryIds, setCategoryIds]     = useState([]);
    const [content, setContent]             = useState({ blocks: [] });
    const [status, setStatus]               = useState('draft');
    const [loading, setLoading]             = useState(isEdit);
    const [contentLoaded, setContentLoaded] = useState(!isEdit);
    const [saving, setSaving]               = useState(false);
    const [publishing, setPublishing]       = useState(false);
    const [feedback, setFeedback]           = useState(null);
    const [savedId, setSavedId]             = useState(id ? parseInt(id, 10) : null);
    const [activeTab, setActiveTab]         = useState('edit');
    // pickerMode: 'cover' | 'editor'
    const [pickerMode, setPickerMode]       = useState(null);

    const editorRef = useRef(null);

    useEffect(() => {
        if (!isEdit) return;

        api.get(`/api/admin/articles/${id}`)
            .then((data) => {
                setTitle(data.title);
                setExcerpt(data.excerpt ?? '');
                setCoverImage(data.coverImage ?? null);
                setCategoryIds((data.categories ?? []).map((c) => c.id));
                setContent(data.content ?? { blocks: [] });
                setStatus(data.status);
                setContentLoaded(true);
            })
            .catch(() => setFeedback({ type: 'error', message: "Impossible de charger l'article." }))
            .finally(() => setLoading(false));
    }, [id]);

    // Called by BlockEditor's custom uploader (file selected via Editor.js image tool)
    const handleUploadFile = useCallback(async (file) => {
        const formData = new FormData();
        formData.append('file', file);
        try {
            const media = await api.post(urls.mediaUpload ?? '/api/admin/medias', formData);
            return { success: 1, file: { url: media.url } };
        } catch {
            return { success: 0, message: 'Upload échoué.' };
        }
    }, [urls.mediaUpload]);

    const handleLibrarySelect = useCallback((url) => {
        if (pickerMode === 'cover') {
            setCoverImage(url);
        } else {
            editorRef.current?.insertImage(url);
        }
        setPickerMode(null);
    }, [pickerMode]);

    const handleSave = async () => {
        const trimmed = title.trim();
        if (!trimmed) {
            setFeedback({ type: 'error', message: 'Le titre est obligatoire.' });
            return;
        }

        setSaving(true);
        setFeedback(null);

        try {
            const payload = { title: trimmed, content, excerpt: excerpt.trim() || null, coverImage: coverImage || null, categoryIds };

            if (savedId) {
                const updated = await api.put(`/api/admin/articles/${savedId}`, payload);
                setStatus(updated.status);
                setFeedback({ type: 'success', message: 'Article enregistré.' });
            } else {
                const created = await api.post(urls.articles ?? '/api/admin/articles', payload);
                setSavedId(created.id);
                setStatus(created.status);
                navigate(`/articles/${created.id}/modifier`, { replace: true });
                setFeedback({ type: 'success', message: 'Article créé.' });
            }
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Erreur lors de la sauvegarde.') });
        } finally {
            setSaving(false);
        }
    };

    const handleTogglePublish = async () => {
        if (!savedId) {
            setFeedback({ type: 'error', message: "Enregistrez d'abord l'article avant de le publier." });
            return;
        }

        setPublishing(true);
        setFeedback(null);

        const endpoint = status === 'published'
            ? `/api/admin/articles/${savedId}/depublier`
            : `/api/admin/articles/${savedId}/publier`;

        try {
            const updated = await api.post(endpoint);
            setStatus(updated.status);
            setFeedback({
                type: 'success',
                message: updated.status === 'published' ? 'Article publié.' : 'Article repassé en brouillon.',
            });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setPublishing(false);
        }
    };

    if (loading) {
        return (
            <div className="space-y-4 animate-pulse">
                <div className="h-8 w-48 rounded bg-muted" />
                <div className="h-10 rounded bg-muted" />
                <div className="h-32 rounded bg-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* En-tête */}
            <div className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" onClick={() => navigate('/articles')}>
                        <ArrowLeft className="size-4" />
                        <span className="sr-only">Retour</span>
                    </Button>
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            {isEdit ? "Modifier l'article" : 'Nouvel article'}
                        </h2>
                        <Badge variant={status === 'published' ? 'default' : 'secondary'} className="mt-1">
                            {status === 'published' ? 'Publié' : 'Brouillon'}
                        </Badge>
                    </div>
                </div>

                <div className="flex items-center gap-2 shrink-0">
                    {permissions.canPublishArticle && savedId && (
                        <Button variant="outline" onClick={handleTogglePublish} disabled={publishing || saving}>
                            {status === 'published'
                                ? <><GlobeLock className="size-4" /> Dépublier</>
                                : <><Globe className="size-4" /> Publier</>
                            }
                        </Button>
                    )}

                    {(permissions.canCreateArticle || permissions.canEditArticle) && (
                        <Button onClick={handleSave} disabled={saving || publishing}>
                            <Save className="size-4" />
                            {saving ? 'Enregistrement…' : 'Enregistrer'}
                        </Button>
                    )}
                </div>
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            {/* Titre */}
            <div className="space-y-2">
                <Label htmlFor="article-title">Titre</Label>
                <Input
                    id="article-title"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Titre de l'article"
                    className="text-lg"
                />
            </div>

            {/* Image de couverture */}
            <div className="space-y-2">
                <Label>Image de couverture</Label>
                {coverImage ? (
                    <div className="relative group w-full overflow-hidden rounded-lg border bg-muted">
                        <img
                            src={coverImage}
                            alt="Couverture"
                            className="w-full max-h-64 object-cover"
                        />
                        <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() => setPickerMode('cover')}
                            >
                                <ImageIcon className="size-4" />
                                Changer
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={() => setCoverImage(null)}
                            >
                                <X className="size-4" />
                                Supprimer
                            </Button>
                        </div>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() => setPickerMode('cover')}
                        className="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border bg-muted/30 py-10 text-sm text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground"
                    >
                        <ImageIcon className="size-5" />
                        Choisir une image de couverture
                    </button>
                )}
            </div>

            {/* Extrait */}
            <div className="space-y-2">
                <Label htmlFor="article-excerpt">
                    Extrait <span className="text-muted-foreground font-normal">(optionnel)</span>
                </Label>
                <Textarea
                    id="article-excerpt"
                    value={excerpt}
                    onChange={(e) => setExcerpt(e.target.value)}
                    placeholder="Courte description affichée en aperçu…"
                    rows={3}
                    maxLength={500}
                />
                <p className="text-xs text-muted-foreground text-right">{excerpt.length} / 500</p>
            </div>

            {/* Catégories */}
            <div className="space-y-2">
                <Label>Catégories <span className="text-muted-foreground font-normal">(optionnel)</span></Label>
                <CategoryPicker urls={urls} selectedIds={categoryIds} onChange={setCategoryIds} />
            </div>

            {/* Contenu + Aperçu */}
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <Label>Contenu</Label>

                    <div className="flex items-center gap-2">
                        {/* Bouton bibliothèque (visible uniquement en mode édition) */}
                        {activeTab === 'edit' && permissions.canUploadMedia && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setPickerMode('editor')}
                                className="text-muted-foreground hover:text-foreground"
                            >
                                <ImageIcon className="size-4" />
                                Bibliothèque
                            </Button>
                        )}

                        {/* Onglets Édition / Aperçu */}
                        <div className="inline-flex rounded-md border text-xs">
                            <button
                                type="button"
                                onClick={() => setActiveTab('edit')}
                                className={`px-3 py-1.5 rounded-l-md transition-colors ${
                                    activeTab === 'edit'
                                        ? 'bg-muted font-medium'
                                        : 'hover:bg-muted/50 text-muted-foreground'
                                }`}
                            >
                                Édition
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('preview')}
                                className={`px-3 py-1.5 rounded-r-md transition-colors ${
                                    activeTab === 'preview'
                                        ? 'bg-muted font-medium'
                                        : 'hover:bg-muted/50 text-muted-foreground'
                                }`}
                            >
                                Aperçu
                            </button>
                        </div>
                    </div>
                </div>

                {contentLoaded && (
                    <>
                        <div className={activeTab === 'edit' ? 'block' : 'hidden'}>
                            <BlockEditor
                                ref={editorRef}
                                key={savedId ?? 'new'}
                                value={content}
                                onChange={setContent}
                                onUploadFile={handleUploadFile}
                            />
                        </div>

                        {activeTab === 'preview' && (
                            <div className="min-h-64 rounded-md border bg-background px-6 py-5">
                                {title.trim() && (
                                    <h1 className="text-3xl font-bold mb-6">{title}</h1>
                                )}
                                {excerpt.trim() && (
                                    <p className="text-muted-foreground italic mb-6 text-base leading-relaxed border-l-4 border-border pl-4">
                                        {excerpt}
                                    </p>
                                )}
                                <BlockRenderer content={content} coverImage={coverImage} />
                            </div>
                        )}
                    </>
                )}
            </div>

            <MediaPickerModal
                open={pickerMode !== null}
                onSelect={handleLibrarySelect}
                onCancel={() => setPickerMode(null)}
                urls={urls}
            />
        </div>
    );
}
