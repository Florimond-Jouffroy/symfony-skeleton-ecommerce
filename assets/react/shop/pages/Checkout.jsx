import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { CheckCircle, ChevronRight, Lock, Package, ShoppingBag, Tag, UserPlus, X } from 'lucide-react';
import { api, ApiError, getErrorMessage } from '../../utils/api';
import { useCart } from '../context/CartContext';

function formatPrice(cents) {
    return (cents / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

function computePromoDiscount(promo, subtotalCents) {
    if (!promo || !subtotalCents) return 0;
    if (promo.type === 'percent') return Math.round(subtotalCents * Number(promo.value) / 100);
    return Math.min(Number(promo.value), subtotalCents);
}

const EMPTY_ADDRESS = {
    firstName: '', lastName: '', phone: '',
    line1: '', line2: '', city: '', postalCode: '', country: 'France',
};

export default function Checkout({ urls }) {
    const navigate   = useNavigate();
    const { cart, loading: cartLoading } = useCart();

    const [authStatus, setAuthStatus]           = useState('checking'); // 'checking' | 'ok' | 'guest'
    const [step, setStep]                       = useState(1);
    const [address, setAddress]                 = useState(EMPTY_ADDRESS);
    const [customerNote, setCustomerNote]       = useState('');
    const [shippingMethods, setShippingMethods] = useState([]);
    const [selectedShipping, setSelectedShipping] = useState(null);
    const [loadingShipping, setLoadingShipping] = useState(true);
    const [submitting, setSubmitting]           = useState(false);
    const [error, setError]                     = useState('');
    const [orderNumber, setOrderNumber]         = useState('');
    const [promo, setPromo]                     = useState(null); // {code, type, value}

    // Check auth + pre-fill form from profile
    useEffect(() => {
        api.get(urls.profile)
            .then((profile) => {
                setAuthStatus('ok');
                if (profile?.firstName || profile?.lastName) {
                    setAddress((a) => ({
                        ...a,
                        firstName: profile.firstName ?? a.firstName,
                        lastName:  profile.lastName  ?? a.lastName,
                        phone:     profile.phone     ?? a.phone,
                    }));
                }
            })
            .catch((err) => {
                if (err instanceof ApiError && err.status === 401) {
                    setAuthStatus('guest');
                } else {
                    // Other error (network, etc.) — allow to proceed, API will reject if needed
                    setAuthStatus('ok');
                }
            });
    }, []);

    // Load shipping methods when cart changes
    useEffect(() => {
        if (cartLoading) return;
        setLoadingShipping(true);
        api.get(urls.shipping, { subtotal: cart.subtotal })
            .then((data) => {
                setShippingMethods(data);
                if (data.length > 0 && !selectedShipping) {
                    setSelectedShipping(data[0]);
                }
            })
            .catch(() => {})
            .finally(() => setLoadingShipping(false));
    }, [cart.subtotal, cartLoading]);

    // Fetch promo actif en session (utile après refresh de page)
    useEffect(() => {
        api.get(urls.promo ?? '/api/boutique/panier/promo')
            .then(data => { if (data) setPromo(data); })
            .catch(() => {});
    }, []);

    const addrField = (key, value) => setAddress((a) => ({ ...a, [key]: value }));

    const handleStep1Submit = (e) => {
        e.preventDefault();
        const required = ['firstName', 'lastName', 'line1', 'city', 'postalCode'];
        for (const f of required) {
            if (!address[f].trim()) {
                setError('Veuillez remplir tous les champs obligatoires.');
                return;
            }
        }
        if (!selectedShipping) {
            setError('Veuillez choisir une méthode de livraison.');
            return;
        }
        setError('');
        setStep(2);
        window.scrollTo(0, 0);
    };

    const handleConfirm = async () => {
        setSubmitting(true);
        setError('');
        try {
            const data = await api.post(urls.checkout, {
                shippingMethodId: selectedShipping.id,
                shippingAddress:  address,
                customerNote:     customerNote || null,
            });
            setOrderNumber(data.orderNumber);
            // Refresh cart badge (cart cleared server-side)
            window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { count: 0 } }));
            setStep(3);
            window.scrollTo(0, 0);
        } catch (err) {
            setError(getErrorMessage(err));
        } finally {
            setSubmitting(false);
        }
    };

    if (cartLoading || authStatus === 'checking') return <PageSkeleton />;

    // Not logged in → show auth gate
    if (authStatus === 'guest') {
        return <AuthGate cart={cart} />;
    }

    if (cart.items.length === 0 && step < 3) {
        return (
            <div className="mx-auto max-w-2xl px-4 py-28 text-center">
                <p className="text-muted-foreground">Votre panier est vide.</p>
                <button onClick={() => navigate('/')} className="mt-3 text-sm font-medium text-primary hover:underline">
                    Retour à la boutique
                </button>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-5xl px-4 sm:px-6 py-10">
            {/* Progress */}
            {step < 3 && <StepBar step={step} />}

            {step === 1 && (
                <Step1
                    address={address}
                    addrField={addrField}
                    customerNote={customerNote}
                    setCustomerNote={setCustomerNote}
                    shippingMethods={shippingMethods}
                    selectedShipping={selectedShipping}
                    setSelectedShipping={setSelectedShipping}
                    loadingShipping={loadingShipping}
                    cart={cart}
                    promo={promo}
                    setPromo={setPromo}
                    promoUrl={urls.promo}
                    error={error}
                    onSubmit={handleStep1Submit}
                    onBack={() => navigate('/panier')}
                />
            )}

            {step === 2 && (
                <Step2
                    address={address}
                    selectedShipping={selectedShipping}
                    customerNote={customerNote}
                    cart={cart}
                    promo={promo}
                    error={error}
                    submitting={submitting}
                    onConfirm={handleConfirm}
                    onBack={() => { setStep(1); window.scrollTo(0, 0); }}
                />
            )}

            {step === 3 && (
                <Step3 orderNumber={orderNumber} navigate={navigate} />
            )}
        </div>
    );
}

