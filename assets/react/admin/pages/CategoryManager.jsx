import React, { useEffect, useState } from 'react';
import { Plus, Tag, Trash2 } from 'lucide-react';
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
import { api, getErrorMessage } from '../../utils/api';

export default function CategoryManager({ permissions = {}, urls = {} }) {
    const [categories, setCategories] = useState([]);
    const [loading, setLoading]       = useState(true);
    const [newName, setNewName]       = useState('');
    const [creating, setCreating]     = useState(false);
    const [feedback, setFeedback]     = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting]     = useState(false);

    const fetchCategories = async () => {
        try {
            const data = await api.get(urls.categories ?? '/api/admin/categories');
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
            await api.post(urls.categories ?? '/api/admin/categories', { name });
            setNewName('');
            await fetchCategories();
            setFeedback({ type: 'success', message: `Catégorie « ${name} » créée.` });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err, 'Erreur lors de la création.') });
        } finally {
            setCreating(false);
        }
    };

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        setFeedback(null);
        try {
            await api.delete(`/api/admin/categories/${deleteTarget.id}`);
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

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Catégories</h2>
                <p className="text-muted-foreground">Organisez vos articles par catégorie.</p>
            </div>

            {permissions.canCreateCategory && (
                <form onSubmit={handleCreate} className="flex items-end gap-3 max-w-sm">
                    <div className="flex-1 space-y-1.5">
                        <Label htmlFor="cat-name">Nouvelle catégorie</Label>
                        <Input
                            id="cat-name"
                            value={newName}
                            onChange={(e) => setNewName(e.target.value)}
                            placeholder="Ex : Technologie"
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
                            </div>
                            <div className="flex items-center gap-3">
                                <Badge variant="secondary">
                                    {cat.articleCount} article{cat.articleCount !== 1 ? 's' : ''}
                                </Badge>
                                {permissions.canDeleteCategory && (
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

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                {deleteTarget && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Supprimer la catégorie</DialogTitle>
                            <DialogDescription>
                                La catégorie <strong>{deleteTarget.name}</strong> sera supprimée.
                                {deleteTarget.articleCount > 0 && (
                                    <> Elle est utilisée par <strong>{deleteTarget.articleCount} article{deleteTarget.articleCount > 1 ? 's' : ''}</strong> qui ne seront pas supprimés.</>
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDeleteTarget(null)} disabled={deleting}>
                                Annuler
                            </Button>
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
