import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, CheckCircle2, ChevronRight, Clock, FileDown, MapPin, MessageSquare, Package, Receipt, User } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { api, getErrorMessage } from '../../utils/api';

const STATUS_LABELS = {
    pending:   { label: 'En attente',  variant: 'secondary',    icon: Clock },
    confirmed: { label: 'Confirmée',   variant: 'default',      icon: CheckCircle2 },
    shipped:   { label: 'Expédiée',    variant: 'default',      icon: Package },
    delivered: { label: 'Livrée',      variant: 'default',      icon: CheckCircle2 },
    cancelled: { label: 'Annulée',     variant: 'destructive',  icon: null },
    refunded:  { label: 'Remboursée',  variant: 'outline',      icon: null },
};

const TRANSITION_LABELS = {
    confirmed: 'Confirmer',
    shipped:   'Marquer expédiée',
    delivered: 'Marquer livrée',
    cancelled: 'Annuler',
    refunded:  'Rembourser',
};

const TRANSITION_VARIANTS = {
    confirmed: 'default',
    shipped:   'default',
    delivered: 'default',
    cancelled: 'destructive',
    refunded:  'outline',
};

function formatPrice(cents) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
}

function AddressBlock({ address, title }) {
    if (!address) return null;
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide mb-1">{title}</p>
            <p className="text-sm">{address.firstName} {address.lastName}</p>
            <p className="text-sm">{address.line1}</p>
            {address.line2 && <p className="text-sm">{address.line2}</p>}
            <p className="text-sm">{address.postalCode} {address.city}</p>
            <p className="text-sm">{address.country}</p>
        </div>
    );
}