/* ── Step bar ── */
function StepBar({ step }) {
    const steps = ['Livraison', 'Récapitulatif', 'Confirmation'];
    return (
        <div className="mb-10 flex items-center justify-center gap-0">
            {steps.map((label, i) => {
                const n = i + 1;
                const active = n === step;
                const done   = n < step;
                return (
                    <React.Fragment key={n}>
                        <div className="flex flex-col items-center gap-1">
                            <div className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold transition-colors ${
                                done ? 'bg-primary text-primary-foreground' :
                                active ? 'bg-primary text-primary-foreground ring-4 ring-primary/20' :
                                'bg-muted text-muted-foreground'
                            }`}>
                                {done ? <CheckCircle className="h-4 w-4" /> : n}
                            </div>
                            <span className={`text-xs ${active ? 'font-medium text-foreground' : 'text-muted-foreground'}`}>
                                {label}
                            </span>
                        </div>
                        {i < steps.length - 1 && (
                            <div className={`mx-3 mb-4 h-px w-16 sm:w-24 ${n < step ? 'bg-primary' : 'bg-border'}`} />
                        )}
                    </React.Fragment>
                );
            })}
        </div>
    );
}

/* ── Promo code input ── */
function PromoInput({ promo, setPromo, promoUrl }) {
    const [input, setInput]     = useState('');
    const [loading, setLoading] = useState(false);
    const [promoError, setPromoError] = useState('');

    const handleApply = async () => {
        if (!input.trim() || loading) return;
        setLoading(true);
        setPromoError('');
        try {
            const data = await api.post(promoUrl ?? '/api/boutique/panier/promo', { code: input.trim() });
            setPromo(data);
            setInput('');
        } catch (err) {
            setPromoError(getErrorMessage(err));
        } finally {
            setLoading(false);
        }
    };

    const handleRemove = async () => {
        try {
            await api.delete(promoUrl ?? '/api/boutique/panier/promo');
        } catch { /* ignore */ }
        setPromo(null);
        setPromoError('');
    };

    if (promo) {
        return (
            <div className="flex items-center justify-between rounded-lg bg-green-50 border border-green-200 px-3 py-2 text-sm">
                <div className="flex items-center gap-2 text-green-700">
                    <Tag className="size-3.5 shrink-0" />
                    <span className="font-mono font-semibold">{promo.code}</span>
                    <span className="text-green-600">
                        − {promo.type === 'percent' ? `${promo.value} %` : formatPrice(promo.value)}
                    </span>
                </div>
                <button type="button" onClick={handleRemove} className="text-green-600 hover:text-green-800 transition-colors">
                    <X className="size-4" />
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-1.5">
            <div className="flex gap-2">
                <input
                    value={input}
                    onChange={e => setInput(e.target.value.toUpperCase())}
                    onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), handleApply())}
                    placeholder="Code promo"
                    className="flex-1 rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono uppercase placeholder:normal-case placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                />
                <button
                    type="button"
                    onClick={handleApply}
                    disabled={loading || !input.trim()}
                    className="rounded-lg border px-3 py-2 text-sm font-medium hover:bg-accent transition-colors disabled:opacity-50"
                >
                    {loading ? '…' : 'Appliquer'}
                </button>
            </div>
            {promoError && <p className="text-xs text-destructive">{promoError}</p>}
        </div>
    );
}

/* ── Step 1 : Adresse + livraison ── */
function Step1({ address, addrField, customerNote, setCustomerNote, shippingMethods, selectedShipping, setSelectedShipping, loadingShipping, cart, promo, setPromo, promoUrl, error, onSubmit, onBack }) {
    return (
        <form onSubmit={onSubmit}>
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3 lg:items-start">
                <div className="space-y-6 lg:col-span-2">
                    {/* Address */}
                    <section className="rounded-xl border border-border bg-card p-5 space-y-4">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Adresse de livraison</h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Prénom *" id="firstName" value={address.firstName} onChange={(v) => addrField('firstName', v)} required />
                            <Field label="Nom *" id="lastName" value={address.lastName} onChange={(v) => addrField('lastName', v)} required />
                        </div>
                        <Field label="Téléphone" id="phone" type="tel" value={address.phone} onChange={(v) => addrField('phone', v)} />
                        <Field label="Adresse *" id="line1" value={address.line1} onChange={(v) => addrField('line1', v)} required placeholder="123 rue de la Paix" />
                        <Field label="Complément d'adresse" id="line2" value={address.line2} onChange={(v) => addrField('line2', v)} placeholder="Appartement, bâtiment…" />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Code postal *" id="postalCode" value={address.postalCode} onChange={(v) => addrField('postalCode', v)} required />
                            <Field label="Ville *" id="city" value={address.city} onChange={(v) => addrField('city', v)} required />
                        </div>
                        <Field label="Pays" id="country" value={address.country} onChange={(v) => addrField('country', v)} />
                    </section>

                    {/* Shipping methods */}
                    <section className="rounded-xl border border-border bg-card p-5 space-y-3">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Méthode de livraison</h2>
                        {loadingShipping ? (
                            <div className="space-y-2 animate-pulse">
                                {[1, 2].map((i) => <div key={i} className="h-14 rounded-lg bg-muted" />)}
                            </div>
                        ) : shippingMethods.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Aucune méthode disponible.</p>
                        ) : (
                            shippingMethods.map((method) => (
                                <label
                                    key={method.id}
                                    className={`flex cursor-pointer items-center justify-between rounded-lg border p-4 transition-colors ${
                                        selectedShipping?.id === method.id
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:bg-accent/50'
                                    }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <input
                                            type="radio"
                                            name="shipping"
                                            value={method.id}
                                            checked={selectedShipping?.id === method.id}
                                            onChange={() => setSelectedShipping(method)}
                                            className="accent-primary"
                                        />
                                        <div>
                                            <p className="text-sm font-medium">{method.name}</p>
                                            {method.description && (
                                                <p className="text-xs text-muted-foreground">{method.description}</p>
                                            )}
                                        </div>
                                    </div>
                                    <span className={`text-sm font-semibold ${method.effectivePrice === 0 ? 'text-green-600' : ''}`}>
                                        {method.effectivePrice === 0 ? 'Gratuit' : formatPrice(method.effectivePrice)}
                                    </span>
                                </label>
                            ))
                        )}
                    </section>

                    {/* Customer note */}
                    <section className="rounded-xl border border-border bg-card p-5 space-y-2">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Note pour la livraison</h2>
                        <textarea
                            value={customerNote}
                            onChange={(e) => setCustomerNote(e.target.value)}
                            rows={3}
                            placeholder="Instructions particulières pour le livreur (optionnel)"
                            className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        />
                    </section>
                </div>

                {/* Order summary sidebar */}
                <OrderSummary cart={cart} selectedShipping={selectedShipping} promo={promo} setPromo={setPromo} promoUrl={promoUrl} error={error}>
                    <button
                        type="button"
                        onClick={onBack}
                        className="w-full rounded-xl border border-border py-2.5 text-sm text-muted-foreground hover:bg-accent transition-colors"
                    >
                        ← Retour au panier
                    </button>
                    <button
                        type="submit"
                        className="w-full flex items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                    >
                        Continuer vers le récapitulatif
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </OrderSummary>
            </div>
        </form>
    );
}

