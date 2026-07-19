import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

export default function Alerts() {
    const { can } = useAuth();
    const [alerts, setAlerts] = useState([]);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    async function load() {
        const data = await api('/alerts?unresolved_only=1');
        setAlerts(data.data || []);
    }

    useEffect(() => {
        load().catch((err) => setError(err.message));
    }, []);

    async function generate() {
        try {
            const res = await api('/alerts/generate', { method: 'POST' });
            setMessage(res.message);
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    async function resolve(id) {
        try {
            await api(`/alerts/${id}/resolve`, { method: 'POST' });
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    return (
        <div className="ac-fade-up space-y-8">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="font-display text-3xl font-bold">Moteur d&apos;alertes</h1>
                    <p className="mt-1 text-[var(--color-ink-soft)]">
                        Assurances, entretiens et contrôles techniques.
                    </p>
                </div>
                {can('gestionnaire', 'super_admin') && (
                    <button type="button" className="ac-btn ac-btn-primary" onClick={generate}>
                        Générer les alertes
                    </button>
                )}
            </div>

            {error && <p className="text-[var(--color-signal)]">{error}</p>}
            {message && <p className="text-[var(--color-teal)]">{message}</p>}

            <div className="space-y-3">
                {alerts.length === 0 && (
                    <p className="ac-panel rounded-lg p-5 text-sm text-[var(--color-ink-soft)]">
                        Aucune alerte ouverte.
                    </p>
                )}
                {alerts.map((alert) => (
                    <article key={alert.id} className="ac-panel rounded-lg p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-[var(--color-signal)]">
                                    {alert.severity} · {alert.type}
                                </p>
                                <h2 className="mt-1 font-semibold">{alert.title}</h2>
                                <p className="mt-1 text-sm text-[var(--color-ink-soft)]">
                                    {alert.message}
                                </p>
                                {alert.vehicle && (
                                    <p className="mt-2 text-xs text-[var(--color-ink-soft)]">
                                        {alert.vehicle.registration_number} — {alert.vehicle.brand}{' '}
                                        {alert.vehicle.model}
                                    </p>
                                )}
                            </div>
                            {can('gestionnaire', 'super_admin') && (
                                <button
                                    type="button"
                                    className="ac-btn border border-black/10"
                                    onClick={() => resolve(alert.id)}
                                >
                                    Résoudre
                                </button>
                            )}
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}
