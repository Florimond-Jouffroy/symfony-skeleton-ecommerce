import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FileDown } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { api } from '../../utils/api';

const STATUS_VARIANTS = {
    pending:   'secondary',
    paid:      'default',
    cancelled: 'destructive',
};

const STATUS_LABELS = {
    pending:   'En attente',
    paid:      'Payée',
    cancelled: 'Annulée',
};

function formatPrice(cents) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(iso));
}

export default function InvoicesList({ urls = {}, permissions = {} }) {
    const navigate = useNavigate();
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading]   = useState(true);

    useEffect(() => {
        api.get(urls.invoices ?? '/api/admin/factures')
            .then(setInvoices)
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div className="space-y-4 animate-pulse">
                <div className="h-8 w-48 rounded bg-muted" />
                <div className="h-64 rounded bg-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-2xl font-bold tracking-tight">Factures</h2>
                <p className="text-sm text-muted-foreground mt-1">{invoices.length} facture{invoices.length !== 1 ? 's' : ''}</p>
            </div>

            {invoices.length === 0 ? (
                <div className="rounded-lg border p-12 text-center text-muted-foreground">
                    Aucune facture pour l'instant. Les factures sont générées automatiquement selon les paramètres configurés.
                </div>
            ) : (
                <div className="rounded-lg border overflow-hidden">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40">
                                <th className="text-left px-4 py-3 font-medium text-muted-foreground">Facture</th>
                                <th className="text-left px-4 py-3 font-medium text-muted-foreground">Commande</th>
                                <th className="text-left px-4 py-3 font-medium text-muted-foreground">Client</th>
                                <th className="text-left px-4 py-3 font-medium text-muted-foreground">Date</th>
                                <th className="text-right px-4 py-3 font-medium text-muted-foreground">Total TTC</th>
                                <th className="text-center px-4 py-3 font-medium text-muted-foreground">Statut</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {invoices.map((inv) => (
                                <tr key={inv.id} className="hover:bg-muted/20 transition-colors">
                                    <td className="px-4 py-3 font-mono font-medium">{inv.invoiceNumber}</td>
                                    <td className="px-4 py-3">
                                        {permissions.canViewOrders !== false ? (
                                            <button
                                                className="text-primary hover:underline font-mono text-xs"
                                                onClick={() => navigate(`/commandes/${inv.order.id}`)}
                                            >
                                                {inv.order.orderNumber}
                                            </button>
                                        ) : (
                                            <span className="font-mono text-xs">{inv.order.orderNumber}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">{inv.order.customer.fullName}</p>
                                        <p className="text-xs text-muted-foreground">{inv.order.customer.email}</p>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">{formatDate(inv.issuedAt)}</td>
                                    <td className="px-4 py-3 text-right font-medium">{formatPrice(inv.totalTtc)}</td>
                                    <td className="px-4 py-3 text-center">
                                        <Badge variant={STATUS_VARIANTS[inv.status] ?? 'secondary'}>
                                            {STATUS_LABELS[inv.status] ?? inv.status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {permissions.canDownloadInvoice !== false && (
                                            <a
                                                href={`/api/admin/factures/${inv.id}/pdf`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Button variant="ghost" size="icon" title="Télécharger PDF">
                                                    <FileDown className="size-4" />
                                                </Button>
                                            </a>
                                        )}
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