export default function OrderDetail({ permissions = {} }) {
    const { id } = useParams();
    const navigate = useNavigate();

    const [order, setOrder]         = useState(null);
    const [loading, setLoading]     = useState(true);
    const [feedback, setFeedback]   = useState(null);
    const [transitioning, setTransitioning] = useState(false);
    const [transitionTarget, setTransitionTarget] = useState(null);
    const [transitionComment, setTransitionComment] = useState('');
    const [internalNote, setInternalNote] = useState('');
    const [savingNote, setSavingNote] = useState(false);
    const [invoice, setInvoice]           = useState(undefined); // undefined = not yet fetched
    const [generatingInvoice, setGeneratingInvoice] = useState(false);

    const fetchOrder = async () => {
        try {
            const data = await api.get(`/api/admin/commandes/${id}`);
            setOrder(data);
            setInternalNote(data.internalNote ?? '');
        } catch {
            setFeedback({ type: 'error', message: 'Impossible de charger la commande.' });
        } finally {
            setLoading(false);
        }
    };

    const fetchInvoice = async () => {
        try {
            const data = await api.get(`/api/admin/factures/commande/${id}`);
            setInvoice(data); // null = no invoice yet
        } catch {
            setInvoice(null);
        }
    };

    const handleGenerateInvoice = async () => {
        setGeneratingInvoice(true);
        setFeedback(null);
        try {
            const data = await api.post(`/api/admin/factures/commande/${id}/generer`);
            setInvoice(data);
            setFeedback({ type: 'success', message: `Facture ${data.invoiceNumber} générée.` });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setGeneratingInvoice(false);
        }
    };

    const handleInvoiceStatus = async (status) => {
        if (!invoice) return;
        try {
            const data = await api.patch(`/api/admin/factures/${invoice.id}/statut`, { status });
            setInvoice(data);
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        }
    };

    useEffect(() => { fetchOrder(); fetchInvoice(); }, [id]);

    const handleTransition = async () => {
        if (!transitionTarget) return;
        setTransitioning(true);
        setFeedback(null);
        try {
            const data = await api.post(`/api/admin/commandes/${id}/transition`, {
                status: transitionTarget,
                comment: transitionComment.trim() || null,
            });
            setOrder(data);
            setInternalNote(data.internalNote ?? '');
            setTransitionTarget(null);
            setTransitionComment('');
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setTransitioning(false);
        }
    };

    const handleSaveNote = async () => {
        setSavingNote(true);
        setFeedback(null);
        try {
            const data = await api.patch(`/api/admin/commandes/${id}/note`, { internalNote });
            setOrder(data);
            setFeedback({ type: 'success', message: 'Note enregistrée.' });
        } catch (err) {
            setFeedback({ type: 'error', message: getErrorMessage(err) });
        } finally {
            setSavingNote(false);
        }
    };

    if (loading) {
        return (
            <div className="space-y-4 animate-pulse">
                <div className="h-8 w-48 rounded bg-muted" />
                <div className="h-48 rounded bg-muted" />
            </div>
        );
    }

    if (!order) {
        return <p className="text-destructive">{feedback?.message ?? 'Commande introuvable.'}</p>;
    }

    const statusInfo = STATUS_LABELS[order.status] ?? { label: order.status, variant: 'secondary' };

    return (
        <div className="space-y-6">
            {/* En-tête */}
            <div className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" onClick={() => navigate('/commandes')}>
                        <ArrowLeft className="size-4" />
                    </Button>
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight font-mono">{order.orderNumber}</h2>
                        <div className="flex items-center gap-2 mt-1">
                            <Badge variant={statusInfo.variant}>{statusInfo.label}</Badge>
                            <span className="text-xs text-muted-foreground">{formatDate(order.createdAt)}</span>
                        </div>
                    </div>
                </div>

                {/* Actions de transition */}
                {permissions.canEditOrder && order.allowedTransitions?.length > 0 && (
                    <div className="flex items-center gap-2 shrink-0">
                        {order.allowedTransitions.map((status) => (
                            <Button
                                key={status}
                                variant={TRANSITION_VARIANTS[status] ?? 'default'}
                                size="sm"
                                onClick={() => setTransitionTarget(status)}
                            >
                                {TRANSITION_LABELS[status] ?? status}
                            </Button>
                        ))}
                    </div>
                )}
            </div>

            {feedback && (
                <p className={`text-sm ${feedback.type === 'success' ? 'text-green-600' : 'text-destructive'}`}>
                    {feedback.message}
                </p>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Colonne principale */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Articles */}
                    <div className="rounded-lg border">
                        <div className="flex items-center gap-2 px-4 py-3 border-b bg-muted/40">
                            <Package className="size-4 text-muted-foreground" />
                            <h3 className="font-medium text-sm">Articles ({order.items.length})</h3>
                        </div>
                        <div className="divide-y">
                            {order.items.map((item) => (
                                <div key={item.id} className="flex items-center justify-between px-4 py-3">
                                    <div>
                                        <p className="font-medium text-sm">{item.productName}</p>
                                        {item.variantName && (
                                            <p className="text-xs text-muted-foreground">{item.variantName}</p>
                                        )}
                                    </div>
                                    <div className="text-right text-sm">
                                        <p>{formatPrice(item.unitPrice)} × {item.quantity}</p>
                                        <p className="font-medium">{formatPrice(item.total)}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="px-4 py-3 border-t space-y-1 text-sm">
                            <div className="flex justify-between text-muted-foreground">
                                <span>Sous-total</span>
                                <span>{formatPrice(order.subtotal)}</span>
                            </div>
                            {order.discountAmount > 0 && (
                                <div className="flex justify-between text-green-600">
                                    <span>Réduction {order.promoCode && `(${order.promoCode})`}</span>
                                    <span>−{formatPrice(order.discountAmount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-muted-foreground">
                                <span>Livraison</span>
                                <span>{order.shippingAmount > 0 ? formatPrice(order.shippingAmount) : 'Offerte'}</span>
                            </div>
                            <div className="flex justify-between font-semibold text-base pt-1 border-t mt-1">
                                <span>Total</span>
                                <span>{formatPrice(order.total)}</span>
                            </div>
                        </div>
                    </div>

                    {/* Historique des statuts */}
                    <div className="rounded-lg border">
                        <div className="flex items-center gap-2 px-4 py-3 border-b bg-muted/40">
                            <Clock className="size-4 text-muted-foreground" />
                            <h3 className="font-medium text-sm">Historique</h3>
                        </div>
                        <div className="divide-y">
                            {order.statusHistory.map((h) => {
                                const s = STATUS_LABELS[h.status] ?? { label: h.status, variant: 'secondary' };
                                return (
                                    <div key={h.id} className="flex items-start gap-3 px-4 py-3">
                                        <ChevronRight className="size-4 text-muted-foreground mt-0.5 shrink-0" />
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <Badge variant={s.variant} className="text-xs">{s.label}</Badge>
                                                <span className="text-xs text-muted-foreground">{formatDate(h.createdAt)}</span>
                                            </div>
                                            {h.comment && <p className="text-sm text-muted-foreground mt-0.5">{h.comment}</p>}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Colonne latérale */}
                <div className="space-y-4">
                    {/* Client */}
                    <div className="rounded-lg border p-4 space-y-3">
                        <div className="flex items-center gap-2">
                            <User className="size-4 text-muted-foreground" />
                            <h3 className="font-medium text-sm">Client</h3>
                        </div>
                        <div className="text-sm space-y-1">
                            <p className="font-medium">{order.customer.fullName}</p>
                            <p className="text-muted-foreground">{order.customer.email}</p>
                            {order.customer.phone && <p className="text-muted-foreground">{order.customer.phone}</p>}
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            className="w-full"
                            onClick={() => navigate(`/customers/${order.customer.id}`)}
                        >
                            Voir la fiche client
                        </Button>
                    </div>

                    {/* Adresses */}
                    <div className="rounded-lg border p-4 space-y-4">
                        <div className="flex items-center gap-2">
                            <MapPin className="size-4 text-muted-foreground" />
                            <h3 className="font-medium text-sm">Adresses</h3>
                        </div>
                        <AddressBlock address={order.shippingAddress} title="Livraison" />
                        {order.billingAddress && (
                            <AddressBlock address={order.billingAddress} title="Facturation" />
                        )}
                        {!order.billingAddress && (
                            <p className="text-xs text-muted-foreground">Facturation = Livraison</p>
                        )}
                    </div>

                    {/* Note client */}
                    {order.customerNote && (
                        <div className="rounded-lg border p-4 space-y-2">
                            <div className="flex items-center gap-2">
                                <MessageSquare className="size-4 text-muted-foreground" />
                                <h3 className="font-medium text-sm">Note du client</h3>
                            </div>
                            <p className="text-sm text-muted-foreground">{order.customerNote}</p>
                        </div>
                    )}

                    {/* Facture */}
                    <div className="rounded-lg border p-4 space-y-3">
                        <div className="flex items-center gap-2">
                            <Receipt className="size-4 text-muted-foreground" />
                            <h3 className="font-medium text-sm">Facture</h3>
                        </div>

                        {invoice === undefined && (
                            <p className="text-sm text-muted-foreground">Chargement…</p>
                        )}

                        {invoice === null && (
                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground">Aucune facture générée.</p>
                                <Button
                                    size="sm"
                                    className="w-full"
                                    onClick={handleGenerateInvoice}
                                    disabled={generatingInvoice}
                                >
                                    {generatingInvoice ? 'Génération…' : 'Générer la facture'}
                                </Button>
                            </div>
                        )}

                        {invoice && (
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-mono text-sm font-medium">{invoice.invoiceNumber}</span>
                                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                                        invoice.status === 'paid'      ? 'bg-green-100 text-green-700' :
                                        invoice.status === 'cancelled' ? 'bg-red-100 text-red-700' :
                                        'bg-yellow-100 text-yellow-700'
                                    }`}>{invoice.statusLabel}</span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Émise le {new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(invoice.issuedAt))}
                                </p>
                                <p className="text-sm font-semibold">{formatPrice(invoice.totalTtc)}</p>
                                <a
                                    href={`/api/admin/factures/${invoice.id}/pdf`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center justify-center gap-2 w-full rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-accent transition-colors"
                                >
                                    <FileDown className="size-4" />
                                    Télécharger le PDF
                                </a>
                                {invoice.status === 'pending' && (
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="flex-1 text-green-700 border-green-200 hover:bg-green-50"
                                            onClick={() => handleInvoiceStatus('paid')}
                                        >
                                            Marquer payée
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="flex-1 text-destructive"
                                            onClick={() => handleInvoiceStatus('cancelled')}
                                        >
                                            Annuler
                                        </Button>
                                    </div>
                                )}
                                {invoice.status === 'cancelled' && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="w-full"
                                        onClick={handleGenerateInvoice}
                                        disabled={generatingInvoice}
                                    >
                                        Regénérer
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Note interne */}
                    {permissions.canEditOrder && (
                        <div className="rounded-lg border p-4 space-y-3">
                            <h3 className="font-medium text-sm">Note interne</h3>
                            <Textarea
                                value={internalNote}
                                onChange={(e) => setInternalNote(e.target.value)}
                                placeholder="Note visible uniquement par l'équipe…"
                                rows={3}
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                className="w-full"
                                onClick={handleSaveNote}
                                disabled={savingNote}
                            >
                                {savingNote ? 'Enregistrement…' : 'Enregistrer la note'}
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            {/* Dialog confirmation transition */}
            <Dialog open={transitionTarget !== null} onOpenChange={(open) => !open && setTransitionTarget(null)}>
                {transitionTarget && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{TRANSITION_LABELS[transitionTarget] ?? transitionTarget}</DialogTitle>
                        </DialogHeader>
                        <div className="space-y-2 py-2">
                            <Textarea
                                value={transitionComment}
                                onChange={(e) => setTransitionComment(e.target.value)}
                                placeholder="Commentaire (optionnel)…"
                                rows={3}
                            />
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setTransitionTarget(null)} disabled={transitioning}>
                                Annuler
                            </Button>
                            <Button
                                variant={TRANSITION_VARIANTS[transitionTarget] ?? 'default'}
                                onClick={handleTransition}
                                disabled={transitioning}
                            >
                                {transitioning ? 'En cours…' : 'Confirmer'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>
        </div>
    );
}