/* ── Step 2 : Récapitulatif ── */
function Step2({ address, selectedShipping, customerNote, cart, promo, error, submitting, onConfirm, onBack }) {
    const shippingCost = selectedShipping?.effectivePrice ?? 0;
    const discount     = computePromoDiscount(promo, cart.subtotal);
    const total        = Math.max(0, cart.subtotal - discount + shippingCost);

    return (
        <div className="grid grid-cols-1 gap-8 lg:grid-cols-3 lg:items-start">
            <div className="space-y-6 lg:col-span-2">
                {/* Address recap */}
                <section className="rounded-xl border border-border bg-card p-5 space-y-2">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Adresse de livraison</h2>
                    <address className="not-italic text-sm leading-relaxed">
                        <p className="font-medium">{address.firstName} {address.lastName}</p>
                        <p>{address.line1}</p>
                        {address.line2 && <p>{address.line2}</p>}
                        <p>{address.postalCode} {address.city}</p>
                        <p>{address.country}</p>
                        {address.phone && <p className="text-muted-foreground">{address.phone}</p>}
                    </address>
                </section>

                {/* Shipping recap */}
                <section className="rounded-xl border border-border bg-card p-5 space-y-1">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Livraison</h2>
                    <div className="flex items-center justify-between text-sm">
                        <span>{selectedShipping?.name}</span>
                        <span className={shippingCost === 0 ? 'text-green-600 font-medium' : 'font-medium'}>
                            {shippingCost === 0 ? 'Gratuit' : formatPrice(shippingCost)}
                        </span>
                    </div>
                    {selectedShipping?.description && (
                        <p className="text-xs text-muted-foreground">{selectedShipping.description}</p>
                    )}
                </section>

                {/* Items */}
                <section className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="px-5 py-3 border-b border-border bg-muted/30">
                        <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Articles ({cart.itemCount})</h2>
                    </div>
                    <ul className="divide-y divide-border">
                        {cart.items.map((item) => (
                            <li key={item.key} className="flex items-center gap-3 px-5 py-3">
                                <div className="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-muted">
                                    {item.imageUrl && <img src={item.imageUrl} alt={item.productName} className="h-full w-full object-cover" />}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate">{item.productName}</p>
                                    {item.variantName && <p className="text-xs text-muted-foreground">{item.variantName}</p>}
                                    <p className="text-xs text-muted-foreground">Qté {item.quantity}</p>
                                </div>
                                <span className="text-sm font-semibold shrink-0">{formatPrice(item.lineTotal)}</span>
                            </li>
                        ))}
                    </ul>
                </section>

                {customerNote && (
                    <section className="rounded-xl border border-border bg-card p-5 space-y-1">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Note</h2>
                        <p className="text-sm text-muted-foreground">{customerNote}</p>
                    </section>
                )}
            </div>

            {/* Totals + actions */}
            <div className="rounded-xl border border-border bg-card p-5 space-y-4 lg:sticky lg:top-24">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Récapitulatif</h2>
                <dl className="space-y-2 text-sm">
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Sous-total</dt>
                        <dd>{formatPrice(cart.subtotal)}</dd>
                    </div>
                    {discount > 0 && (
                        <div className="flex justify-between text-green-600">
                            <dt>Réduction{promo ? ` (${promo.code})` : ''}</dt>
                            <dd>− {formatPrice(discount)}</dd>
                        </div>
                    )}
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Livraison</dt>
                        <dd className={shippingCost === 0 ? 'text-green-600' : ''}>{shippingCost === 0 ? 'Gratuite' : formatPrice(shippingCost)}</dd>
                    </div>
                    <div className="flex justify-between border-t border-border pt-2 font-semibold">
                        <dt>Total</dt>
                        <dd>{formatPrice(total)}</dd>
                    </div>
                </dl>

                {error && <p className="rounded-lg bg-destructive/10 px-3 py-2 text-xs text-destructive">{error}</p>}

                <button
                    onClick={onConfirm}
                    disabled={submitting}
                    className="w-full flex items-center justify-center gap-2 rounded-xl bg-primary py-3.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
                >
                    <Lock className="h-4 w-4" />
                    {submitting ? 'Traitement en cours…' : 'Confirmer la commande'}
                </button>
                <button
                    onClick={onBack}
                    disabled={submitting}
                    className="w-full rounded-xl border border-border py-2.5 text-sm text-muted-foreground hover:bg-accent transition-colors"
                >
                    ← Modifier la livraison
                </button>
            </div>
        </div>
    );
}

