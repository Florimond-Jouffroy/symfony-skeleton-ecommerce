import React, { useState } from 'react';
import { CheckCircle } from 'lucide-react';
import { api } from '../../utils/api';

export default function ContactPage({ urls, isConnected, prefillEmail }) {
    const [form, setForm]     = useState({ name: '', email: prefillEmail, subject: '', body: '' });
    const [sending, setSending] = useState(false);
    const [error, setError]   = useState(null);
    const [done, setDone]     = useState(false);

    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        setSending(true);

        try {
            if (isConnected) {
                await api.post(urls.createTicketAuth, {
                    subject: form.subject,
                    body: form.body,
                });
            } else {
                await api.post(urls.createTicket, form);
            }
            setDone(true);
        } catch (err) {
            setError(err.message ?? 'Une erreur est survenue. Veuillez réessayer.');
        } finally {
            setSending(false);
        }
    };

    if (done) {
        return (
            <div className="mx-auto max-w-lg px-4 py-20 text-center">
                <CheckCircle className="mx-auto mb-4 size-12 text-green-500" />
                <h1 className="text-2xl font-bold">Message envoyé !</h1>
                {isConnected ? (
                    <p className="mt-3 text-muted-foreground">
                        Votre demande a été créée. Vous pouvez la suivre depuis{' '}
                        <a href="/mon-compte/support" className="text-primary underline underline-offset-2">votre espace compte</a>.
                    </p>
                ) : (
                    <p className="mt-3 text-muted-foreground">
                        Un e-mail de confirmation vous a été envoyé à <strong>{form.email}</strong> avec un lien pour suivre votre demande et consulter nos réponses.
                    </p>
                )}
                <button
                    type="button"
                    onClick={() => { setDone(false); setForm({ name: '', email: prefillEmail, subject: '', body: '' }); }}
                    className="mt-6 rounded-md border px-4 py-2 text-sm hover:bg-accent transition-colors"
                >
                    Envoyer un autre message
                </button>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-lg px-4 py-16">
            <div className="mb-8 text-center">
                <h1 className="text-3xl font-bold tracking-tight">Nous contacter</h1>
                <p className="mt-3 text-muted-foreground">
                    Un problème lors de votre achat ? Une question ? Remplissez ce formulaire, nous vous répondons rapidement.
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border bg-card p-6">
                {error && (
                    <div className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {!isConnected && (
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium">Votre nom <span className="text-destructive">*</span></label>
                            <input
                                required
                                value={form.name}
                                onChange={e => set('name', e.target.value)}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                                placeholder="Jean Dupont"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Votre e-mail <span className="text-destructive">*</span></label>
                            <input
                                required
                                type="email"
                                value={form.email}
                                onChange={e => set('email', e.target.value)}
                                className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                                placeholder="jean@exemple.fr"
                            />
                        </div>
                    </div>
                )}

                <div>
                    <label className="mb-1 block text-sm font-medium">Sujet <span className="text-destructive">*</span></label>
                    <input
                        required
                        value={form.subject}
                        onChange={e => set('subject', e.target.value)}
                        className="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Ex. Problème avec ma commande #1234"
                    />
                </div>

                <div>
                    <label className="mb-1 block text-sm font-medium">Message <span className="text-destructive">*</span></label>
                    <textarea
                        required
                        rows={6}
                        value={form.body}
                        onChange={e => set('body', e.target.value)}
                        className="w-full resize-y rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Décrivez votre problème ou votre question…"
                    />
                </div>

                <button
                    type="submit"
                    disabled={sending}
                    className="w-full rounded-md bg-primary py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
                >
                    {sending ? 'Envoi en cours…' : 'Envoyer le message'}
                </button>

                {!isConnected && (
                    <p className="text-center text-xs text-muted-foreground">
                        Déjà un ticket ?{' '}
                        <a href="/contact/suivi" className="text-primary underline underline-offset-2">
                            Suivre ma demande
                        </a>
                    </p>
                )}
            </form>
        </div>
    );
}
