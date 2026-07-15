import React, { useEffect, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { api } from '../../utils/api';

function FaqItem({ question, answer }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="border-b last:border-b-0">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full items-center justify-between gap-4 py-5 text-left text-base font-medium text-foreground hover:text-primary transition-colors"
            >
                <span>{question}</span>
                <ChevronDown
                    className={`size-5 shrink-0 text-muted-foreground transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
                />
            </button>
            {open && (
                <div className="pb-5 text-sm text-muted-foreground leading-relaxed whitespace-pre-line">
                    {answer}
                </div>
            )}
        </div>
    );
}

export default function FaqPage({ urls }) {
    const [items, setItems]   = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError]   = useState(null);

    useEffect(() => {
        api.get(urls.faq)
            .then(data => setItems(data ?? []))
            .catch(() => setError('Impossible de charger la FAQ.'))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div className="mx-auto max-w-2xl px-4 py-16">
            <div className="mb-10 text-center">
                <h1 className="text-3xl font-bold tracking-tight">Questions fréquentes</h1>
                <p className="mt-3 text-muted-foreground">Retrouvez les réponses aux questions les plus courantes.</p>
            </div>

            {loading && (
                <div className="flex justify-center py-12">
                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            )}

            {error && (
                <p className="text-center text-sm text-destructive">{error}</p>
            )}

            {!loading && !error && items.length === 0 && (
                <p className="text-center text-sm text-muted-foreground">Aucune question pour le moment.</p>
            )}

            {!loading && !error && items.length > 0 && (
                <div className="rounded-lg border bg-card px-6">
                    {items.map(item => (
                        <FaqItem key={item.id} question={item.question} answer={item.answer} />
                    ))}
                </div>
            )}
        </div>
    );
}
