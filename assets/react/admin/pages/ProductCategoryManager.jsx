import React, { useEffect, useState } from 'react';
import { Pencil, Plus, Tag, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { api, getErrorMessage } from '../../utils/api';

const TAX_RATE_OPTIONS = [
    { value: '',    label: 'Taux par défaut global' },
    { value: '20',  label: '20% — Taux normal' },
    { value: '10',  label: '10% — Taux intermédiaire' },
    { value: '5.5', label: '5,5% — Taux réduit' },
    { value: '2.1', label: '2,1% — Taux super-réduit' },
    { value: '0',   label: '0% — Exonéré' },
];

function TaxRateBadge({ taxRate }) {
    if (taxRate === null || taxRate === undefined) {
        return <Badge variant="outline" className="text-xs font-normal text-muted-foreground">TVA par défaut</Badge>;
    }
    return <Badge variant="secondary" className="text-xs font-mono">TVA {taxRate}%</Badge>;
}

export default function ProductCategoryManager({ permissions = {}, urls = {} }) {
    const [categories, setCategories] = useState([]);
    const [loading, setLoading]       = useState(true);
    const [newName, setNewName]       = useState('');
    const [creating, setCreating]     = useState(false);
    const [feedback, setFeedback]     = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting]     = useState(false);
    const [editTarget, setEditTarget] = useState(null);
    const [editName, setEditName]     = useState('');
    const [editTaxRate, setEditTaxRate] = useState('');
    const [saving, setSaving]         = useState(false);

    const fetchCategories = async () => {
        try {
            const data = await api.get(urls.productCategories ?? '/api/admin/categories-produits');
            setCategories(data);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchCategories(); }, []);

    const handleCreate = async (e) => {
        e.preventDefault();
        const name = newName.trim();
        if (!name) return;
        setCreating(true);
        setFeedback(null);
        try {
            await api.post(urls.productCategories ?? '/api/admin/categories-produits', { name });
            setNewName('');
            await fetchCategories();
            setFeedback({ type: 'success', message: `Catégorie « ${name} » créée.` });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setCreating(false);
        }
    };

    const handleEdit = async () => {
        if (!editTarget) return;
        const name = editName.trim();
        if (!name) return;
        setSaving(true);
        setFeedback(null);
        try {
            const taxRate = editTaxRate !== '' ? editTaxRate : null;
            await api.put(`/api/admin/categories-produits/${editTarget.id}`, { name, taxRate });
            setEditTarget(null);
            await fetchCategories();
            setFeedback({ type: 'success', message: `Catégorie « ${name} » mise à jour.` });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        setFeedback(null);
        try {
            await api.delete(`/api/admin/categories-produits/${deleteTarget.id}`);
            setFeedback({ type: 'success', message: `Catégorie « ${deleteTarget.name} » supprimée.` });
            setDeleteTarget(null);
            await fetchCategories();
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
            setDeleteTarget(null);
        } finally {
            setDeleting(false);
        }
    };

    const openEdit = (cat) => {
        setEditTarget(cat);
        setEditName(cat.name);
        setEditTaxRate(cat.taxRate !== null && cat.taxRate !== undefined ? String(cat.taxRate) : '');
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Catégories produits</h2>
                <p className="text-muted-foreground">Organisez votre catalogue par catégorie et configurez le taux de TVA par type de produit.</p>
            </div>

            {permissions.canCreateProductCategory && (
                <form onSubmit={handleCreate} className="flex items-end gap-3 max-w-sm">
                    <div className="flex-1 space-y-1.5">
                        <Label htmlFor="pc-name">Nouvelle catégorie</Label>
                        <Input
                            id="pc-name"
                            value={newName}
                            onChange={(e) => setNewName(e.target.value)}
                            placeholder="Ex : Vêtements"
                            disabled={creating}
                        />
                    </div>
                    <Button type="submit" disabled={creating || !newName.trim()}>
                        <Plus className="size-4" />
                        {creating ? 'Création…' : 'Ajouter'}
                    </Button>
                </form>
            )}

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            {loading ? (
                <div className="space-y-2">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className="h-12 rounded-md bg-muted animate-pulse" />
                    ))}
                </div>
            ) : categories.length === 0 ? (
                <div className="flex flex-col items-center gap-3 py-16 text-muted-foreground">
                    <Tag className="size-10 opacity-30" />
                    <p className="text-sm">Aucune catégorie pour l'instant.</p>
                </div>
            ) : (
                <div className="rounded-md border divide-y">
                    {categories.map((cat) => (
                        <div key={cat.id} className="flex items-center justify-between px-4 py-3">
                            <div className="flex items-center gap-3">
                                <span className="font-medium">{cat.name}</span>
                                <span className="text-xs text-muted-foreground font-mono">{cat.slug}</span>
                                {!cat.isActive && <Badge variant="outline" className="text-xs">Inactif</Badge>}
                            </div>
                            <div className="flex items-center gap-3">
                                <TaxRateBadge taxRate={cat.taxRate} />
                                <Badge variant="secondary">
                                    {cat.productCount} produit{cat.productCount !== 1 ? 's' : ''}
                                </Badge>
                                {permissions.canEditProductCategory && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 text-muted-foreground hover:text-foreground"
                                        onClick={() => openEdit(cat)}
                                    >
                                        <Pencil className="size-4" />
                                    </Button>
                                )}
                                {permissions.canDeleteProductCategory && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 text-muted-foreground hover:text-destructive"
                                        onClick={() => setDeleteTarget(cat)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Edit dialog */}
            <Dialog open={editTarget !== null} onOpenChange={(open) => !open && setEditTarget(null)}>
                {editTarget && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Modifier la catégorie</DialogTitle>
                        </DialogHeader>
                        <div className="space-y-4 py-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="edit-cat-name">Nom</Label>
                                <Input
                                    id="edit-cat-name"
                                    value={editName}
                                    onChange={(e) => setEditName(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && handleEdit()}
                                    disabled={saving}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="edit-cat-tax">Taux de TVA</Label>
                                <Select
                                    value={editTaxRate}
                                    onValueChange={setEditTaxRate}
                                    disabled={saving}
                                >
                                    <SelectTrigger id="edit-cat-tax">
                                        <SelectValue placeholder="Taux par défaut global" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {TAX_RATE_OPTIONS.map(({ value, label }) => (
                                            <SelectItem key={value} value={value}>{label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Laissez sur « Taux par défaut global » pour hériter du taux configuré dans les Paramètres.
                                </p>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setEditTarget(null)} disabled={saving}>Annuler</Button>
                            <Button onClick={handleEdit} disabled={saving || !editName.trim()}>
                                {saving ? 'Enregistrement…' : 'Enregistrer'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>

            {/* Delete dialog */}
            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                {deleteTarget && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Supprimer la catégorie</DialogTitle>
                            <DialogDescription>
                                La catégorie <strong>{deleteTarget.name}</strong> sera supprimée.
                                {deleteTarget.productCount > 0 && (
                                    <> Les <strong>{deleteTarget.productCount} produit{deleteTarget.productCount > 1 ? 's' : ''}</strong> associés ne seront pas supprimés.</>
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDeleteTarget(null)} disabled={deleting}>Annuler</Button>
                            <Button variant="destructive" onClick={handleDelete} disabled={deleting}>
                                {deleting ? 'Suppression…' : 'Supprimer'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>
        </div>
    );
}