/* ── Step 3 : Confirmation ── */
function Step3({ orderNumber, navigate }) {
    return (
        <div className="mx-auto max-w-lg text-center py-10 space-y-6">
            <div className="flex justify-center">
                <div className="flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                    <CheckCircle className="h-10 w-10 text-green-600" />
                </div>
            </div>
            <div className="space-y-2">
                <h1 className="text-2xl font-bold tracking-tight">Commande confirmée !</h1>
                <p className="text-muted-foreground">
                    Merci pour votre commande. Vous allez recevoir un e-mail de confirmation.
                </p>
                <p className="text-sm font-medium">
                    Numéro de commande : <span className="font-bold text-foreground">{orderNumber}</span>
                </p>
            </div>
            <div className="flex flex-col sm:flex-row gap-3 justify-center pt-2">
                <a
                    href={`/mon-compte/commandes/${orderNumber}`}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    <Package className="h-4 w-4" />
                    Suivre ma commande
                </a>
                <button
                    onClick={() => navigate('/')}
                    className="inline-flex items-center justify-center rounded-xl border border-border px-6 py-3 text-sm font-medium text-muted-foreground hover:bg-accent transition-colors"
                >
                    Continuer mes achats
                </button>
            </div>
        </div>
    );
}

/* ── Shared: Order summary sidebar ── */
function OrderSummary({ cart, selectedShipping, promo, setPromo, promoUrl, error, children }) {
    const shippingCost = selectedShipping?.effectivePrice ?? null;
    const discount     = computePromoDiscount(promo, cart.subtotal);
    const total        = shippingCost !== null ? Math.max(0, cart.subtotal - discount + shippingCost) : null;

    return (
        <div className="rounded-xl border border-border bg-card p-5 space-y-4 lg:sticky lg:top-24">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                Commande ({cart.itemCount} article{cart.itemCount !== 1 ? 's' : ''})
            </h2>
            <ul className="space-y-2">
                {cart.items.map((item) => (
                    <li key={item.key} className="flex items-center justify-between gap-2 text-sm">
                        <span className="truncate text-muted-foreground">
                            {item.productName}{item.variantName ? ` – ${item.variantName}` : ''} × {item.quantity}
                        </span>
                        <span className="shrink-0 font-medium">{formatPrice(item.lineTotal)}</span>
                    </li>
                ))}
            </ul>
            <dl className="space-y-1.5 text-sm border-t border-border pt-3">
                <div className="flex justify-between text-muted-foreground">
                    <dt>Sous-total</dt>
                    <dd>{formatPrice(cart.subtotal)}</dd>
                </div>
                {discount > 0 && (
                    <div className="flex justify-between text-green-600">
                        <dt>Réduction</dt>
                        <dd>− {formatPrice(discount)}</dd>
                    </div>
                )}
                {shippingCost !== null && (
                    <div className="flex justify-between text-muted-foreground">
                        <dt>Livraison</dt>
                        <dd className={shippingCost === 0 ? 'text-green-600' : ''}>{shippingCost === 0 ? 'Gratuite' : formatPrice(shippingCost)}</dd>
                    </div>
                )}
                {total !== null && (
                    <div className="flex justify-between border-t border-border pt-2 font-semibold">
                        <dt>Total</dt>
                        <dd>{formatPrice(total)}</dd>
                    </div>
                )}
            </dl>
            {setPromo && (
                <PromoInput promo={promo} setPromo={setPromo} promoUrl={promoUrl} />
            )}
            {error && <p className="rounded-lg bg-destructive/10 px-3 py-2 text-xs text-destructive">{error}</p>}
            <div className="space-y-2">{children}</div>
        </div>
    );
}

