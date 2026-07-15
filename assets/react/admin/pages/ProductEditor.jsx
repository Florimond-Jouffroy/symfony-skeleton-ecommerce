import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Globe, GlobeLock, GripVertical, ImageIcon, Plus, Save, Trash2, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import BlockEditor from '../components/BlockEditor';
import MediaPickerModal from '../components/MediaPickerModal';
import { api, getErrorMessage } from '../../utils/api';

function formatPrice(cents) {
    return cents > 0 ? (cents / 100).toFixed(2) : '';
}

function parsePriceToCents(str) {
    const val = parseFloat(String(str).replace(',', '.'));
    return isNaN(val) || val < 0 ? 0 : Math.round(val * 100);
}

function CategoryPicker({ urls, selectedIds, onChange }) {
    const [categories, setCategories] = useState([]);
    useEffect(() => {
        api.get(urls.productCategories ?? '/api/admin/categories-produits').then(setCategories).catch(() => {});
    }, [urls.productCategories]);

    const toggle = (id) => onChange(selectedIds.includes(id) ? selectedIds.filter((x) => x !== id) : [...selectedIds, id]);

    if (categories.length === 0) return <p className="text-sm text-muted-foreground">Aucune catégorie disponible.</p>;

    return (
        <div className="flex flex-wrap gap-2">
            {categories.map((cat) => {
                const active = selectedIds.includes(cat.id);
                return (
                    <button
                        key={cat.id}
                        type="button"
                        onClick={() => toggle(cat.id)}
                        className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                            active ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:text-foreground hover:border-foreground/40'
                        }`}
                    >
                        {cat.name}
                    </button>
                );
            })}
        </div>
    );
}

function VariantRow({ variant, index, onChange, onRemove }) {
    const update = (field, value) => onChange(index, { ...variant, [field]: value });

    return (
        <div className="grid grid-cols-[auto_1fr_1fr_1fr_1fr_auto] items-center gap-2 py-2 border-b last:border-0">
            <GripVertical className="size-4 text-muted-foreground cursor-grab" />
            <Input
                value={variant.name}
                onChange={(e) => update('name', e.target.value)}
                placeholder="Nom (ex : Rouge / L)"
                className="text-sm"
            />
            <Input
                value={variant.sku ?? ''}
                onChange={(e) => update('sku', e.target.value)}
                placeholder="SKU (optionnel)"
                className="text-sm"
            />
            <Input
                value={variant.priceOverride !== null && variant.priceOverride !== undefined ? formatPrice(variant.priceOverride) : ''}
                onChange={(e) => update('priceOverride', e.target.value === '' ? null : parsePriceToCents(e.target.value))}
                placeholder="Prix (€)"
                className="text-sm"
                type="number"
                step="0.01"
                min="0"
            />
            <Input
                value={variant.stock}
                onChange={(e) => update('stock', parseInt(e.target.value, 10) || 0)}
                placeholder="Stock"
                className="text-sm"
                type="number"
                min="0"
            />
            <Button variant="ghost" size="icon" className="size-8 text-muted-foreground hover:text-destructive" onClick={() => onRemove(index)}>
                <X className="size-4" />
            </Button>
        </div>
    );
}

export default function ProductEditor({ permissions = {}, urls = {} }) {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEdit = id !== undefined;

    const [name, setName]             = useState('');
    const [priceStr, setPriceStr]     = useState('');
    const [compareStr, setCompareStr] = useState('');
    const [stock, setStock]           = useState(0);
    const [lowStockThreshold, setLowStockThreshold] = useState(5);
    const [hasVariants, setHasVariants] = useState(false);
    const [variants, setVariants]     = useState([]);
    const [images, setImages]         = useState([]);
    const [content, setContent]       = useState({ blocks: [] });
    const [status, setStatus]         = useState('draft');
    const [categoryIds, setCategoryIds] = useState([]);

    const [loading, setLoading]       = useState(isEdit);
    const [contentLoaded, setContentLoaded] = useState(!isEdit);
    const [saving, setSaving]         = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [feedback, setFeedback]     = useState(null);
    const [savedId, setSavedId]       = useState(id ? parseInt(id, 10) : null);
    const [pickerOpen, setPickerOpen] = useState(false);

    const editorRef = useRef(null);

    useEffect(() => {
        if (!isEdit) return;
        api.get(`/api/admin/produits/${id}`)
            .then((data) => {
                setName(data.name);
                setPriceStr(formatPrice(data.price));
                setCompareStr(data.compareAtPrice ? formatPrice(data.compareAtPrice) : '');
                setStock(data.stock);
                setLowStockThreshold(data.lowStockThreshold ?? 5);
                setHasVariants(data.hasVariants);
                setVariants(data.variants ?? []);
                setImages(data.images ?? []);
                setContent(data.description ? { blocks: data.description.blocks ?? [] } : { blocks: [] });
                setStatus(data.status);
                setCategoryIds((data.categories ?? []).map((c) => c.id));
                setContentLoaded(true);
            })
            .catch(() => setFeedback({ type: 'error', message: 'Impossible de charger le produit.' }))
            .finally(() => setLoading(false));
    }, [id]);

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

    const handleImageSelect = (url) => {
        setImages((prev) => [...prev, { url, alt: null, position: prev.length }]);
        setPickerOpen(false);
    };

    const removeImage = (index) => setImages((prev) => prev.filter((_, i) => i !== index));

    const addVariant = () => setVariants((prev) => [
        ...prev,
        { id: null, name: '', sku: null, priceOverride: null, stock: 0, lowStockThreshold: 5, position: prev.length, attributes: null, isActive: true },
    ]);

    const updateVariant = (index, data) => setVariants((prev) => prev.map((v, i) => (i === index ? data : v)));
    const removeVariant = (index) => setVariants((prev) => prev.filter((_, i) => i !== index));

    const buildPayload = () => ({
        name: name.trim(),
        description: content,
        price: parsePriceToCents(priceStr),
        compareAtPrice: compareStr.trim() ? parsePriceToCents(compareStr) : null,
        stock,
        lowStockThreshold,
        hasVariants,
        categoryIds,
        images: images.map((img, i) => ({ url: img.url, alt: img.alt ?? null, position: i })),
        variants: hasVariants ? variants.map((v, i) => ({ ...v, position: i })) : [],
    });

    const handleSave = async () => {
        if (!name.trim()) { setFeedback({ type: 'error', message: 'Le nom est obligatoire.' }); return; }
        setSaving(true);
        setFeedback(null);
        try {
            const payload = buildPayload();
            if (savedId) {
                await api.put(`/api/admin/produits/${savedId}`, payload);
                setFeedback({ type: 'success', message: 'Produit enregistré.' });
            } else {
                const created = await api.post(urls.products ?? '/api/admin/produits', payload);
                setSavedId(created.id);
                setStatus(created.status);
                navigate(`/produits/${created.id}/modifier`, { replace: true });
                setFeedback({ type: 'success', message: 'Produit créé.' });
            }
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Erreur lors de la sauvegarde.') });
        } finally {
            setSaving(false);
        }
    };

    const handleTogglePublish = async () => {
        if (!savedId) { setFeedback({ type: 'error', message: "Enregistrez d'abord le produit." }); return; }
        setPublishing(true);
        setFeedback(null);
        const endpoint = status === 'published'
            ? `/api/admin/produits/${savedId}/depublier`
            : `/api/admin/produits/${savedId}/publier`;
        try {
            const updated = await api.post(endpoint);
            setStatus(updated.status);
            setFeedback({ type: 'success', message: updated.status === 'published' ? 'Produit publié.' : 'Produit repassé en brouillon.' });
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
        <div className="space-y-8">
            {/* En-tête */}
            <div className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" onClick={() => navigate('/produits')}>
                        <ArrowLeft className="size-4" />
                    </Button>
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            {isEdit ? 'Modifier le produit' : 'Nouveau produit'}
                        </h2>
                        <Badge variant={status === 'published' ? 'default' : 'secondary'} className="mt-1">
                            {status === 'published' ? 'Publié' : 'Brouillon'}
                        </Badge>
                    </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    {permissions.canPublishProduct && savedId && (
                        <Button variant="outline" onClick={handleTogglePublish} disabled={publishing || saving}>
                            {status === 'published' ? <><GlobeLock className="size-4" /> Dépublier</> : <><Globe className="size-4" /> Publier</>}
                        </Button>
                    )}
                    {(permissions.canCreateProduct || permissions.canEditProduct) && (
                        <Button onClick={handleSave} disabled={saving || publishing}>
                            <Save className="size-4" />
                            {saving ? 'Enregistrement…' : 'Enregistrer'}
                        </Button>
                    )}
                </div>
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>{feedback.message}</p>
            )}

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                {/* Colonne principale */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Nom */}
                    <div className="space-y-2">
                        <Label htmlFor="product-name">Nom du produit</Label>
                        <Input
                            id="product-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Nom du produit"
                            className="text-lg"
                        />
                    </div>

                    {/* Description */}
                    <div className="space-y-2">
                        <Label>Description</Label>
                        {contentLoaded && (
                            <BlockEditor
                                ref={editorRef}
                                key={savedId ?? 'new'}
                                value={content}
                                onChange={setContent}
                                onUploadFile={handleUploadFile}
                            />
                        )}
                    </div>

                    {/* Images */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label>Images</Label>
                            <Button type="button" variant="ghost" size="sm" onClick={() => setPickerOpen(true)} className="text-muted-foreground">
                                <ImageIcon className="size-4" /> Ajouter depuis la bibliothèque
                            </Button>
                        </div>
                        {images.length === 0 ? (
                            <button
                                type="button"
                                onClick={() => setPickerOpen(true)}
                                className="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border bg-muted/30 py-10 text-sm text-muted-foreground hover:bg-muted/60 hover:text-foreground transition-colors"
                            >
                                <ImageIcon className="size-5" /> Ajouter des images
                            </button>
                        ) : (
                            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                                {images.map((img, i) => (
                                    <div key={i} className="relative group aspect-square rounded-md overflow-hidden border bg-muted">
                                        <img src={img.url} alt={img.alt ?? ''} className="w-full h-full object-cover" />
                                        {i === 0 && (
                                            <span className="absolute top-1 left-1 text-[10px] bg-black/60 text-white rounded px-1">
                                                Principale
                                            </span>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => removeImage(i)}
                                            className="absolute top-1 right-1 size-5 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={() => setPickerOpen(true)}
                                    className="aspect-square rounded-md border-2 border-dashed border-border flex items-center justify-center text-muted-foreground hover:text-foreground hover:border-foreground/40 transition-colors"
                                >
                                    <Plus className="size-5" />
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Variantes */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <Label>Variantes</Label>
                                <p className="text-xs text-muted-foreground mt-0.5">Tailles, couleurs, etc.</p>
                            </div>
                            <Switch checked={hasVariants} onCheckedChange={setHasVariants} />
                        </div>

                        {hasVariants && (
                            <div className="rounded-md border">
                                <div className="grid grid-cols-[auto_1fr_1fr_1fr_1fr_auto] gap-2 px-3 py-2 bg-muted/50 text-xs font-medium text-muted-foreground border-b">
                                    <span />
                                    <span>Nom</span>
                                    <span>SKU</span>
                                    <span>Prix (€)</span>
                                    <span>Stock</span>
                                    <span />
                                </div>
                                <div className="px-3">
                                    {variants.map((v, i) => (
                                        <VariantRow key={i} variant={v} index={i} onChange={updateVariant} onRemove={removeVariant} />
                                    ))}
                                </div>
                                <div className="px-3 py-2 border-t">
                                    <Button type="button" variant="ghost" size="sm" onClick={addVariant} className="text-muted-foreground">
                                        <Plus className="size-4" /> Ajouter une variante
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Colonne latérale */}
                <div className="space-y-6">
                    {/* Prix */}
                    <div className="rounded-lg border p-4 space-y-4">
                        <h3 className="font-medium text-sm">Tarification</h3>
                        <div className="space-y-2">
                            <Label htmlFor="product-price">Prix (€)</Label>
                            <Input
                                id="product-price"
                                value={priceStr}
                                onChange={(e) => setPriceStr(e.target.value)}
                                placeholder="0.00"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="product-compare">Prix barré (€) <span className="text-muted-foreground font-normal">(optionnel)</span></Label>
                            <Input
                                id="product-compare"
                                value={compareStr}
                                onChange={(e) => setCompareStr(e.target.value)}
                                placeholder="0.00"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </div>
                    </div>

                    {/* Stock (simple) */}
                    {!hasVariants && (
                        <div className="rounded-lg border p-4 space-y-4">
                            <h3 className="font-medium text-sm">Stock</h3>
                            <div className="space-y-2">
                                <Label htmlFor="product-stock">Quantité</Label>
                                <Input
                                    id="product-stock"
                                    value={stock}
                                    onChange={(e) => setStock(parseInt(e.target.value, 10) || 0)}
                                    type="number"
                                    min="0"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="product-threshold">Seuil d'alerte</Label>
                                <Input
                                    id="product-threshold"
                                    value={lowStockThreshold}
                                    onChange={(e) => setLowStockThreshold(parseInt(e.target.value, 10) || 0)}
                                    type="number"
                                    min="0"
                                />
                                <p className="text-xs text-muted-foreground">Alerte stock bas en dessous de ce seuil.</p>
                            </div>
                        </div>
                    )}

                    {/* Catégories */}
                    <div className="rounded-lg border p-4 space-y-3">
                        <h3 className="font-medium text-sm">Catégories</h3>
                        <CategoryPicker urls={urls} selectedIds={categoryIds} onChange={setCategoryIds} />
                    </div>
                </div>
            </div>

            <MediaPickerModal
                open={pickerOpen}
                onSelect={handleImageSelect}
                onCancel={() => setPickerOpen(false)}
                urls={urls}
            />
        </div>
    );
}
