import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

export default function Assignments() {
    const { user, can } = useAuth();
    const [items, setItems] = useState([]);
    const [vehicles, setVehicles] = useState([]);
    const [drivers, setDrivers] = useState([]);
    const [form, setForm] = useState({ vehicle_id: '', driver_id: '', notes: '' });
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    async function load() {
        const [assignments, vehicleData] = await Promise.all([
            api('/assignments'),
            api('/vehicles'),
        ]);
        setItems(assignments.data || []);
        setVehicles(vehicleData.data || []);

        if (can('gestionnaire', 'super_admin')) {
            const users = await api('/drivers');
            setDrivers(users.data || []);
        }
    }

    useEffect(() => {
        load().catch((err) => setError(err.message));
    }, []);

    async function onAssign(e) {
        e.preventDefault();
        setError('');
        try {
            await api('/assignments', {
                method: 'POST',
                body: {
                    vehicle_id: Number(form.vehicle_id),
                    driver_id: Number(form.driver_id),
                    notes: form.notes || null,
                },
            });
            setMessage('Véhicule affecté.');
            setForm({ vehicle_id: '', driver_id: '', notes: '' });
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    async function acknowledge(id) {
        try {
            await api(`/assignments/${id}/acknowledge`, { method: 'POST' });
            setMessage('Prise en charge déclarée.');
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    return (
        <div className="ac-fade-up space-y-8">
            <div>
                <h1 className="font-display text-3xl font-bold">Affectations</h1>
                <p className="mt-1 text-[var(--color-ink-soft)]">
                    Attribution automatisée des véhicules aux chauffeurs.
                </p>
            </div>

            {can('gestionnaire', 'super_admin') && (
                <form onSubmit={onAssign} className="ac-panel grid gap-3 rounded-lg p-5 md:grid-cols-3">
                    <label className="text-sm font-medium">
                        Véhicule
                        <select
                            className="ac-input mt-1"
                            value={form.vehicle_id}
                            onChange={(e) => setForm((f) => ({ ...f, vehicle_id: e.target.value }))}
                            required
                        >
                            <option value="">Choisir…</option>
                            {vehicles.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.registration_number} — {v.brand} {v.model}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm font-medium">
                        Chauffeur
                        <select
                            className="ac-input mt-1"
                            value={form.driver_id}
                            onChange={(e) => setForm((f) => ({ ...f, driver_id: e.target.value }))}
                            required
                        >
                            <option value="">Choisir…</option>
                            {drivers.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm font-medium md:col-span-1">
                        Notes
                        <input
                            className="ac-input mt-1"
                            value={form.notes}
                            onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                        />
                    </label>
                    <div className="md:col-span-3">
                        <button className="ac-btn ac-btn-primary" type="submit">
                            Affecter
                        </button>
                    </div>
                </form>
            )}

            {error && <p className="text-[var(--color-signal)]">{error}</p>}
            {message && <p className="text-[var(--color-teal)]">{message}</p>}

            <div className="space-y-3">
                {items.map((a) => (
                    <article key={a.id} className="ac-panel rounded-lg p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-semibold">
                                    {a.vehicle?.registration_number} → {a.driver?.name}
                                </p>
                                <p className="text-sm text-[var(--color-ink-soft)]">
                                    Statut {a.status}
                                    {a.driver_acknowledged ? ' · prise en charge OK' : ' · en attente'}
                                </p>
                            </div>
                            {user?.role === 'chauffeur' &&
                                a.driver_id === user.id &&
                                a.status === 'active' &&
                                !a.driver_acknowledged && (
                                    <button
                                        type="button"
                                        className="ac-btn ac-btn-primary"
                                        onClick={() => acknowledge(a.id)}
                                    >
                                        Déclarer prise en charge
                                    </button>
                                )}
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}