/* ── Field component ── */
function Field({ label, id, value, onChange, type = 'text', required = false, placeholder }) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={id} className="text-sm font-medium">{label}</label>
            <input
                id={id}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                required={required}
                placeholder={placeholder}
                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            />
        </div>
    );
}

/* ── Auth gate : shown to non-authenticated users ── */
function AuthGate({ cart }) {
    const redirect = encodeURIComponent('/boutique/commander');

    return (
        <div className="mx-auto max-w-5xl px-4 sm:px-6 py-10">
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:items-center">
                {/* Message */}
                <div className="space-y-6">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-bold tracking-tight">
                            Finalisez votre commande
                        </h1>
                        <p className="text-muted-foreground">
                            Connectez-vous ou créez un compte pour passer commande.
                            Votre panier est sauvegardé et vous attend.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                        <ShoppingBag className="h-4 w-4 shrink-0" />
                        {cart.itemCount} article{cart.itemCount !== 1 ? 's' : ''} dans votre panier — {formatPrice(cart.subtotal)}
                    </div>

                    <div className="flex flex-col gap-3">
                        <a
                            href={`/connexion?redirect=${redirect}`}
                            className="flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
                        >
                            <Lock className="h-4 w-4" />
                            Se connecter
                        </a>
                        <a
                            href={`/inscription?redirect=${redirect}`}
                            className="flex items-center justify-center gap-2 rounded-xl border border-border px-6 py-3.5 text-sm font-semibold text-foreground hover:bg-accent transition-colors"
                        >
                            <UserPlus className="h-4 w-4" />
                            Créer un compte
                        </a>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        La création de compte est gratuite et prend moins d'une minute.
                    </p>
                </div>

                {/* Cart mini-summary */}
                {cart.items.length > 0 && (
                    <div className="rounded-xl border border-border bg-card p-5 space-y-4">
                        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">
                            Votre panier
                        </h2>
                        <ul className="space-y-3">
                            {cart.items.map((item) => (
                                <li key={item.key} className="flex items-center gap-3">
                                    <div className="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-muted">
                                        {item.imageUrl
                                            ? <img src={item.imageUrl} alt={item.productName} className="h-full w-full object-cover" />
                                            : <div className="h-full w-full flex items-center justify-center text-muted-foreground/30"><ShoppingBag className="h-5 w-5" /></div>
                                        }
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium truncate">{item.productName}</p>
                                        {item.variantName && <p className="text-xs text-muted-foreground">{item.variantName}</p>}
                                        <p className="text-xs text-muted-foreground">× {item.quantity}</p>
                                    </div>
                                    <span className="text-sm font-semibold shrink-0">{formatPrice(item.lineTotal)}</span>
                                </li>
                            ))}
                        </ul>
                        <div className="flex justify-between border-t border-border pt-3 text-sm font-semibold">
                            <span>Sous-total</span>
                            <span>{formatPrice(cart.subtotal)}</span>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function PageSkeleton() {
    return (
        <div className="mx-auto max-w-5xl px-4 py-10 animate-pulse space-y-6">
            <div className="flex justify-center gap-6 mb-10">
                {[1, 2, 3].map((i) => <div key={i} className="h-8 w-24 rounded-full bg-muted" />)}
            </div>
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-4">
                    <div className="h-64 rounded-xl bg-muted" />
                    <div className="h-32 rounded-xl bg-muted" />
                </div>
                <div className="h-64 rounded-xl bg-muted" />
            </div>
        </div>
    );
}
