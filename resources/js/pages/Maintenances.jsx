import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

const TYPES = [
    { value: 'revision', label: 'Révision' },
    { value: 'reparation', label: 'Réparation' },
    { value: 'vidange', label: 'Vidange' },
    { value: 'pneus', label: 'Pneus' },
    { value: 'freins', label: 'Freins' },
    { value: 'controle', label: 'Contrôle' },
    { value: 'autre', label: 'Autre' },
];

const emptyForm = {
    vehicle_id: '',
    type: 'revision',
    title: '',
    description: '',
    parts_changed: '',
    mileage_at_service: '',
    cost: '',
    next_service_mileage: '',
    next_service_at: '',
};

export default function Maintenances() {
    const { can } = useAuth();
    const [items, setItems] = useState([]);
    const [vehicles, setVehicles] = useState([]);
    const [form, setForm] = useState(emptyForm);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    async function load() {
        const [m, v] = await Promise.all([api('/maintenances'), api('/vehicles')]);
        setItems(m.data || []);
        setVehicles(v.data || []);
    }

    useEffect(() => {
        load().catch((err) => setError(err.message));
    }, []);

    async function onSubmit(e) {
        e.preventDefault();
        setError('');
        setMessage('');

        const parts = form.parts_changed
            .split(',')
            .map((p) => p.trim())
            .filter(Boolean);

        try {
            await api('/maintenances', {
                method: 'POST',
                body: {
                    vehicle_id: Number(form.vehicle_id),
                    type: form.type,
                    title: form.title,
                    description: form.description || null,
                    parts_changed: parts,
                    mileage_at_service: Number(form.mileage_at_service),
                    cost: form.cost === '' ? 0 : Number(form.cost),
                    next_service_mileage: form.next_service_mileage
                        ? Number(form.next_service_mileage)
                        : null,
                    next_service_at: form.next_service_at || null,
                },
            });
            setForm(emptyForm);
            setMessage('Maintenance certifiée et ancrée on-chain.');
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    return (
        <div className="ac-fade-up space-y-8">
            <div>
                <h1 className="font-display text-3xl font-bold">Registre de maintenance</h1>
                <p className="mt-1 text-[var(--color-ink-soft)]">
                    Certification des interventions et pièces changées par le garagiste agréé.
                </p>
            </div>

            {can('garagiste', 'gestionnaire', 'super_admin') && (
                <form onSubmit={onSubmit} className="ac-panel grid gap-3 rounded-lg p-5 md:grid-cols-3">
                    <h2 className="font-display text-lg font-bold md:col-span-3">
                        Certifier une intervention
                    </h2>

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
                                    {v.registration_number} ({v.current_mileage} km)
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-sm font-medium">
                        Type
                        <select
                            className="ac-input mt-1"
                            value={form.type}
                            onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
                        >
                            {TYPES.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-sm font-medium">
                        Titre
                        <input
                            className="ac-input mt-1"
                            value={form.title}
                            onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                            required
                            placeholder="Vidange + filtres"
                        />
                    </label>

                    <label className="text-sm font-medium md:col-span-2">
                        Description
                        <input
                            className="ac-input mt-1"
                            value={form.description}
                            onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Km au service
                        <input
                            className="ac-input mt-1"
                            type="number"
                            min="0"
                            value={form.mileage_at_service}
                            onChange={(e) =>
                                setForm((f) => ({ ...f, mileage_at_service: e.target.value }))
                            }
                            required
                        />
                    </label>

                    <label className="text-sm font-medium md:col-span-2">
                        Pièces changées (séparées par des virgules)
                        <input
                            className="ac-input mt-1"
                            value={form.parts_changed}
                            onChange={(e) => setForm((f) => ({ ...f, parts_changed: e.target.value }))}
                            placeholder="Filtre à huile, Huile 5W30"
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Coût
                        <input
                            className="ac-input mt-1"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.cost}
                            onChange={(e) => setForm((f) => ({ ...f, cost: e.target.value }))}
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Prochain entretien (km)
                        <input
                            className="ac-input mt-1"
                            type="number"
                            min="0"
                            value={form.next_service_mileage}
                            onChange={(e) =>
                                setForm((f) => ({ ...f, next_service_mileage: e.target.value }))
                            }
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Prochain entretien (date)
                        <input
                            className="ac-input mt-1"
                            type="date"
                            value={form.next_service_at}
                            onChange={(e) => setForm((f) => ({ ...f, next_service_at: e.target.value }))}
                        />
                    </label>

                    <div className="md:col-span-3">
                        <button className="ac-btn ac-btn-primary" type="submit">
                            Certifier & ancrer
                        </button>
                    </div>
                </form>
            )}

            {error && <p className="text-[var(--color-signal)]">{error}</p>}
            {message && <p className="text-[var(--color-teal)]">{message}</p>}

            <div className="ac-panel overflow-x-auto rounded-lg p-5">
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead className="text-[var(--color-ink-soft)]">
                        <tr>
                            <th className="pb-3">Intervention</th>
                            <th className="pb-3">Véhicule</th>
                            <th className="pb-3">Garagiste</th>
                            <th className="pb-3">Km</th>
                            <th className="pb-3">Pièces</th>
                            <th className="pb-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-6 text-[var(--color-ink-soft)]">
                                    Aucune maintenance.
                                </td>
                            </tr>
                        )}
                        {items.map((m) => (
                            <tr key={m.id} className="border-t border-black/5">
                                <td className="py-3">
                                    <p className="font-medium">{m.title}</p>
                                    <p className="text-xs text-[var(--color-ink-soft)] capitalize">
                                        {m.type}
                                    </p>
                                </td>
                                <td className="py-3">{m.vehicle?.registration_number}</td>
                                <td className="py-3">{m.garage?.name || '—'}</td>
                                <td className="py-3">
                                    {m.mileage_at_service?.toLocaleString('fr-FR')}
                                </td>
                                <td className="py-3 text-xs">
                                    {(m.parts_changed || []).join(', ') || '—'}
                                </td>
                                <td className="py-3">
                                    {m.is_certified ? 'Certifié' : m.status}
                                    {m.blockchain_tx_hash && (
                                        <p className="font-mono text-xs text-[var(--color-ink-soft)]">
                                            {m.blockchain_tx_hash.slice(0, 12)}…
                                        </p>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
