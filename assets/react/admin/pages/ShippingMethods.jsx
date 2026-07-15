import React, { useEffect, useState } from 'react';
import { Package, Pencil, Plus, Trash2, Truck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { api, getErrorMessage } from '../../utils/api';

const EMPTY_FORM = { name: '', description: '', price: '', freeAboveAmount: '', isActive: true, position: 0 };

function formatPrice(cents) {
    return cents === 0
        ? 'Offert'
        : (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

export default function ShippingMethods({ urls = {}, permissions = {} }) {
    const [methods, setMethods]     = useState([]);
    const [loading, setLoading]     = useState(true);
    const [feedback, setFeedback]   = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing]     = useState(null);
    const [form, setForm]           = useState(EMPTY_FORM);
    const [saving, setSaving]       = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting]   = useState(false);

    const base = urls.shippingMethods ?? '/api/admin/livraison';

    const fetchMethods = async () => {
        try {
            const data = await api.get(base);
            setMethods(data);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchMethods(); }, []);

    const openCreate = () => {
        setEditing(null);
        setForm(EMPTY_FORM);
        setModalOpen(true);
    };

    const openEdit = (method) => {
        setEditing(method);
        setForm({
            name:            method.name,
            description:     method.description ?? '',
            price:           String(method.price),
            freeAboveAmount: method.freeAboveAmount !== null ? String(method.freeAboveAmount) : '',
            isActive:        method.isActive,
            position:        method.position,
        });
        setModalOpen(true);
    };

    const handleSave = async () => {
        const payload = {
            name:            form.name.trim(),
            description:     form.description.trim() || null,
            price:           parseInt(form.price, 10) || 0,
            freeAboveAmount: form.freeAboveAmount !== '' ? parseInt(form.freeAboveAmount, 10) : null,
            isActive:        form.isActive,
            position:        parseInt(form.position, 10) || 0,
        };

        if (!payload.name) {
            setFeedback({ type: 'error', message: 'Le nom est obligatoire.' });
            return;
        }

        setSaving(true);
        setFeedback(null);
        try {
            if (editing) {
                await api.put(`${base}/${editing.id}`, payload);
            } else {
                await api.post(base, payload);
            }
            setModalOpen(false);
            await fetchMethods();
            setFeedback({ type: 'success', message: editing ? 'Méthode mise à jour.' : 'Méthode créée.' });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deleteTarget) return;
        setDeleting(true);
        try {
            await api.delete(`${base}/${deleteTarget.id}`);
            setDeleteTarget(null);
            await fetchMethods();
            setFeedback({ type: 'success', message: 'Méthode supprimée.' });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setDeleting(false);
        }
    };

    const setField = (key, value) => setForm((f) => ({ ...f, [key]: value }));

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                        <Truck className="h-6 w-6" />
                        Livraison
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Configurez les méthodes de livraison proposées lors de la commande.
                    </p>
                </div>
                {permissions.canCreateShipping !== false && (
                    <Button onClick={openCreate} className="gap-1.5">
                        <Plus className="h-4 w-4" />
                        Nouvelle méthode
                    </Button>
                )}
            </div>

            {/* Feedback */}
            {feedback && (
                <div className={`rounded-lg px-4 py-3 text-sm ${feedback.type === 'error' ? 'bg-destructive/10 text-destructive' : 'bg-green-50 text-green-700'}`}>
                    {feedback.message}
                </div>
            )}

            {/* List */}
            {loading ? (
                <div className="space-y-3 animate-pulse">
                    {[1, 2].map((i) => <div key={i} className="h-20 rounded-xl bg-muted" />)}
                </div>
            ) : methods.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border py-16 text-center">
                    <Package className="mx-auto mb-3 h-10 w-10 text-muted-foreground/30" />
                    <p className="text-sm text-muted-foreground">Aucune méthode de livraison configurée.</p>
                </div>
            ) : (
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <ul className="divide-y divide-border">
                        {methods.map((method) => (
                            <li key={method.id} className="flex items-center gap-4 px-5 py-4">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium">{method.name}</span>
                                        <Badge variant={method.isActive ? 'default' : 'secondary'}>
                                            {method.isActive ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                    {method.description && (
                                        <p className="text-xs text-muted-foreground mt-0.5 truncate">{method.description}</p>
                                    )}
                                    <div className="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                                        <span>{formatPrice(method.price)}</span>
                                        {method.freeAboveAmount !== null && (
                                            <span>· Gratuit dès {formatPrice(method.freeAboveAmount)}</span>
                                        )}
                                        {method.position > 0 && (
                                            <span>· Position {method.position}</span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex items-center gap-1 shrink-0">
                                    {permissions.canEditShipping !== false && (
                                        <Button variant="ghost" size="icon" onClick={() => openEdit(method)}>
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                    )}
                                    {permissions.canDeleteShipping !== false && (
                                        <Button variant="ghost" size="icon" onClick={() => setDeleteTarget(method)}
                                            className="text-muted-foreground hover:text-destructive hover:bg-destructive/10">
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Create/Edit modal */}
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Modifier la méthode' : 'Nouvelle méthode de livraison'}</DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="sm-name">Nom *</Label>
                            <Input id="sm-name" value={form.name} onChange={(e) => setField('name', e.target.value)} placeholder="Colissimo, Chronopost…" />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="sm-desc">Description</Label>
                            <Textarea id="sm-desc" value={form.description} onChange={(e) => setField('description', e.target.value)}
                                placeholder="Livraison en 2-3 jours ouvrés" rows={2} />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="sm-price">Prix (centimes) *</Label>
                                <Input id="sm-price" type="number" min="0" value={form.price}
                                    onChange={(e) => setField('price', e.target.value)} placeholder="490" />
                                <p className="text-xs text-muted-foreground">490 = 4,90 €</p>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="sm-free">Gratuit dès (centimes)</Label>
                                <Input id="sm-free" type="number" min="0" value={form.freeAboveAmount}
                                    onChange={(e) => setField('freeAboveAmount', e.target.value)} placeholder="5000" />
                                <p className="text-xs text-muted-foreground">Vide = jamais gratuit</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="sm-pos">Position</Label>
                                <Input id="sm-pos" type="number" min="0" value={form.position}
                                    onChange={(e) => setField('position', e.target.value)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Statut</Label>
                                <button
                                    type="button"
                                    onClick={() => setField('isActive', !form.isActive)}
                                    className={`flex h-9 w-full items-center justify-center rounded-md border text-sm font-medium transition-colors ${form.isActive ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-accent'}`}
                                >
                                    {form.isActive ? 'Active' : 'Inactive'}
                                </button>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>Annuler</Button>
                        <Button onClick={handleSave} disabled={saving}>
                            {saving ? 'Enregistrement…' : 'Enregistrer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete confirm modal */}
            <Dialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Supprimer la méthode</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Supprimer <strong>{deleteTarget?.name}</strong> ? Cette action est irréversible.
                    </p>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>Annuler</Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={deleting}>
                            {deleting ? 'Suppression…' : 'Supprimer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
