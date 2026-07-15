import React, { useState, useEffect } from 'react';
import { api, getErrorMessage } from '../../utils/api';

export default function Profil({ urls }) {
    const [profile, setProfile]   = useState(null);
    const [loading, setLoading]   = useState(true);

    useEffect(() => {
        api.get(urls.profile)
            .then(setProfile)
            .finally(() => setLoading(false));
    }, []);

    if (loading) return <Skeleton />;

    return (
        <div className="space-y-8">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Mon profil</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Gérez vos informations personnelles et votre mot de passe.
                </p>
            </div>

            <InfoForm profile={profile} urls={urls} onSaved={setProfile} />
            <PasswordForm urls={urls} />
        </div>
    );
}

/* ── Formulaire informations ── */
function InfoForm({ profile, urls, onSaved }) {
    const [firstName, setFirstName] = useState(profile?.firstName ?? '');
    const [lastName, setLastName]   = useState(profile?.lastName ?? '');
    const [phone, setPhone]         = useState(profile?.phone ?? '');
    const [loading, setLoading]     = useState(false);
    const [success, setSuccess]     = useState(false);
    const [error, setError]         = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess(false);
        setLoading(true);
        try {
            const data = await api.put(urls.profileUpdate, { firstName, lastName, phone: phone || null });
            onSaved(data);
            setSuccess(true);
        } catch (err) {
            setError(getErrorMessage(err));
        } finally {
            setLoading(false);
        }
    };

    return (
        <section className="rounded-xl border border-border bg-card p-6 space-y-5">
            <div>
                <h2 className="text-base font-semibold">Informations personnelles</h2>
                <p className="text-sm text-muted-foreground">Ces informations sont utilisées pour vos livraisons.</p>
            </div>

            {/* Email (lecture seule) */}
            <div className="space-y-1.5">
                <label className="text-sm font-medium">Adresse e-mail</label>
                <p className="rounded-lg border border-input bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                    {profile?.email}
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Prénom" id="firstName" value={firstName} onChange={setFirstName} required />
                    <Field label="Nom" id="lastName" value={lastName} onChange={setLastName} required />
                </div>
                <Field label="Téléphone" id="phone" value={phone} onChange={setPhone} type="tel" />

                {error && <p className="text-sm text-destructive">{error}</p>}
                {success && <p className="text-sm text-green-600">Informations mises à jour.</p>}

                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={loading}
                        className="rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {loading ? 'Enregistrement…' : 'Enregistrer'}
                    </button>
                </div>
            </form>
        </section>
    );
}

/* ── Formulaire mot de passe ── */
function PasswordForm({ urls }) {
    const [current, setCurrent]   = useState('');
    const [next, setNext]         = useState('');
    const [confirm, setConfirm]   = useState('');
    const [loading, setLoading]   = useState(false);
    const [success, setSuccess]   = useState(false);
    const [error, setError]       = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess(false);
        setLoading(true);
        try {
            await api.put(urls.passwordUpdate, {
                currentPassword:    current,
                newPassword:        next,
                newPasswordConfirm: confirm,
            });
            setSuccess(true);
            setCurrent(''); setNext(''); setConfirm('');
        } catch (err) {
            setError(getErrorMessage(err));
        } finally {
            setLoading(false);
        }
    };

    return (
        <section className="rounded-xl border border-border bg-card p-6 space-y-5">
            <div>
                <h2 className="text-base font-semibold">Changer de mot de passe</h2>
                <p className="text-sm text-muted-foreground">Utilisez un mot de passe d'au moins 8 caractères.</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
                <Field
                    label="Mot de passe actuel" id="current" type="password"
                    value={current} onChange={setCurrent} required autoComplete="current-password"
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Nouveau mot de passe" id="new" type="password"
                        value={next} onChange={setNext} required autoComplete="new-password"
                    />
                    <Field
                        label="Confirmer" id="confirm" type="password"
                        value={confirm} onChange={setConfirm} required autoComplete="new-password"
                    />
                </div>

                {error && <p className="text-sm text-destructive">{error}</p>}
                {success && <p className="text-sm text-green-600">Mot de passe mis à jour.</p>}

                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={loading}
                        className="rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {loading ? 'Mise à jour…' : 'Mettre à jour'}
                    </button>
                </div>
            </form>
        </section>
    );
}

function Field({ label, id, value, onChange, type = 'text', required = false, autoComplete }) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={id} className="text-sm font-medium">
                {label}{required && <span className="text-destructive ml-0.5">*</span>}
            </label>
            <input
                id={id}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                required={required}
                autoComplete={autoComplete}
                className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            />
        </div>
    );
}

function Skeleton() {
    return (
        <div className="space-y-8 animate-pulse">
            <div className="h-7 w-40 rounded bg-muted" />
            <div className="h-48 rounded-xl bg-muted" />
            <div className="h-48 rounded-xl bg-muted" />
        </div>
    );
}
